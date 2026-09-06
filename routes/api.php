<?php

use App\Http\Controllers\Api\v1\AuditLogController;
use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\BrandController;
use App\Http\Controllers\Api\v1\CategoryController;
use App\Http\Controllers\Api\v1\ColorController;
use App\Http\Controllers\Api\v1\CompanyProfileController;
use App\Http\Controllers\Api\v1\CustomerController;
use App\Http\Controllers\Api\v1\CustomerAnalyticsController;
use App\Http\Controllers\Api\v1\DatabaseManagementController;
use App\Http\Controllers\Api\v1\HsnCodeController;
use App\Http\Controllers\Api\v1\InventoryStockController;
use App\Http\Controllers\Api\v1\InventorySettingController;
use App\Http\Controllers\Api\v1\LowStockNotificationController;
use App\Http\Controllers\Api\v1\ProductController;
use App\Http\Controllers\Api\v1\ProductImageController;
use App\Http\Controllers\Api\v1\PromotionController;
use App\Http\Controllers\Api\v1\PosSaleController;
use App\Http\Controllers\Api\v1\PosRegisterController;
use App\Http\Controllers\Api\v1\PosSessionController;
use App\Http\Controllers\Api\v1\ExchangeController;
use App\Http\Controllers\Api\v1\ExecutiveDashboardController;
use App\Http\Controllers\Api\v1\ExpenseController;
use App\Http\Controllers\Api\v1\FinancialReportController;
use App\Http\Controllers\Api\v1\LoyaltyController;
use App\Http\Controllers\Api\v1\OfflineSyncController;
use App\Http\Controllers\Api\v1\PosSyncConflictController;
use App\Http\Controllers\Api\v1\PrinterSettingController;
use App\Http\Controllers\Api\v1\PurchaseOrderController;
use App\Http\Controllers\Api\v1\GoodsReceiveController;
use App\Http\Controllers\Api\v1\PurchaseBillController;
use App\Http\Controllers\Api\v1\PurchaseReturnController;
use App\Http\Controllers\Api\v1\ReportController;
use App\Http\Controllers\Api\v1\SalesReturnController;
use App\Http\Controllers\Api\v1\SizeChartController;
use App\Http\Controllers\Api\v1\SizeController;
use App\Http\Controllers\Api\v1\StockAdjustmentController;
use App\Http\Controllers\Api\v1\StockDamageController;
use App\Http\Controllers\Api\v1\StockLocationController;
use App\Http\Controllers\Api\v1\StockMovementController;
use App\Http\Controllers\Api\v1\StockTransferController;
use App\Http\Controllers\Api\v1\StoreController;
use App\Http\Controllers\Api\v1\StoreCreditController;
use App\Http\Controllers\Api\v1\StoreTransferAnalyticsController;
use App\Http\Controllers\Api\v1\SupplierController;
use App\Http\Controllers\Api\v1\SupplierAnalyticsController;
use App\Http\Controllers\Api\v1\TaxRateController;
use App\Http\Controllers\Api\v1\TaxSettingController;
use App\Http\Controllers\Api\v1\PaymentCollectionController;
use App\Http\Controllers\Api\v1\RecycleBinController;
use App\Http\Controllers\Api\v1\RefundController;
use App\Http\Controllers\Api\v1\CashDrawerController;
use App\Http\Controllers\Api\v1\DayClosingController;
use App\Http\Controllers\Api\v1\ModuleSettingController;
use App\Http\Controllers\Api\v1\ReportPdfController;
use App\Http\Controllers\Api\v1\UserController;
use App\Http\Controllers\Api\v1\RoleController;
use App\Http\Controllers\Api\v1\StoreAccessController;
use App\Http\Controllers\Api\v1\InvoiceSettingController;
use App\Http\Controllers\Api\v1\PaymentMethodSettingController;
use App\Http\Controllers\Api\v1\PosSettingController;
use App\Http\Controllers\Api\v1\NumberSeriesSettingController;
use App\Http\Controllers\Api\v1\GeneralSettingController;
use App\Http\Controllers\Api\v1\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Auth Routes
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::put('profile', [AuthController::class, 'updateProfile']);
            Route::put('change-password', [AuthController::class, 'changePassword']);
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    // Public Website Dynamic Settings & Catalog API (Unauthenticated)
    Route::get('public/website-settings', [\App\Http\Controllers\Api\v1\FrontWebsiteSettingController::class, 'getPublicSettings']);
    Route::get('public/company-profile', [CompanyProfileController::class, 'show']);
    Route::get('public/categories', [\App\Http\Controllers\Api\v1\CategoryController::class, 'index']);
    Route::get('public/brands', [\App\Http\Controllers\Api\v1\BrandController::class, 'index']);
    Route::get('public/products', [\App\Http\Controllers\Api\v1\ProductController::class, 'index']);

    // Protected Master Data Routes (Sanctum + RBAC)
    Route::middleware('auth:sanctum')->group(function () {
        // Inventory Read-Only API
        Route::middleware('permission:products.view')->get('inventory/overview', [InventoryStockController::class, 'overview']);
        Route::middleware('permission:products.view|procurement.view')->get('products/size-matrix-search', [ProductController::class, 'sizeMatrixSearch']);
        Route::middleware('permission:products.view')->get('inventory', [InventoryStockController::class, 'index']);
        Route::middleware('permission:products.view')->get('inventory/low-stock', [InventoryStockController::class, 'lowStock']);
        Route::middleware('permission:products.view')->get('inventory/reconciliation/{sku}', [InventoryStockController::class, 'reconciliation']);
        Route::middleware('permission:products.view')->get('inventory/stocks', [InventoryStockController::class, 'index']);
        Route::middleware('permission:products.view')->get('inventory/stocks/{id}', [InventoryStockController::class, 'show']);

        // Low Stock Notifications API
        Route::middleware('permission:products.view')->get('notifications/low-stock', [LowStockNotificationController::class, 'index']);
        Route::middleware('permission:products.view')->get('notifications/unread-count', [LowStockNotificationController::class, 'unreadCount']);
        Route::middleware('permission:products.view')->post('notifications/{id}/read', [LowStockNotificationController::class, 'markAsRead']);
        Route::middleware('permission:products.view')->post('notifications/read-all', [LowStockNotificationController::class, 'markAllAsRead']);

        // Inventory / Stock Settings API
        Route::middleware('permission:system.settings')->get('settings/inventory', [InventorySettingController::class, 'getSettings']);
        Route::middleware('permission:system.settings')->post('settings/inventory', [InventorySettingController::class, 'updateSettings']);

        // Stock Movement / Ledger API
        Route::middleware('permission:products.view')->get('inventory/movements', [StockMovementController::class, 'index']);
        Route::middleware('permission:products.view')->get('inventory/movements/{id}', [StockMovementController::class, 'show']);

        // Stock Adjustment API
        Route::middleware('permission:products.view')->get('inventory/adjustments', [StockAdjustmentController::class, 'index']);
        Route::middleware('permission:products.view')->get('inventory/adjustments/{id}', [StockAdjustmentController::class, 'show']);
        Route::middleware('permission:products.edit')->post('inventory/adjustments', [StockAdjustmentController::class, 'store']);

        // Stock Out – Damage API
        Route::middleware('permission:products.view|inventory.damage.view')->get('inventory/damage', [StockDamageController::class, 'index']);
        Route::middleware('permission:products.view|inventory.damage.view')->get('inventory/damage/product-search', [StockDamageController::class, 'productSearch']);
        Route::middleware('permission:products.view|inventory.damage.view')->get('inventory/damage/{id}', [StockDamageController::class, 'show']);
        Route::middleware('permission:products.edit|inventory.damage.create')->post('inventory/damage', [StockDamageController::class, 'store']);

        // Stock Transfer & Multi-Location Analytics API
        Route::middleware('permission:products.view')->get('transfers/analytics/summary', [StoreTransferAnalyticsController::class, 'summary']);
        Route::middleware('permission:products.view')->get('transfers/analytics/matrix', [StoreTransferAnalyticsController::class, 'matrix']);
        Route::middleware('permission:products.view')->get('transfers/analytics/history', [StoreTransferAnalyticsController::class, 'history']);
        Route::middleware('permission:products.view')->get('inventory/turnover-analytics', [StoreTransferAnalyticsController::class, 'inventoryTurnover']);
        Route::middleware('permission:products.view')->get('inventory/cross-store-balance', [StoreTransferAnalyticsController::class, 'crossStoreBalance']);
        Route::middleware('permission:products.view')->get('inventory/transfers', [StockTransferController::class, 'index']);
        Route::middleware('permission:products.view')->get('inventory/transfers/{id}', [StockTransferController::class, 'show']);
        Route::middleware('permission:products.edit')->post('inventory/transfers', [StockTransferController::class, 'store']);

        // Customers API
        Route::middleware('permission:products.view')->get('customers', [CustomerController::class, 'index']);
        Route::middleware('permission:products.view')->get('customers/{id}', [CustomerController::class, 'show']);
        Route::middleware('permission:products.create')->post('customers', [CustomerController::class, 'store']);
        Route::middleware('permission:products.edit')->put('customers/{id}', [CustomerController::class, 'update']);
        Route::middleware('permission:products.edit')->delete('customers/{id}', [CustomerController::class, 'destroy']);
        Route::middleware('permission:products.view')->get('customers/{id}/loyalty', [LoyaltyController::class, 'getAccount']);
        Route::middleware('permission:products.view')->get('customers/{id}/loyalty/transactions', [LoyaltyController::class, 'getTransactions']);
        Route::middleware('permission:products.edit')->post('pos/sales/{id}/loyalty/redeem', [LoyaltyController::class, 'redeemPoints']);
        Route::middleware('permission:products.view')->get('customers/{id}/store-credit', [StoreCreditController::class, 'getAccount']);
        Route::middleware('permission:products.view')->get('customers/{id}/store-credit/transactions', [StoreCreditController::class, 'getTransactions']);
        Route::middleware('permission:products.edit')->post('customers/{id}/store-credit', [StoreCreditController::class, 'issueOrAdjust']);

        // Suppliers API
        Route::middleware('permission:products.view|procurement.view|suppliers.view')->get('suppliers', [SupplierController::class, 'index']);
        Route::middleware('permission:products.view|procurement.view|suppliers.view')->get('suppliers/performance-ranking', [SupplierAnalyticsController::class, 'performanceRanking']);
        Route::middleware('permission:products.view|procurement.view|suppliers.view')->get('suppliers/{id}', [SupplierController::class, 'show']);
        Route::middleware('permission:products.create|procurement.view|suppliers.view')->post('suppliers', [SupplierController::class, 'store']);
        Route::middleware('permission:products.edit|procurement.view|suppliers.view')->put('suppliers/{id}', [SupplierController::class, 'update']);
        Route::middleware('permission:products.edit|suppliers.view')->delete('suppliers/{id}', [SupplierController::class, 'destroy']);
        Route::middleware('permission:products.create|procurement.view|suppliers.view')->post('suppliers/{id}/payments', [SupplierController::class, 'storePayment']);
        Route::middleware('permission:products.view|procurement.view|suppliers.view')->get('suppliers/{id}/payments', [SupplierController::class, 'payments']);
        Route::middleware('permission:products.view|procurement.view|suppliers.view')->get('suppliers/{id}/ledger', [SupplierController::class, 'ledger']);


        // Purchase Orders & Goods Receiving API
        Route::middleware('permission:products.view|procurement.view')->get('purchases/orders', [PurchaseOrderController::class, 'index']);
        Route::middleware('permission:products.view|procurement.view')->get('purchases/orders/{id}', [PurchaseOrderController::class, 'show']);
        Route::middleware('permission:products.create|procurement.view')->post('purchases/orders', [PurchaseOrderController::class, 'store']);
        Route::middleware('permission:products.edit|procurement.view')->put('purchases/orders/{id}', [PurchaseOrderController::class, 'update']);
        Route::middleware('permission:products.edit|procurement.receive|procurement.view')->post('purchases/orders/{id}/receive', [PurchaseOrderController::class, 'receive']);
        Route::middleware('permission:products.delete|purchases.delete|procurement.create')->delete('purchases/orders/{id}', [PurchaseOrderController::class, 'destroy']);

        // Goods Receive Notes (GRN) API
        Route::middleware('permission:products.view|procurement.view|procurement.receive')->get('purchases/grn', [GoodsReceiveController::class, 'index']);
        Route::middleware('permission:products.view|procurement.view|procurement.receive')->get('purchases/grn/{id}', [GoodsReceiveController::class, 'show']);
        Route::middleware('permission:products.create|procurement.receive|procurement.view')->post('purchases/grn', [GoodsReceiveController::class, 'store']);

        // Purchase Bills & Vendor Payments API
        Route::middleware('permission:products.view|procurement.view')->get('purchases/bills', [PurchaseBillController::class, 'index']);
        Route::middleware('permission:products.view|procurement.view')->get('purchases/bills/{id}', [PurchaseBillController::class, 'show']);
        Route::middleware('permission:products.create|procurement.view')->post('purchases/bills', [PurchaseBillController::class, 'store']);
        Route::middleware('permission:products.create|procurement.view')->post('purchases/bills/{id}/pay', [PurchaseBillController::class, 'pay']);
        Route::middleware('permission:products.delete|purchases.delete|procurement.create')->delete('purchases/bills/{id}', [PurchaseBillController::class, 'destroy']);

        // Purchase Returns API
        Route::middleware('permission:products.view|procurement.view')->get('purchases/returns', [PurchaseReturnController::class, 'index']);
        Route::middleware('permission:products.view|procurement.view')->get('purchases/returns/{id}', [PurchaseReturnController::class, 'show']);
        Route::middleware('permission:products.edit|procurement.view')->post('purchases/orders/{id}/return', [PurchaseReturnController::class, 'store']);
        Route::middleware('permission:products.create|procurement.view')->post('purchases/returns', [PurchaseReturnController::class, 'store']);

        // POS Session Management API
        Route::middleware('permission:products.view|pos.sessions|pos.billing')->get('pos/sessions', [PosSessionController::class, 'index']);
        Route::middleware('permission:products.view|pos.sessions|pos.billing')->get('pos/sessions/current', [PosSessionController::class, 'current']);
        Route::middleware('permission:products.view|pos.sessions|pos.billing')->get('pos/sessions/{id}', [PosSessionController::class, 'show']);
        Route::middleware('permission:products.create|pos.sessions|pos.billing')->post('pos/sessions', [PosSessionController::class, 'open']);
        Route::middleware('permission:products.edit|pos.sessions|pos.billing')->post('pos/sessions/{id}/close', [PosSessionController::class, 'close']);

        // POS Billing, Split Payment, Invoice Receipt, Sales Return & Exchange API
        Route::middleware('permission:products.view|pos.billing|sales.view')->get('pos/sales', [PosSaleController::class, 'index']);
        Route::middleware('permission:products.view|pos.returns|sales_returns.view')->get('pos/sales/returns', [SalesReturnController::class, 'index']);
        Route::middleware('permission:products.view|pos.returns|sales_returns.view')->get('pos/sales/returns/{id}', [SalesReturnController::class, 'show']);
        Route::middleware('permission:products.view|pos.exchanges|exchanges.view')->get('pos/exchanges', [ExchangeController::class, 'index']);
        Route::middleware('permission:products.view|pos.exchanges|exchanges.view')->get('pos/sales/exchanges', [ExchangeController::class, 'index']);
        Route::middleware('permission:products.view|pos.exchanges|exchanges.view')->get('pos/exchanges/{id}', [ExchangeController::class, 'show']);
        Route::middleware('permission:products.view|pos.billing')->get('pos/sync-conflicts', [PosSyncConflictController::class, 'index']);
        Route::middleware('permission:products.view|pos.billing')->get('pos/sync-conflicts/{id}', [PosSyncConflictController::class, 'show']);
        Route::middleware('permission:products.edit|pos.billing')->post('pos/sync-conflicts/{id}/resolve', [PosSyncConflictController::class, 'resolve']);
        Route::middleware('permission:products.view|pos.billing|sales.view')->get('pos/sales/{id}', [PosSaleController::class, 'show']);
        Route::middleware('permission:products.view|pos.billing|sales.view')->get('pos/sales/{id}/invoice', [PosSaleController::class, 'getInvoice']);
        Route::middleware('permission:products.create|pos.billing')->post('pos/sales/sync', [OfflineSyncController::class, 'sync']);
        Route::middleware('permission:products.create|pos.billing')->post('pos/sales', [PosSaleController::class, 'store']);
        Route::middleware('permission:products.view|pos.billing')->get('pos/sales/{id}/payments', [PosSaleController::class, 'getPayments']);
        Route::middleware('permission:products.create|pos.billing')->post('pos/sales/{id}/payments', [PosSaleController::class, 'storePayments']);
        Route::middleware('permission:products.edit|pos.returns|pos.billing')->post('pos/sales/{id}/return', [SalesReturnController::class, 'store']);
        Route::middleware('permission:products.edit|pos.exchanges|pos.billing')->post('pos/sales/{id}/exchange', [ExchangeController::class, 'store']);
        Route::middleware('permission:sales.delete')->delete('pos/sales/{id}', [PosSaleController::class, 'destroy']);
        Route::middleware('permission:sales_returns.delete')->delete('pos/sales/returns/{id}', [SalesReturnController::class, 'destroy']);
        Route::middleware('permission:exchanges.delete')->delete('pos/exchanges/{id}', [ExchangeController::class, 'destroy']);

        // Expense Management API
        Route::middleware('permission:products.view|expenses.view')->get('expenses', [ExpenseController::class, 'index']);
        Route::middleware('permission:products.view|expenses.view')->get('expenses/reports', [ExpenseController::class, 'reports']);
        Route::middleware('permission:products.view|expenses.view')->get('expense-categories', [ExpenseController::class, 'categories']);
        Route::middleware('permission:products.create|expenses.create')->post('expense-categories', [ExpenseController::class, 'storeCategory']);
        Route::middleware('permission:products.edit|expenses.create')->put('expense-categories/{id}', [ExpenseController::class, 'updateCategory']);
        Route::middleware('permission:products.edit|expenses.create')->delete('expense-categories/{id}', [ExpenseController::class, 'destroyCategory']);
        Route::middleware('permission:products.view|expenses.view')->get('expenses/{id}', [ExpenseController::class, 'show']);
        Route::middleware('permission:products.create|expenses.create')->post('expenses', [ExpenseController::class, 'store']);
        Route::middleware('permission:products.edit|expenses.create')->put('expenses/{id}', [ExpenseController::class, 'update']);
        Route::middleware('permission:products.edit|expenses.create')->delete('expenses/{id}', [ExpenseController::class, 'destroy']);

        // Unified Payments, Refunds, Cash Drawer & Day Closing API
        Route::middleware('permission:products.view|pos.billing|payments.view')->get('payments/collections', [PaymentCollectionController::class, 'index']);
        Route::middleware('permission:products.create|pos.billing|payments.create')->post('payments/collections', [PaymentCollectionController::class, 'store']);

        Route::middleware('permission:products.view|pos.returns|payments.view|payments.refund')->get('payments/refunds', [RefundController::class, 'index']);
        Route::middleware('permission:products.create|pos.returns|payments.refund')->post('payments/refunds', [RefundController::class, 'store']);

        Route::middleware('permission:products.view|pos.sessions|cash_drawer.view')->get('payments/cash-drawer/status', [CashDrawerController::class, 'status']);
        Route::middleware('permission:products.view|pos.sessions|cash_drawer.view')->get('payments/cash-drawer/movements', [CashDrawerController::class, 'movements']);
        Route::middleware('permission:products.create|pos.sessions|cash_drawer.adjust')->post('payments/cash-drawer/movement', [CashDrawerController::class, 'recordMovement']);

        Route::middleware('permission:products.view|pos.sessions|day_closing.view')->get('payments/day-closing', [DayClosingController::class, 'index']);
        Route::middleware('permission:products.view|pos.sessions|day_closing.view')->get('payments/day-closing/summary', [DayClosingController::class, 'summary']);
        Route::middleware('permission:products.view|pos.sessions|day_closing.view')->get('payments/day-closing/{id}', [DayClosingController::class, 'show']);
        Route::middleware('permission:products.create|pos.sessions|day_closing.create')->post('payments/day-closing', [DayClosingController::class, 'store']);
        Route::middleware('permission:products.create|day_closing.reopen|roles.manage')->post('payments/day-closing/{id}/reopen', [DayClosingController::class, 'reopen']);

        // Executive Dashboard & KPI Summary API
        Route::middleware('permission:products.view|reports.view')->get('dashboard/executive-kpi', [ExecutiveDashboardController::class, 'executiveKpi']);
        Route::middleware('permission:products.view|reports.view')->get('dashboard/sales-trend', [ExecutiveDashboardController::class, 'salesTrend']);
        Route::middleware('permission:products.view|reports.view')->get('dashboard/store-performance', [ExecutiveDashboardController::class, 'storePerformance']);
        Route::middleware('permission:products.view|reports.view')->get('dashboard/product-performance', [ExecutiveDashboardController::class, 'productPerformance']);
        Route::middleware('permission:products.view|reports.view')->get('dashboard/inventory-kpi', [ExecutiveDashboardController::class, 'inventoryKpi']);
        Route::middleware('permission:products.view|reports.view')->get('dashboard/pos-register-kpi', [ExecutiveDashboardController::class, 'posRegisterKpi']);
        Route::middleware('permission:products.view|reports.view')->get('dashboard/customer-kpi', [ExecutiveDashboardController::class, 'customerKpi']);

        // Multi-Store Consolidated Financial & Tax Reporting API
        Route::middleware('permission:reports.view|products.view')->get('financial-reports/consolidated-sales', [FinancialReportController::class, 'consolidatedSales']);
        Route::middleware('permission:reports.view|products.view')->get('financial-reports/profit-loss', [FinancialReportController::class, 'profitLoss']);
        Route::middleware('permission:reports.view|products.view')->get('financial-reports/gst-liability', [FinancialReportController::class, 'gstLiability']);

        // Store-Level Audit Log & Financial Audit Trail API
        Route::middleware('permission:audit.view|products.view')->get('audit-logs', [AuditLogController::class, 'index']);
        Route::middleware('permission:audit.view|products.view')->get('audit-logs/financial-trail', [AuditLogController::class, 'financialTrail']);
        Route::middleware('permission:audit.view|products.view')->get('audit-logs/entity/{type}/{id}', [AuditLogController::class, 'entityHistory']);
        Route::middleware('permission:audit.view|products.view')->get('audit-logs/user/{userId}', [AuditLogController::class, 'userActivity']);
        Route::middleware('permission:audit.view|products.view')->get('audit-logs/store/{storeId}', [AuditLogController::class, 'storeHistory']);
        Route::middleware('permission:audit.view|products.view')->get('audit-logs/{id}', [AuditLogController::class, 'show']);

        // Admin Reports Foundation API
        Route::middleware('permission:products.view|reports.view')->get('reports/sales-summary', [ReportController::class, 'salesSummary']);
        Route::middleware('permission:products.view|reports.view')->get('reports/store-performance', [ReportController::class, 'storePerformance']);
        Route::middleware('permission:products.view|reports.view')->get('reports/stock-valuation', [ReportController::class, 'stockValuation']);
        Route::middleware('permission:products.view|reports.view')->get('reports/tax-summary', [ReportController::class, 'taxSummary']);
        Route::middleware('permission:products.view|reports.view')->get('reports/purchases-summary', [ReportController::class, 'purchaseSummary']);
        Route::middleware('permission:products.view|reports.view')->get('reports/customers-summary', [ReportController::class, 'customerSummary']);
        Route::middleware('permission:products.view|reports.view')->get('reports/payments-summary', [ReportController::class, 'paymentSummary']);
        Route::middleware('permission:products.view|reports.view')->get('reports/item-wise-sales', [ReportController::class, 'itemWiseSales']);
        Route::middleware('permission:products.view|reports.view')->get('reports/date-wise-sales', [ReportController::class, 'dateWiseSales']);
        Route::middleware('permission:products.view|reports.view')->get('reports/date-wise-payments', [ReportController::class, 'dateWisePayments']);
        Route::middleware('permission:products.view|reports.view')->get('reports/date-wise-profit-loss', [ReportController::class, 'dateWiseProfitLoss']);
        Route::middleware('permission:products.view|reports.view')->get('reports/pdf', [ReportPdfController::class, 'export']);

        // Database Management & Backup / Reset API
        Route::middleware('permission:database.manage|system.settings')->group(function () {
            Route::get('database/backups', [DatabaseManagementController::class, 'listBackups']);
            Route::post('database/backups', [DatabaseManagementController::class, 'createBackup']);
            Route::get('database/backups/{id}/download', [DatabaseManagementController::class, 'downloadBackup']);
            Route::delete('database/backups/{id}', [DatabaseManagementController::class, 'deleteBackup']);
            Route::get('database/reset/categories', [DatabaseManagementController::class, 'getResetCategories']);
            Route::post('database/reset', [DatabaseManagementController::class, 'executeReset']);
            Route::get('database/reset/audits', [DatabaseManagementController::class, 'getResetAudits']);
        });

        // Users & Access Control API
        Route::middleware('permission:users.view|users.manage')->get('users', [UserController::class, 'index']);
        Route::middleware('permission:users.create|users.manage')->post('users', [UserController::class, 'store']);
        Route::middleware('permission:users.view|users.manage')->get('users/{id}', [UserController::class, 'show']);
        Route::middleware('permission:users.edit|users.manage')->put('users/{id}', [UserController::class, 'update']);
        Route::middleware('permission:users.delete|users.manage')->delete('users/{id}', [UserController::class, 'destroy']);
        Route::middleware('permission:users.edit|users.manage')->patch('users/{id}/status', [UserController::class, 'toggleStatus']);
        Route::middleware('permission:users.edit|users.manage')->post('users/{id}/reset-password', [UserController::class, 'resetPassword']);

        // Roles & Permissions API
        Route::middleware('permission:roles.manage|users.manage')->get('roles', [RoleController::class, 'index']);
        Route::middleware('permission:roles.manage')->get('permissions', [RoleController::class, 'permissions']);
        Route::middleware('permission:roles.manage')->post('roles', [RoleController::class, 'store']);
        Route::middleware('permission:roles.manage')->get('roles/{id}', [RoleController::class, 'show']);
        Route::middleware('permission:roles.manage')->put('roles/{id}', [RoleController::class, 'update']);
        Route::middleware('permission:roles.manage')->delete('roles/{id}', [RoleController::class, 'destroy']);

        // Store Access API
        Route::middleware('permission:stores.manage|users.manage')->get('store-access', [StoreAccessController::class, 'index']);
        Route::middleware('permission:stores.manage|users.manage')->post('store-access/assign/{userId}', [StoreAccessController::class, 'assignStoreAccess']);

        // Stores API
        Route::middleware('permission:users.manage|stores.manage|stores.view|reports.view')->get('stores', [StoreController::class, 'index']);
        Route::middleware('permission:reports.view|stores.manage|stores.view|users.manage')->get('stores/performance', [StoreController::class, 'performanceOverview']);
        Route::middleware(['permission:reports.view|stores.manage|stores.view|users.manage', 'store.access'])->get('stores/{id}/performance', [StoreController::class, 'performance']);
        Route::middleware(['permission:users.manage', 'store.access'])->get('stores/{id}', [StoreController::class, 'show']);
        Route::middleware('permission:users.manage')->post('stores', [StoreController::class, 'store']);
        Route::middleware(['permission:users.manage', 'store.access'])->put('stores/{id}', [StoreController::class, 'update']);
        Route::middleware(['permission:users.manage', 'store.access'])->patch('stores/{id}/status', [StoreController::class, 'toggleStatus']);
        Route::middleware(['permission:users.manage', 'store.access'])->delete('stores/{id}', [StoreController::class, 'destroy']);
        Route::middleware(['permission:users.manage', 'store.access'])->post('stores/{id}/users', [StoreController::class, 'assignUsers']);
        Route::middleware(['permission:users.manage', 'store.access'])->post('stores/{id}/warehouses', [WarehouseController::class, 'assignToStore']);

        // Warehouses API
        Route::middleware('permission:users.manage')->get('warehouses', [WarehouseController::class, 'index']);
        Route::middleware('permission:users.manage')->get('warehouses/{id}', [WarehouseController::class, 'show']);
        Route::middleware('permission:users.manage')->post('warehouses', [WarehouseController::class, 'store']);
        Route::middleware('permission:users.manage')->put('warehouses/{id}', [WarehouseController::class, 'update']);
        Route::middleware('permission:users.manage')->patch('warehouses/{id}/status', [WarehouseController::class, 'toggleStatus']);

        // Stock Locations API
        Route::middleware('permission:users.manage')->get('stock-locations', [StockLocationController::class, 'index']);
        Route::middleware('permission:users.manage')->get('stock-locations/{id}', [StockLocationController::class, 'show']);
        Route::middleware('permission:users.manage')->post('stock-locations', [StockLocationController::class, 'store']);
        Route::middleware('permission:users.manage')->put('stock-locations/{id}', [StockLocationController::class, 'update']);
        Route::middleware('permission:users.manage')->patch('stock-locations/{id}/status', [StockLocationController::class, 'toggleStatus']);

        // Brands API
        Route::middleware('permission:products.view')->get('brands', [BrandController::class, 'index']);
        Route::middleware('permission:products.view')->get('brands/{id}', [BrandController::class, 'show']);
        Route::middleware('permission:products.create')->post('brands', [BrandController::class, 'store']);
        Route::middleware('permission:products.edit')->put('brands/{id}', [BrandController::class, 'update']);
        Route::middleware('permission:products.edit')->patch('brands/{id}/status', [BrandController::class, 'toggleStatus']);

        // Categories API
        Route::middleware('permission:products.view')->get('categories', [CategoryController::class, 'index']);
        Route::middleware('permission:products.view')->get('categories/{id}', [CategoryController::class, 'show']);
        Route::middleware('permission:products.create')->post('categories', [CategoryController::class, 'store']);
        Route::middleware('permission:products.edit')->put('categories/{id}', [CategoryController::class, 'update']);
        Route::middleware('permission:products.edit')->patch('categories/{id}/status', [CategoryController::class, 'toggleStatus']);

        // Sizes API
        Route::middleware('permission:products.view')->get('sizes', [SizeController::class, 'index']);
        Route::middleware('permission:products.view')->get('sizes/{id}', [SizeController::class, 'show']);
        Route::middleware('permission:products.create')->post('sizes', [SizeController::class, 'store']);
        Route::middleware('permission:products.edit')->put('sizes/{id}', [SizeController::class, 'update']);
        Route::middleware('permission:products.edit')->patch('sizes/{id}/status', [SizeController::class, 'toggleStatus']);

        // Size Charts API
        Route::get('size-charts', [SizeChartController::class, 'index']);
        Route::post('size-charts', [SizeChartController::class, 'store']);
        Route::get('size-charts/{id}', [SizeChartController::class, 'show']);
        Route::put('size-charts/{id}', [SizeChartController::class, 'update']);
        Route::delete('size-charts/{id}', [SizeChartController::class, 'destroy']);
        Route::post('size-charts/{id}/duplicate', [SizeChartController::class, 'duplicate']);
        Route::post('size-charts/{id}/toggle-status', [SizeChartController::class, 'toggleStatus']);
        Route::post('size-charts/{id}/set-default', [SizeChartController::class, 'setDefault']);
        Route::get('categories/{id}/size-chart', [SizeChartController::class, 'getCategoryDefault']);
        Route::post('categories/{id}/size-chart', [SizeChartController::class, 'assignCategoryDefault']);

        // Colours API
        Route::middleware('permission:products.view')->get('colors', [ColorController::class, 'index']);
        Route::middleware('permission:products.view')->get('colors/{id}', [ColorController::class, 'show']);
        Route::middleware('permission:products.create')->post('colors', [ColorController::class, 'store']);
        Route::middleware('permission:products.edit')->put('colors/{id}', [ColorController::class, 'update']);
        Route::middleware('permission:products.edit')->patch('colors/{id}/status', [ColorController::class, 'toggleStatus']);

        // Products & Variants API
        Route::middleware('permission:products.view')->get('products', [ProductController::class, 'index']);
        Route::middleware('permission:products.view')->get('products/{id}', [ProductController::class, 'show']);
        Route::middleware('permission:products.create')->post('products/bulk-create', [ProductController::class, 'bulkStore']);
        Route::middleware('permission:products.edit')->post('products/{id}/bulk-update', [ProductController::class, 'bulkUpdate']);
        Route::middleware('permission:products.create')->post('products', [ProductController::class, 'store']);
        Route::middleware('permission:products.edit')->put('products/{id}', [ProductController::class, 'update']);
        Route::middleware('permission:products.edit')->patch('products/{id}/status', [ProductController::class, 'toggleStatus']);
        Route::middleware('permission:products.edit')->delete('products/{id}', [ProductController::class, 'destroy']);
        Route::middleware('permission:products.create')->post('products/{id}/variants', [ProductController::class, 'storeVariant']);
        Route::middleware('permission:products.create')->post('products/{id}/variants/{variantId}/sizes', [ProductController::class, 'storeVariantSize']);

        // Product Images API
        Route::middleware('permission:products.view')->get('products/{id}/images', [ProductImageController::class, 'index']);
        Route::middleware('permission:products.view')->post('products/{id}/images', [ProductImageController::class, 'store']);
        Route::middleware('permission:products.edit')->put('products/images/{imageId}', [ProductImageController::class, 'update']);
        Route::middleware('permission:products.edit')->patch('products/images/{imageId}/primary', [ProductImageController::class, 'setPrimary']);
        Route::middleware('permission:products.edit')->patch('products/images/{imageId}/status', [ProductImageController::class, 'toggleStatus']);

        // HSN Codes API
        Route::middleware('permission:products.view')->get('hsn-codes', [HsnCodeController::class, 'index']);
        Route::middleware('permission:products.view')->get('hsn-codes/{id}', [HsnCodeController::class, 'show']);
        Route::middleware('permission:products.create')->post('hsn-codes', [HsnCodeController::class, 'store']);
        Route::middleware('permission:products.edit')->put('hsn-codes/{id}', [HsnCodeController::class, 'update']);

        // Tax Rates API
        Route::middleware('permission:products.view')->get('tax-rates', [TaxRateController::class, 'index']);
        Route::middleware('permission:products.view')->get('tax-rates/{id}', [TaxRateController::class, 'show']);
        Route::middleware('permission:products.edit')->post('tax-rates', [TaxRateController::class, 'store']);
        Route::middleware('permission:products.edit')->put('tax-rates/{id}', [TaxRateController::class, 'update']);

        // Tax Settings API
        Route::middleware('permission:products.view|system.settings')->get('tax-settings', [TaxSettingController::class, 'index']);
        Route::middleware('permission:products.edit|system.settings')->put('tax-settings', [TaxSettingController::class, 'update']);

        // Module Settings API
        Route::middleware('permission:products.view|system.settings')->get('module-settings', [ModuleSettingController::class, 'index']);
        Route::middleware('permission:products.edit|system.settings')->put('module-settings', [ModuleSettingController::class, 'update']);

        // Front Website Settings API (Admin)
        Route::middleware('permission:system.settings')->get('website-settings', [\App\Http\Controllers\Api\v1\FrontWebsiteSettingController::class, 'getAdminSettings']);
        Route::middleware('permission:system.settings')->put('website-settings', [\App\Http\Controllers\Api\v1\FrontWebsiteSettingController::class, 'updateSettings']);
        Route::middleware('permission:system.settings')->post('website-settings/logo', [\App\Http\Controllers\Api\v1\FrontWebsiteSettingController::class, 'uploadLogo']);
        Route::middleware('permission:system.settings')->post('website-settings/favicon', [\App\Http\Controllers\Api\v1\FrontWebsiteSettingController::class, 'uploadFavicon']);
        Route::middleware('permission:system.settings')->post('website-settings/media', [\App\Http\Controllers\Api\v1\FrontWebsiteSettingController::class, 'uploadMedia']);

        // Promotions & Discount Engine API
        Route::middleware('permission:products.view|pos.billing')->get('promotions', [PromotionController::class, 'index']);
        Route::middleware('permission:products.create')->post('promotions', [PromotionController::class, 'store']);
        Route::middleware('permission:products.view|pos.billing')->post('promotions/evaluate', [PromotionController::class, 'evaluate']);
        Route::middleware('permission:products.view|pos.billing')->get('promotions/{id}', [PromotionController::class, 'show']);
        Route::middleware('permission:products.edit')->put('promotions/{id}', [PromotionController::class, 'update']);
        Route::middleware('permission:products.edit')->delete('promotions/{id}', [PromotionController::class, 'destroy']);

        // Multi-Store POS Register & Drawer Management API
        Route::middleware('permission:products.view|pos.sessions|pos.billing')->get('pos/registers', [PosRegisterController::class, 'index']);
        Route::middleware('permission:products.create')->post('pos/registers', [PosRegisterController::class, 'store']);
        Route::middleware('permission:products.view|pos.sessions|pos.billing')->get('pos/registers/cash-movements', [PosRegisterController::class, 'cashMovements']);
        Route::middleware('permission:products.create|pos.sessions|pos.billing')->post('pos/registers/cash-in', [PosRegisterController::class, 'recordCashMovement']);
        Route::middleware('permission:products.create|pos.sessions|pos.billing')->post('pos/registers/cash-out', [PosRegisterController::class, 'recordCashMovement']);
        Route::middleware('permission:products.create|pos.sessions|pos.billing')->post('pos/registers/drawer-drop', [PosRegisterController::class, 'recordCashMovement']);
        Route::middleware('permission:products.view|pos.sessions|pos.billing')->get('pos/registers/{id}', [PosRegisterController::class, 'show']);
        Route::middleware('permission:products.edit')->put('pos/registers/{id}', [PosRegisterController::class, 'update']);
        Route::middleware('permission:products.edit')->patch('pos/registers/{id}/status', [PosRegisterController::class, 'toggleStatus']);
        Route::middleware('permission:products.edit')->post('pos/registers/{id}/assign', [PosRegisterController::class, 'assign']);
        Route::middleware('permission:products.create|pos.sessions|pos.billing')->post('pos/registers/{id}/open', [PosRegisterController::class, 'open']);
        Route::middleware('permission:products.view|pos.sessions|pos.billing')->get('pos/registers/{id}/current-session', [PosRegisterController::class, 'currentSession']);
        Route::middleware('permission:products.edit|pos.sessions|pos.billing')->post('pos/registers/{id}/close', [PosRegisterController::class, 'close']);
        Route::middleware('permission:products.view|pos.sessions|pos.billing')->get('pos/registers/{id}/reconciliations', [PosRegisterController::class, 'reconciliations']);

        // Customer Purchase History & Analytics API
        Route::middleware('permission:products.view')->get('customers/{id}/purchase-history', [CustomerAnalyticsController::class, 'purchaseHistory']);
        Route::middleware('permission:products.view')->get('customers/{id}/purchase-summary', [CustomerAnalyticsController::class, 'purchaseSummary']);
        Route::middleware('permission:products.view')->get('customers/{id}/purchase-analytics', [CustomerAnalyticsController::class, 'purchaseAnalytics']);
        Route::middleware('permission:products.view')->get('customers/{id}/rfm', [CustomerAnalyticsController::class, 'rfmSegmentation']);

        // Supplier Performance & Purchase Analytics API
        Route::middleware('permission:products.view')->get('suppliers/{id}/purchase-history', [SupplierAnalyticsController::class, 'purchaseHistory']);
        Route::middleware('permission:products.view')->get('suppliers/{id}/purchase-summary', [SupplierAnalyticsController::class, 'purchaseSummary']);
        Route::middleware('permission:products.view')->get('suppliers/{id}/performance-analytics', [SupplierAnalyticsController::class, 'performanceAnalytics']);

        // Centralized Thermal Printer Settings API
        Route::middleware('permission:system.settings|pos.billing|pos.sessions|products.view')->get('settings/printer', [PrinterSettingController::class, 'getSettings']);
        Route::middleware('permission:system.settings')->post('settings/printer', [PrinterSettingController::class, 'updateSettings']);
        Route::middleware('permission:system.settings')->post('settings/printer/logo', [PrinterSettingController::class, 'uploadLogo']);
        Route::middleware('permission:system.settings')->delete('settings/printer/logo', [PrinterSettingController::class, 'deleteLogo']);
        Route::middleware('permission:system.settings|pos.billing|pos.sessions')->post('settings/printer/test-print', [PrinterSettingController::class, 'testPrintData']);

        // Company Profile & Dual Branding Logo API
        Route::middleware('permission:system.settings|pos.billing|pos.sessions|products.view')->get('settings/company', [CompanyProfileController::class, 'show']);
        Route::middleware('permission:system.settings')->post('settings/company', [CompanyProfileController::class, 'update']);
        Route::middleware('permission:system.settings')->post('settings/company/primary-logo', [CompanyProfileController::class, 'uploadPrimaryLogo']);
        Route::middleware('permission:system.settings')->delete('settings/company/primary-logo', [CompanyProfileController::class, 'deletePrimaryLogo']);
        Route::middleware('permission:system.settings')->post('settings/company/white-logo', [CompanyProfileController::class, 'uploadWhiteLogo']);
        Route::middleware('permission:system.settings')->delete('settings/company/white-logo', [CompanyProfileController::class, 'deleteWhiteLogo']);

        // Invoice Settings API
        Route::middleware('permission:system.settings|invoice.settings')->get('settings/invoices', [InvoiceSettingController::class, 'getSettings']);
        Route::middleware('permission:system.settings|invoice.settings')->post('settings/invoices', [InvoiceSettingController::class, 'updateSettings']);

        // Payment Methods API
        Route::middleware('permission:pos.billing|pos.sessions|products.view|system.settings')->get('payment-methods/active', [PaymentMethodSettingController::class, 'getActiveMethods']);
        Route::middleware('permission:system.settings|payment_methods.manage')->get('settings/payment-methods', [PaymentMethodSettingController::class, 'index']);
        Route::middleware('permission:system.settings|payment_methods.manage')->post('settings/payment-methods', [PaymentMethodSettingController::class, 'store']);
        Route::middleware('permission:system.settings|payment_methods.manage')->put('settings/payment-methods/{id}', [PaymentMethodSettingController::class, 'update']);
        Route::middleware('permission:system.settings|payment_methods.manage')->patch('settings/payment-methods/{id}/status', [PaymentMethodSettingController::class, 'toggleStatus']);

        // POS Settings API
        Route::middleware('permission:pos.billing|pos.sessions|system.settings')->get('settings/pos', [PosSettingController::class, 'getSettings']);
        Route::middleware('permission:system.settings|pos.settings')->post('settings/pos', [PosSettingController::class, 'updateSettings']);

        // Number Series Settings API
        Route::middleware('permission:system.settings|number_series.manage')->get('settings/number-series', [NumberSeriesSettingController::class, 'getSettings']);
        Route::middleware('permission:system.settings|number_series.manage')->post('settings/number-series', [NumberSeriesSettingController::class, 'updateSettings']);

        // General Settings API
        Route::middleware('permission:system.settings|general.settings')->get('settings/general', [GeneralSettingController::class, 'getSettings']);
        Route::middleware('permission:system.settings|general.settings')->post('settings/general', [GeneralSettingController::class, 'updateSettings']);

        // Recycle Bin API
        Route::middleware('permission:recycle_bin.manage|system.settings')->group(function () {
            Route::get('recycle-bin', [RecycleBinController::class, 'index']);
            Route::post('recycle-bin/{type}/{id}/restore', [RecycleBinController::class, 'restore']);
            Route::delete('recycle-bin/{type}/{id}/force-delete', [RecycleBinController::class, 'forceDelete']);
        });
    });
});
