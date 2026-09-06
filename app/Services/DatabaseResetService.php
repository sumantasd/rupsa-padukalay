<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DatabaseResetLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DatabaseResetService
{
    protected DatabaseBackupService $backupService;

    // Protected tables that MUST NEVER be wiped by data resets
    protected array $protectedTables = [
        'users',
        'user_store',
        'roles',
        'permissions',
        'model_has_roles',
        'model_has_permissions',
        'role_has_permissions',
        'stores',
        'warehouses',
        'stock_locations',
        'system_settings',
        'company_profile_settings',
        'tax_settings',
        'tax_rates',
        'hsn_codes',
        'size_charts',
        'size_chart_columns',
        'size_chart_rows',
        'size_chart_values',
        'migrations',
        'personal_access_tokens',
        'failed_jobs',
        'jobs',
        'cache',
        'cache_locks',
        'audit_logs',
        'database_backups',
        'database_reset_logs',
    ];

    // Category mapping to exact database tables
    protected array $categoryTableMap = [
        'sales' => [
            'invoice_items',
            'invoices',
            'payment_refunds',
            'payments',
            'day_closings',
            'pos_cash_movements',
            'pos_sessions',
        ],
        'sales_returns' => [
            'return_items',
            'returns',
            'exchange_items',
            'exchanges',
        ],
        'purchases' => [
            'goods_receive_items',
            'goods_receives',
            'purchase_bill_items',
            'purchase_bills',
            'purchase_return_items',
            'purchase_returns',
            'supplier_payments',
            'purchase_order_items',
            'purchase_orders',
        ],
        'inventory' => [
            'stock_transfer_items',
            'stock_transfers',
            'stock_adjustment_items',
            'stock_adjustments',
            'stock_damage_items',
            'stock_damages',
            'stock_movements',
            'inventory_stocks',
            'low_stock_notifications',
            'pos_sync_conflicts',
        ],
        'expenses' => [
            'expenses',
        ],
        'customer_loyalty' => [
            'customer_point_transactions',
            'customer_points',
            'store_credit_transactions',
            'store_credit_accounts',
            'coupon_redemptions',
            'coupons',
            'promotion_outlets',
            'promotion_rules',
            'promotions',
        ],
        'customers' => [
            'customers',
        ],
        'suppliers' => [
            'suppliers',
        ],
        'products' => [
            'product_images',
            'product_variant_sizes',
            'product_variants',
            'products',
        ],
        'master_attributes' => [
            'categories',
            'brands',
            'colors',
            'sizes',
        ],
    ];

    public function __construct(DatabaseBackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    /**
     * List available reset categories and presets.
     */
    public function getCategoriesAndPresets(): array
    {
        return [
            'presets' => [
                'demo_data_reset' => [
                    'title' => 'Demo Data Reset',
                    'description' => 'Clears all operational transactions (sales, purchases, inventory stocks, movements, returns, expenses) while preserving products, categories, brands, customers, suppliers, and system configuration.',
                    'categories' => ['sales', 'sales_returns', 'purchases', 'inventory', 'expenses', 'customer_loyalty'],
                ],
                'business_data_reset' => [
                    'title' => 'Full Business Data Reset',
                    'description' => 'Clears all operational transactions AND product/inventory catalog data, while strictly preserving admin/staff accounts, retail stores, roles, permissions, tax/hsn codes, and audit logs.',
                    'categories' => ['sales', 'sales_returns', 'purchases', 'inventory', 'expenses', 'customer_loyalty', 'customers', 'suppliers', 'products'],
                ],
            ],
            'categories' => [
                ['id' => 'sales', 'name' => 'Sales & POS Transactions', 'description' => 'Invoices, Invoice Items, Payments, POS Sessions, Cash Drawer Movements, Day Closings'],
                ['id' => 'sales_returns', 'name' => 'Sales Returns & Exchanges', 'description' => 'Sales Returns, Return Items, Product Exchanges, Exchange Items'],
                ['id' => 'purchases', 'name' => 'Procurement & Purchases', 'description' => 'Purchase Orders, Goods Receives, Purchase Bills, Supplier Payments, Purchase Returns'],
                ['id' => 'inventory', 'name' => 'Stock & Inventory Data', 'description' => 'Stock Balances, Movements Ledger, Transfers, Adjustments, Damage Logs, Low Stock Alerts'],
                ['id' => 'expenses', 'name' => 'Store Expenses', 'description' => 'Recorded store operating expense vouchers'],
                ['id' => 'customer_loyalty', 'name' => 'Loyalty & Promotions', 'description' => 'Reward Points, Store Credit balances, Coupons, Active Promotions'],
                ['id' => 'customers', 'name' => 'Customer Database', 'description' => 'Registered customer records (unless protected)'],
                ['id' => 'suppliers', 'name' => 'Supplier Database', 'description' => 'Vendor and supplier directory'],
                ['id' => 'products', 'name' => 'Products & Footwear SKUs', 'description' => 'Product Articles, Color Variants, Size SKUs, Product Images'],
                ['id' => 'master_attributes', 'name' => 'Footwear Masters', 'description' => 'Footwear Categories, Brands, Colors, Sizes'],
            ],
            'protected_tables' => $this->protectedTables,
        ];
    }

    /**
     * Execute Database Reset safely.
     */
    public function executeReset(
        User $user,
        string $resetType,
        array $selectedCategories,
        string $confirmationText,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): DatabaseResetLog {
        // Step 1: Validate confirmation phrase
        if (trim($confirmationText) !== 'CLEAR DATABASE') {
            throw new \InvalidArgumentException("Invalid confirmation phrase. You must type CLEAR DATABASE exactly.", 422);
        }

        if (empty($selectedCategories)) {
            throw new \InvalidArgumentException("No reset categories were selected.", 422);
        }

        // Step 2: Automatic Pre-Reset Database Backup
        $backup = $this->backupService->createBackup($user, 'auto_pre_reset');
        $backupFullPath = $this->backupService->getBackupFullPath($backup);

        // Step 3: Verify Backup File exists and is non-empty
        if (! File::exists($backupFullPath) || File::size($backupFullPath) === 0) {
            throw new \RuntimeException("Pre-reset automatic backup failed or generated an empty file. Reset operation aborted for database safety.");
        }

        // Step 4: Resolve target tables to clear
        $targetTables = [];
        foreach ($selectedCategories as $cat) {
            if (isset($this->categoryTableMap[$cat])) {
                foreach ($this->categoryTableMap[$cat] as $tbl) {
                    if (! in_array($tbl, $this->protectedTables, true) && ! in_array($tbl, $targetTables, true)) {
                        $targetTables[] = $tbl;
                    }
                }
            }
        }

        $clearedTables = [];
        $driver = DB::connection()->getDriverName();

        try {
            DB::transaction(function () use ($targetTables, $driver, &$clearedTables) {
                // Disable foreign key checks
                if ($driver === 'mysql') {
                    DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
                } elseif ($driver === 'sqlite') {
                    DB::statement('PRAGMA foreign_keys = OFF;');
                }

                foreach ($targetTables as $table) {
                    if (DB::getSchemaBuilder()->hasTable($table)) {
                        if ($driver === 'mysql') {
                            DB::table($table)->truncate();
                        } else {
                            DB::table($table)->delete();
                        }
                        $clearedTables[] = $table;
                    }
                }

                // Re-enable foreign key checks
                if ($driver === 'mysql') {
                    DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
                } elseif ($driver === 'sqlite') {
                    DB::statement('PRAGMA foreign_keys = ON;');
                }
            });

            // Create Reset Audit Log
            $resetLog = DatabaseResetLog::create([
                'user_id' => $user->id,
                'reset_type' => $resetType,
                'selected_categories' => $selectedCategories,
                'backup_filename' => $backup->filename,
                'backup_file_size' => $backup->file_size,
                'cleared_tables' => $clearedTables,
                'preserved_tables' => $this->protectedTables,
                'status' => 'success',
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            // Immutable System Audit Trail entry
            AuditLog::create([
                'audit_uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'module' => 'Database',
                'event_type' => 'Database Reset Executed',
                'auditable_type' => DatabaseResetLog::class,
                'auditable_id' => $resetLog->id,
                'status' => 'success',
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'reason_notes' => "Database reset executed ({$resetType}). Cleared " . count($clearedTables) . " tables. Backup: {$backup->filename}",
            ]);

            return $resetLog;
        } catch (\Throwable $e) {
            $failedLog = DatabaseResetLog::create([
                'user_id' => $user->id,
                'reset_type' => $resetType,
                'selected_categories' => $selectedCategories,
                'backup_filename' => $backup->filename,
                'backup_file_size' => $backup->file_size,
                'cleared_tables' => $clearedTables,
                'preserved_tables' => $this->protectedTables,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            AuditLog::create([
                'audit_uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'module' => 'Database',
                'event_type' => 'Database Reset Failed',
                'status' => 'failed',
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'reason_notes' => "Database reset failed: {$e->getMessage()}",
            ]);

            throw $e;
        }
    }
}
