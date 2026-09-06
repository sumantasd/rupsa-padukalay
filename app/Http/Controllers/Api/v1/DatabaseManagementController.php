<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\DatabaseBackup;
use App\Models\DatabaseResetLog;
use App\Services\DatabaseBackupService;
use App\Services\DatabaseResetService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseManagementController extends Controller
{
    use ApiResponse;

    protected DatabaseBackupService $backupService;
    protected DatabaseResetService $resetService;

    public function __construct(DatabaseBackupService $backupService, DatabaseResetService $resetService)
    {
        $this->backupService = $backupService;
        $this->resetService = $resetService;
    }

    /**
     * List all database backups.
     */
    public function listBackups(): JsonResponse
    {
        $backups = $this->backupService->listBackups();

        return $this->successResponse(
            $backups,
            'Database backups retrieved successfully.'
        );
    }

    /**
     * Create a manual database backup.
     */
    public function createBackup(Request $request): JsonResponse
    {
        $backup = $this->backupService->createBackup($request->user(), 'manual');

        return $this->successResponse(
            $backup->load('creator:id,name,username'),
            'Database backup created successfully.',
            201
        );
    }

    /**
     * Securely download a database backup SQL file.
     */
    public function downloadBackup(int $id, Request $request)
    {
        $backup = DatabaseBackup::find($id);
        if (! $backup) {
            return $this->errorResponse('Backup record not found.', 404);
        }

        try {
            $fullPath = $this->backupService->getBackupFullPath($backup);
            $safeFilename = basename($backup->filename);

            return response()->download($fullPath, $safeFilename, [
                'Content-Type' => 'application/x-sql',
                'Cache-Control' => 'no-cache, private',
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Delete a database backup.
     */
    public function deleteBackup(int $id, Request $request): JsonResponse
    {
        $backup = DatabaseBackup::find($id);
        if (! $backup) {
            return $this->errorResponse('Backup record not found.', 404);
        }

        $this->backupService->deleteBackup($backup, $request->user());

        return $this->successResponse(
            null,
            'Database backup deleted successfully.'
        );
    }

    /**
     * Get reset categories, presets, and protected tables information.
     */
    public function getResetCategories(): JsonResponse
    {
        $info = $this->resetService->getCategoriesAndPresets();

        return $this->successResponse(
            $info,
            'Database reset options retrieved successfully.'
        );
    }

    /**
     * Execute Database Reset safely.
     */
    public function executeReset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reset_type' => ['required', 'string', 'in:demo_data_reset,business_data_reset,custom_reset'],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['string'],
            'confirmation_text' => ['required', 'string'],
        ]);

        try {
            $resetLog = $this->resetService->executeReset(
                $request->user(),
                $validated['reset_type'],
                $validated['categories'],
                $validated['confirmation_text'],
                $request->ip(),
                $request->userAgent()
            );

            return $this->successResponse(
                $resetLog,
                'Database reset executed successfully. Pre-reset backup stored.'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->errorResponse("Database reset failed: " . $e->getMessage(), 500);
        }
    }

    /**
     * Get database reset audit history.
     */
    public function getResetAudits(): JsonResponse
    {
        $logs = DatabaseResetLog::with('user:id,name,username')
            ->orderBy('id', 'desc')
            ->paginate(20);

        return $this->successResponse(
            $logs,
            'Database reset audit history retrieved successfully.'
        );
    }
}
