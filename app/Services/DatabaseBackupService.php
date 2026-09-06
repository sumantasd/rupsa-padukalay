<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DatabaseBackup;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DatabaseBackupService
{
    protected string $backupDirectory;

    public function __construct()
    {
        $this->backupDirectory = storage_path('app/private/backups');
        if (! File::exists($this->backupDirectory)) {
            File::makeDirectory($this->backupDirectory, 0755, true, true);
        }
    }

    /**
     * Create a complete database SQL backup file.
     */
    public function createBackup(?User $user = null, string $type = 'manual'): DatabaseBackup
    {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "rupsa_database_{$timestamp}_" . Str::random(4) . ".sql";
        $filePath = $this->backupDirectory . DIRECTORY_SEPARATOR . $filename;

        try {
            $sqlContent = $this->generateDatabaseDump();
            File::put($filePath, $sqlContent);

            $fileSize = File::size($filePath);

            $backup = DatabaseBackup::create([
                'filename' => $filename,
                'file_path' => 'private/backups/' . $filename,
                'file_size' => $fileSize,
                'status' => 'completed',
                'type' => $type,
                'created_by' => $user?->id,
            ]);

            // Audit log creation
            if ($user) {
                AuditLog::create([
                    'audit_uuid' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'module' => 'Database',
                    'event_type' => 'Database Backup Created',
                    'auditable_type' => DatabaseBackup::class,
                    'auditable_id' => $backup->id,
                    'status' => 'success',
                    'reason_notes' => "Database backup created successfully: {$filename} ({$fileSize} bytes, type: {$type})",
                ]);
            }

            return $backup;
        } catch (\Throwable $e) {
            // Delete incomplete file if left behind
            if (File::exists($filePath)) {
                File::delete($filePath);
            }

            $failedBackup = DatabaseBackup::create([
                'filename' => $filename,
                'file_path' => 'private/backups/' . $filename,
                'file_size' => 0,
                'status' => 'failed',
                'type' => $type,
                'created_by' => $user?->id,
            ]);

            if ($user) {
                AuditLog::create([
                    'audit_uuid' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'module' => 'Database',
                    'event_type' => 'Database Backup Failed',
                    'status' => 'failed',
                    'reason_notes' => "Database backup failed: {$e->getMessage()}",
                ]);
            }

            throw new \RuntimeException("Database backup failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate complete SQL dump string.
     */
    protected function generateDatabaseDump(): string
    {
        $pdo = DB::connection()->getPdo();
        $dbName = DB::connection()->getDatabaseName();

        $dump = "-- RUPSA PADUKALAYA Database SQL Backup\n";
        $dump .= "-- Generated At: " . date('Y-m-d H:i:s') . "\n";
        $dump .= "-- Database: {$dbName}\n\n";

        $dump .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $dump .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $dump .= "SET TIME_ZONE = \"+00:00\";\n\n";

        // Get table list
        $tables = [];
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $results = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            foreach ($results as $row) {
                $tables[] = $row->name;
            }
        } else {
            $results = DB::select('SHOW TABLES');
            foreach ($results as $row) {
                $val = array_values((array) $row)[0];
                $tables[] = $val;
            }
        }

        foreach ($tables as $table) {
            $dump .= "-- --------------------------------------------------------\n";
            $dump .= "-- Table structure for table `{$table}`\n";
            $dump .= "-- --------------------------------------------------------\n\n";
            $dump .= "DROP TABLE IF EXISTS `{$table}`;\n";

            if ($driver === 'sqlite') {
                $createRes = DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", [$table]);
                $createSql = $createRes[0]->sql ?? '';
                $dump .= "{$createSql};\n\n";
            } else {
                $createRes = DB::select("SHOW CREATE TABLE `{$table}`");
                $createSql = $createRes[0]->{'Create Table'} ?? '';
                $dump .= "{$createSql};\n\n";
            }

            $dump .= "-- Dumping data for table `{$table}`\n";
            $count = DB::table($table)->count();
            if ($count > 0) {
                DB::table($table)->orderBy(DB::raw('1'))->chunk(500, function ($rows) use (&$dump, $table, $pdo) {
                    foreach ($rows as $row) {
                        $array = (array) $row;
                        $keys = array_keys($array);
                        $escapedKeys = array_map(fn ($k) => "`{$k}`", $keys);
                        $cols = implode(', ', $escapedKeys);

                        $values = [];
                        foreach ($array as $val) {
                            if ($val === null) {
                                $values[] = 'NULL';
                            } elseif (is_int($val) || is_float($val)) {
                                $values[] = $val;
                            } elseif (is_bool($val)) {
                                $values[] = $val ? 1 : 0;
                            } else {
                                $values[] = $pdo->quote((string) $val);
                            }
                        }

                        $vals = implode(', ', $values);
                        $dump .= "INSERT INTO `{$table}` ({$cols}) VALUES ({$vals});\n";
                    }
                });
            }
            $dump .= "\n";
        }

        $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";

        return $dump;
    }

    /**
     * List all database backups.
     */
    public function listBackups(): Collection
    {
        return DatabaseBackup::with('creator:id,name,username')
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get absolute local path for a backup record.
     */
    public function getBackupFullPath(DatabaseBackup $backup): string
    {
        // Sanitize filename to prevent directory traversal
        $safeFilename = basename($backup->filename);
        $fullPath = $this->backupDirectory . DIRECTORY_SEPARATOR . $safeFilename;

        if (! File::exists($fullPath)) {
            throw new \RuntimeException("Backup file does not exist on disk.");
        }

        return $fullPath;
    }

    /**
     * Delete old backup file and record safely.
     */
    public function deleteBackup(DatabaseBackup $backup, ?User $user = null): bool
    {
        $safeFilename = basename($backup->filename);
        $fullPath = $this->backupDirectory . DIRECTORY_SEPARATOR . $safeFilename;

        if (File::exists($fullPath)) {
            File::delete($fullPath);
        }

        $backup->delete();

        if ($user) {
            AuditLog::create([
                'audit_uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'module' => 'Database',
                'event_type' => 'Database Backup Deleted',
                'status' => 'success',
                'reason_notes' => "Database backup deleted: {$safeFilename}",
            ]);
        }

        return true;
    }

    /**
     * Clean old manual backups keeping latest $keepCount.
     */
    public function cleanOldBackups(int $keepCount = 10): int
    {
        $backups = DatabaseBackup::where('type', 'manual')
            ->orderBy('id', 'desc')
            ->get();

        if ($backups->count() <= $keepCount) {
            return 0;
        }

        $toDelete = $backups->slice($keepCount);
        $count = 0;

        foreach ($toDelete as $b) {
            $this->deleteBackup($b);
            $count++;
        }

        return $count;
    }
}
