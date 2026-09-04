<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetBusinessData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:reset-business-data {--force : Force the reset without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clears all test sales, purchases, inventory movements, payments and logs while preserving master catalog, RBAC, users, stores and settings.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dbName = DB::getDatabaseName();
        $this->info("Current Database: {$dbName}");

        if (! $this->option('force') && ! $this->confirm('WARNING: This will clear ALL test transaction data from the database. Master records (Stores, Users, Products, Categories, RBAC) will be preserved. Proceed?')) {
            $this->warn('Operation cancelled.');
            return 1;
        }

        $this->info('Starting controlled business data cleanup...');

        $transactionalTables = [
            'invoice_items',
            'invoice_payments',
            'customer_payments',
            'customer_refunds',
            'return_items',
            'returns',
            'pos_sync_conflicts',
            'promotion_usages',
            'invoices',
            'pos_register_cash_movements',
            'day_closings',
            'pos_sessions',
            'expenses',
            'purchase_return_items',
            'purchase_returns',
            'supplier_payments',
            'purchase_bill_items',
            'purchase_bills',
            'goods_receive_items',
            'goods_receives',
            'purchase_order_items',
            'purchase_orders',
            'stock_movements',
            'stock_transfer_items',
            'stock_transfers',
            'stock_adjustment_items',
            'stock_adjustments',
            'stock_damage_items',
            'stock_damage_transactions',
            'low_stock_notifications',
            'loyalty_transactions',
            'loyalty_accounts',
            'store_credit_transactions',
            'store_credit_accounts',
            'audit_logs',
            'website_leads',
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        try {
            // 1. Truncate Transactional Tables
            foreach ($transactionalTables as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                    $this->line("  [CLEARED] Table '{$table}' truncated.");
                }
            }

            // 2. Reset Inventory Stock Quantities (preserving stock rows)
            if (Schema::hasTable('inventory_stocks')) {
                DB::table('inventory_stocks')->update(['stock_quantity' => 0]);
                $this->line("  [RESET] 'inventory_stocks' stock_quantity set to 0.");
            }

            // 3. Reset Customer Transactional Totals (preserving master customer records)
            if (Schema::hasTable('customers')) {
                DB::table('customers')->update([
                    'reward_points' => 0,
                    'total_purchases_count' => 0,
                    'total_spent_amount' => 0.00,
                ]);
                $this->line("  [RESET] 'customers' reward_points, purchases count and spent amount set to 0.");
            }

            // 4. Reset Supplier Balances (preserving master supplier records)
            if (Schema::hasTable('suppliers')) {
                DB::table('suppliers')->update([
                    'current_balance' => 0.00,
                ]);
                $this->line("  [RESET] 'suppliers' current_balance set to 0.00.");
            }

            $this->info('Database cleanup completed successfully!');
        } catch (\Throwable $e) {
            $this->error('Cleanup failed: ' . $e->getMessage());
            return 1;
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
        }

        return 0;
    }
}
