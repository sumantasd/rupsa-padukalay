<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProductionAdminUserSeeder extends Seeder
{
    /**
     * Seed production Admin account for RUPSA PADUKALAYA.
     */
    public function run(): void
    {
        // 1. Create Core Permissions across modules
        $permissionsList = [
            ['name' => 'users.manage', 'module_group' => 'RBAC', 'display_name' => 'Manage Users & Security'],
            ['name' => 'users.view', 'module_group' => 'RBAC', 'display_name' => 'View Users'],
            ['name' => 'users.create', 'module_group' => 'RBAC', 'display_name' => 'Create Users'],
            ['name' => 'users.edit', 'module_group' => 'RBAC', 'display_name' => 'Edit Users'],
            ['name' => 'users.delete', 'module_group' => 'RBAC', 'display_name' => 'Delete Users'],
            ['name' => 'roles.manage', 'module_group' => 'RBAC', 'display_name' => 'Manage Roles & Permissions'],
            ['name' => 'stores.manage', 'module_group' => 'Stores', 'display_name' => 'Manage Retail Stores'],
            ['name' => 'stores.view', 'module_group' => 'Stores', 'display_name' => 'View Stores'],
            ['name' => 'products.view', 'module_group' => 'Products', 'display_name' => 'View Products & SKUs'],
            ['name' => 'products.create', 'module_group' => 'Products', 'display_name' => 'Create Products'],
            ['name' => 'products.edit', 'module_group' => 'Products', 'display_name' => 'Edit Products'],
            ['name' => 'products.delete', 'module_group' => 'Products', 'display_name' => 'Delete Products'],
            ['name' => 'size_charts.view', 'module_group' => 'Products', 'display_name' => 'View Size Charts'],
            ['name' => 'size_charts.create', 'module_group' => 'Products', 'display_name' => 'Create Size Charts'],
            ['name' => 'size_charts.edit', 'module_group' => 'Products', 'display_name' => 'Edit Size Charts'],
            ['name' => 'size_charts.delete', 'module_group' => 'Products', 'display_name' => 'Delete Size Charts'],
            ['name' => 'size_charts.configure', 'module_group' => 'Products', 'display_name' => 'Configure Size Charts'],
            ['name' => 'inventory.view', 'module_group' => 'Inventory', 'display_name' => 'View Inventory Balances'],
            ['name' => 'inventory.adjust', 'module_group' => 'Inventory', 'display_name' => 'Stock Adjustments'],
            ['name' => 'inventory.transfer', 'module_group' => 'Inventory', 'display_name' => 'Inter-Store Transfers'],
            ['name' => 'pos.billing', 'module_group' => 'POS', 'display_name' => 'POS Billing Terminal'],
            ['name' => 'pos.sessions', 'module_group' => 'POS', 'display_name' => 'POS Registers & Sessions'],
            ['name' => 'pos.returns', 'module_group' => 'POS', 'display_name' => 'Sales Returns & Refunds'],
            ['name' => 'pos.exchanges', 'module_group' => 'POS', 'display_name' => 'Item Exchanges'],
            ['name' => 'procurement.view', 'module_group' => 'Procurement', 'display_name' => 'View Purchase Orders'],
            ['name' => 'procurement.create', 'module_group' => 'Procurement', 'display_name' => 'Create Purchase Orders'],
            ['name' => 'procurement.receive', 'module_group' => 'Procurement', 'display_name' => 'Receive Shipments'],
            ['name' => 'customers.view', 'module_group' => 'Customers', 'display_name' => 'View Customers'],
            ['name' => 'customers.manage', 'module_group' => 'Customers', 'display_name' => 'Manage Customers & Loyalty'],
            ['name' => 'suppliers.view', 'module_group' => 'Suppliers', 'display_name' => 'View Suppliers'],
            ['name' => 'suppliers.manage', 'module_group' => 'Suppliers', 'display_name' => 'Manage Suppliers'],
            ['name' => 'expenses.view', 'module_group' => 'Expenses', 'display_name' => 'View Store Expenses'],
            ['name' => 'expenses.create', 'module_group' => 'Expenses', 'display_name' => 'Record Expenses'],
            ['name' => 'sales.delete', 'module_group' => 'POS', 'display_name' => 'Delete & Reverse Sales Invoices'],
            ['name' => 'sales_returns.delete', 'module_group' => 'POS', 'display_name' => 'Delete & Reverse Sales Returns'],
            ['name' => 'exchanges.delete', 'module_group' => 'POS', 'display_name' => 'Delete & Reverse Exchanges'],
            ['name' => 'purchases.delete', 'module_group' => 'Procurement', 'display_name' => 'Delete & Reverse Purchase Bills'],
            ['name' => 'reports.view', 'module_group' => 'Reports', 'display_name' => 'Executive & Financial Analytics'],
            ['name' => 'audit.view', 'module_group' => 'Audit', 'display_name' => 'View Audit Logs'],
            ['name' => 'system.settings', 'module_group' => 'System', 'display_name' => 'System Settings'],
        ];

        $permissionIds = [];
        foreach ($permissionsList as $pData) {
            $p = Permission::firstOrCreate(
                ['name' => $pData['name']],
                [
                    'guard_name' => 'web',
                    'module_group' => $pData['module_group'],
                    'display_name' => $pData['display_name'],
                ]
            );
            $permissionIds[] = $p->id;
        }

        // 2. Create Core Security Roles
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'Super Admin'],
            [
                'guard_name' => 'web',
                'description' => 'Universal System Administrator with unrestricted cross-store & ERP access.',
            ]
        );

        // Additional standard roles
        $storeManagerRole = Role::firstOrCreate(
            ['name' => 'Store Manager'],
            [
                'guard_name' => 'web',
                'description' => 'Store-scoped manager for inventory, POs, expenses & store performance analytics.',
            ]
        );

        $posCashierRole = Role::firstOrCreate(
            ['name' => 'POS Cashier'],
            [
                'guard_name' => 'web',
                'description' => 'POS billing operator for checkout, receipts, split payments, returns & drawer ops.',
            ]
        );

        $accountantRole = Role::firstOrCreate(
            ['name' => 'Accountant'],
            [
                'guard_name' => 'web',
                'description' => 'Financial analyst for sales reports, tax ledger, day closings & profit margin audit.',
            ]
        );

        // Attach permissions to roles
        $superAdminRole->permissions()->sync($permissionIds);

        $managerPermNames = [
            'products.view', 'products.create', 'products.edit', 'inventory.view', 'inventory.adjust',
            'inventory.transfer', 'procurement.view', 'procurement.create', 'procurement.receive',
            'customers.view', 'suppliers.view', 'expenses.view', 'expenses.create', 'pos.billing',
            'pos.sessions', 'pos.returns', 'pos.exchanges', 'reports.view', 'stores.view'
        ];
        $managerPermIds = Permission::whereIn('name', $managerPermNames)->pluck('id')->toArray();
        $storeManagerRole->permissions()->sync($managerPermIds);

        $cashierPermNames = ['pos.billing', 'pos.sessions', 'pos.returns', 'pos.exchanges', 'customers.view', 'products.view'];
        $cashierPermIds = Permission::whereIn('name', $cashierPermNames)->pluck('id')->toArray();
        $posCashierRole->permissions()->sync($cashierPermIds);

        $accountantPermNames = ['reports.view', 'expenses.view', 'pos.sessions', 'customers.view', 'suppliers.view', 'products.view', 'stores.view'];
        $accountantPermIds = Permission::whereIn('name', $accountantPermNames)->pluck('id')->toArray();
        $accountantRole->permissions()->sync($accountantPermIds);

        // 3. Create Retail Stores (Primary Production Outlet)
        $mainStore = Store::withTrashed()->firstOrCreate(
            ['code' => 'STR-001'],
            [
                'name' => 'RUPSA PADUKALAYA - Main Outlet (College Street)',
                'phone' => '+91 98765 43210',
                'email' => 'store01@rupsapadukalaya.in',
                'address' => '123 Footwear Market Road, College Street Hub, Kolkata 700001',
                'city' => 'Kolkata',
                'pincode' => '700001',
                'is_active' => true,
            ]
        );
        if ($mainStore->trashed()) {
            $mainStore->restore();
        }
        $mainStore->update(['is_active' => true]);

        // Clean up obsolete test/duplicate store STR-002 safely via soft-delete
        $secondStore = Store::withTrashed()->where('code', 'STR-002')->first();
        if ($secondStore) {
            $secondStore->update(['is_active' => false]);
            if (! $secondStore->trashed()) {
                $secondStore->delete();
            }
        }

        // 4. Create or Update Production Super Admin User
        $user = User::firstOrNew(['email' => 'admin@rupsapadukalaya.in']);

        $user->fill([
            'name' => 'Super Admin',
            'username' => 'rupsa_admin',
            'password' => Hash::make('Rupsa@2026@Admin'),
            'is_active' => true,
            'is_protected' => true,
            'phone' => '+91 98765 43210',
        ]);

        $user->save();

        // Attach Super Admin Role (using pivot model_type = User::class)
        $user->roles()->syncWithPivotValues([$superAdminRole->id], ['model_type' => User::class]);

        // Attach Active Main Store
        $user->stores()->sync([
            $mainStore->id => ['is_default' => true],
        ]);
    }
}
