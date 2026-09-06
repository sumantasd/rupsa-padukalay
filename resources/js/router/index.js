import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/authStore';

import AuthLayout from '../layouts/AuthLayout.vue';
import AdminLayout from '../layouts/AdminLayout.vue';
import PosLayout from '../layouts/PosLayout.vue';

import Login from '../views/auth/Login.vue';
import ExecutiveDashboard from '../views/dashboard/ExecutiveDashboard.vue';

import ProductListView from '../views/products/ProductListView.vue';
import ProductCreateView from '../views/products/ProductCreateView.vue';
import ProductDetailView from '../views/products/ProductDetailView.vue';
import ProductEditView from '../views/products/ProductEditView.vue';

import CategoryListView from '../views/products/CategoryListView.vue';
import BrandListView from '../views/products/BrandListView.vue';
import SizeListView from '../views/products/SizeListView.vue';
import ColorListView from '../views/products/ColorListView.vue';

import CustomerListView from '../views/crm/CustomerListView.vue';
import CustomerFormView from '../views/crm/CustomerFormView.vue';
import CustomerDetailView from '../views/crm/CustomerDetailView.vue';

import SupplierListView from '../views/crm/SupplierListView.vue';
import SupplierFormView from '../views/crm/SupplierFormView.vue';
import SupplierDetailView from '../views/crm/SupplierDetailView.vue';
import StockOverviewView from '../views/inventory/StockOverviewView.vue';
import StockMovementsView from '../views/inventory/StockMovementsView.vue';
import StockAdjustmentsView from '../views/inventory/StockAdjustmentsView.vue';
import StockTransfersView from '../views/inventory/StockTransfersView.vue';
import LowStockAlertsView from '../views/inventory/LowStockAlertsView.vue';
import StockDamageView from '../views/inventory/StockDamageView.vue';
import PurchaseOrderListView from '../views/purchases/PurchaseOrderListView.vue';
import GoodsReceiveListView from '../views/purchases/GoodsReceiveListView.vue';
import PurchaseBillListView from '../views/purchases/PurchaseBillListView.vue';
import PurchaseReturnListView from '../views/purchases/PurchaseReturnListView.vue';
import PaymentCollectionsView from '../views/payments/PaymentCollectionsView.vue';
import RefundsView from '../views/payments/RefundsView.vue';
import CashDrawerView from '../views/payments/CashDrawerView.vue';
import DayClosingView from '../views/payments/DayClosingView.vue';
import ExpenseListView from '../views/finance/ExpenseListView.vue';
import FinancialReportsView from '../views/finance/FinancialReportsView.vue';
import AuditLogsView from '../views/system/AuditLogsView.vue';
import PosTerminalView from '../views/pos/PosTerminalView.vue';
import SalesInvoiceListView from '../views/sales/SalesInvoiceListView.vue';
import SalesReturnListView from '../views/sales/SalesReturnListView.vue';
import ExchangeListView from '../views/sales/ExchangeListView.vue';
import PlaceholderView from '../views/common/PlaceholderView.vue';

import SalesReportsView from '../views/reports/SalesReportsView.vue';
import InventoryReportsView from '../views/reports/InventoryReportsView.vue';
import PurchaseReportsView from '../views/reports/PurchaseReportsView.vue';
import CustomerReportsView from '../views/reports/CustomerReportsView.vue';
import PaymentReportsView from '../views/reports/PaymentReportsView.vue';
import ProfitMarginView from '../views/reports/ProfitMarginView.vue';

import StoreList from '../views/settings/StoreList.vue';
import StorePerformanceView from '../views/settings/StorePerformanceView.vue';
import UserList from '../views/settings/UserList.vue';
import RoleList from '../views/settings/RoleList.vue';
import StoreAccessView from '../views/settings/StoreAccessView.vue';
import ProfileView from '../views/settings/ProfileView.vue';
import ModuleSettingsView from '../views/settings/ModuleSettingsView.vue';
import PrinterSettingsView from '../views/settings/PrinterSettingsView.vue';
import InventorySettingsView from '../views/settings/InventorySettingsView.vue';
import CompanyProfileView from '../views/settings/CompanyProfileView.vue';
import InvoiceSettingsView from '../views/settings/InvoiceSettingsView.vue';
import TaxSettingsView from '../views/settings/TaxSettingsView.vue';
import PaymentMethodsSettingsView from '../views/settings/PaymentMethodsSettingsView.vue';
import PosSettingsView from '../views/settings/PosSettingsView.vue';
import NumberSeriesSettingsView from '../views/settings/NumberSeriesSettingsView.vue';
import GeneralSettingsView from '../views/settings/GeneralSettingsView.vue';
import DatabaseManagementView from '../views/settings/DatabaseManagementView.vue';
import RecycleBinView from '../views/settings/RecycleBinView.vue';

import WebsiteDashboardView from '../views/front_website/WebsiteDashboardView.vue';
import FrontHomePageView from '../views/front_website/FrontHomePageView.vue';
import FrontAboutPageView from '../views/front_website/FrontAboutPageView.vue';
import FrontCategoryPageView from '../views/front_website/FrontCategoryPageView.vue';
import FrontContactPageView from '../views/front_website/FrontContactPageView.vue';
import FrontBannersView from '../views/front_website/FrontBannersView.vue';
import FrontHeaderNavView from '../views/front_website/FrontHeaderNavView.vue';
import FrontFooterView from '../views/front_website/FrontFooterView.vue';
import FrontPagesView from '../views/front_website/FrontPagesView.vue';
import FrontContentView from '../views/front_website/FrontContentView.vue';
import FrontTestimonialsView from '../views/front_website/FrontTestimonialsView.vue';
import FrontFaqView from '../views/front_website/FrontFaqView.vue';
import FrontPromotionsView from '../views/front_website/FrontPromotionsView.vue';
import FrontSocialLinksView from '../views/front_website/FrontSocialLinksView.vue';
import FrontWebsiteSettingsView from '../views/settings/FrontWebsiteSettingsView.vue';

const routes = [
    {
        path: '/admin/login',
        component: AuthLayout,
        children: [
            { path: '', name: 'admin-login', component: Login, meta: { guestOnly: true } },
        ],
    },
    {
        path: '/admin',
        component: AdminLayout,
        meta: { requiresAuth: true },
        children: [
            { path: '', redirect: '/admin/dashboard' },
            { path: 'dashboard', name: 'admin-dashboard', component: ExecutiveDashboard },

            // FRONT WEBSITE MANAGEMENT ROUTES (Dedicated Main Section)
            { path: 'front-website', name: 'front-website-dashboard', component: WebsiteDashboardView, meta: { permission: 'system.settings' } },
            { path: 'front-website/home', name: 'front-website-home', component: FrontHomePageView, meta: { permission: 'system.settings' } },
            { path: 'front-website/about', name: 'front-website-about', component: FrontAboutPageView, meta: { permission: 'system.settings' } },
            { path: 'front-website/men', name: 'front-website-men', component: FrontCategoryPageView, meta: { permission: 'system.settings' } },
            { path: 'front-website/women', name: 'front-website-women', component: FrontCategoryPageView, meta: { permission: 'system.settings' } },
            { path: 'front-website/kids', name: 'front-website-kids', component: FrontCategoryPageView, meta: { permission: 'system.settings' } },
            { path: 'front-website/others', name: 'front-website-others', component: FrontCategoryPageView, meta: { permission: 'system.settings' } },
            { path: 'front-website/contact', name: 'front-website-contact', component: FrontContactPageView, meta: { permission: 'system.settings' } },
            { path: 'front-website/banners', name: 'front-website-banners', component: FrontBannersView, meta: { permission: 'system.settings' } },
            { path: 'front-website/header', name: 'front-website-header', component: FrontHeaderNavView, meta: { permission: 'system.settings' } },
            { path: 'front-website/footer', name: 'front-website-footer', component: FrontFooterView, meta: { permission: 'system.settings' } },
            { path: 'front-website/pages', name: 'front-website-pages', component: FrontPagesView, meta: { permission: 'system.settings' } },
            { path: 'front-website/content', name: 'front-website-content', component: FrontContentView, meta: { permission: 'system.settings' } },
            { path: 'front-website/testimonials', name: 'front-website-testimonials', component: FrontTestimonialsView, meta: { permission: 'system.settings' } },
            { path: 'front-website/faq', name: 'front-website-faq', component: FrontFaqView, meta: { permission: 'system.settings' } },
            { path: 'front-website/promotions', name: 'front-website-promotions', component: FrontPromotionsView, meta: { permission: 'system.settings' } },
            { path: 'front-website/social-links', name: 'front-website-social-links', component: FrontSocialLinksView, meta: { permission: 'system.settings' } },
            { path: 'front-website/settings', name: 'front-website-settings', component: FrontWebsiteSettingsView, meta: { permission: 'system.settings' } },

            // Master Data Routes
            { path: 'products', name: 'products-list', component: ProductListView, meta: { permission: 'products.view' } },
            { path: 'products/create', name: 'products-create', component: ProductCreateView, meta: { permission: 'products.create' } },
            { path: 'products/:id', name: 'products-detail', component: ProductDetailView, meta: { permission: 'products.view' } },
            { path: 'products/:id/edit', name: 'products-edit', component: ProductEditView, meta: { permission: 'products.edit' } },

            { path: 'categories', name: 'categories-list', component: CategoryListView, meta: { permission: 'products.view' } },
            { path: 'brands', name: 'brands-list', component: BrandListView, meta: { permission: 'products.view' } },
            { path: 'sizes', name: 'sizes-list', component: SizeListView, meta: { permission: 'products.view' } },
            { path: 'colors', name: 'colors-list', component: ColorListView, meta: { permission: 'products.view' } },
            { path: 'customers', name: 'customers-list', component: CustomerListView, meta: { permission: 'customers.view' } },
            { path: 'customers/create', name: 'customers-create', component: CustomerFormView, meta: { permission: 'customers.create' } },
            { path: 'customers/:id', name: 'customers-detail', component: CustomerDetailView, meta: { permission: 'customers.view' } },
            { path: 'customers/:id/edit', name: 'customers-edit', component: CustomerFormView, meta: { permission: 'customers.edit' } },

            { path: 'suppliers', name: 'suppliers-list', component: SupplierListView, meta: { permission: 'suppliers.view' } },
            { path: 'suppliers/create', name: 'suppliers-create', component: SupplierFormView, meta: { permission: 'suppliers.create' } },
            { path: 'suppliers/:id', name: 'suppliers-detail', component: SupplierDetailView, meta: { permission: 'suppliers.view' } },
            { path: 'suppliers/:id/edit', name: 'suppliers-edit', component: SupplierFormView, meta: { permission: 'suppliers.edit' } },

            // Sales & Billing Routes
            { path: 'sales', name: 'sales-list', component: SalesInvoiceListView, meta: { permission: 'sales.view|pos.billing' } },
            { path: 'sales-returns', name: 'sales-returns', component: SalesReturnListView, meta: { permission: 'sales_returns.view|pos.returns' } },
            { path: 'exchanges', name: 'exchanges-list', component: ExchangeListView, meta: { permission: 'exchanges.view|pos.exchanges' } },

            // Inventory Routes
            { path: 'inventory/stock', name: 'stock-overview', component: StockOverviewView, meta: { permission: 'inventory.view' } },
            { path: 'inventory/movements', name: 'stock-movements', component: StockMovementsView, meta: { permission: 'inventory.view' } },
            { path: 'inventory/adjustments', name: 'stock-adjustments', component: StockAdjustmentsView, meta: { permission: 'inventory.adjust' } },
            { path: 'inventory/transfers', name: 'stock-transfers', component: StockTransfersView, meta: { permission: 'inventory.transfer' } },
            { path: 'inventory/low-stock', name: 'low-stock-alerts', component: LowStockAlertsView, meta: { permission: 'inventory.view' } },
            { path: 'stock-damage', name: 'stock-damage', component: StockDamageView, meta: { permission: 'inventory.view' } },

            // Purchases & Procurement Routes
            { path: 'purchase-orders', name: 'purchase-orders-list', component: PurchaseOrderListView, meta: { permission: 'procurement.view' } },
            { path: 'purchases/orders', redirect: '/admin/purchase-orders' },
            { path: 'grn', name: 'grn-list', component: GoodsReceiveListView, meta: { permission: 'procurement.receive' } },
            { path: 'purchases/grn', redirect: to => ({ path: '/admin/grn', query: to.query }) },
            { path: 'purchase-bills', name: 'purchase-bills-list', component: PurchaseBillListView, meta: { permission: 'procurement.view' } },
            { path: 'purchases/bills', redirect: '/admin/purchase-bills' },
            { path: 'purchase-returns', name: 'purchase-returns-list', component: PurchaseReturnListView, meta: { permission: 'procurement.view' } },
            { path: 'purchases/returns', redirect: '/admin/purchase-returns' },

            // Payments & Financial Routes
            { path: 'payments/collections', name: 'payments-collections', component: PaymentCollectionsView, meta: { permission: 'pos.billing' } },
            { path: 'payment-collections', redirect: '/admin/payments/collections' },
            { path: 'payments/refunds', name: 'payments-refunds', component: RefundsView, meta: { permission: 'pos.returns' } },
            { path: 'refunds', redirect: '/admin/payments/refunds' },
            { path: 'payments/cash-drawer', name: 'cash-drawer', component: CashDrawerView, meta: { permission: 'pos.sessions' } },
            { path: 'cash-drawer', redirect: '/admin/payments/cash-drawer' },
            { path: 'payments/day-closing', name: 'day-closing', component: DayClosingView, meta: { permission: 'pos.sessions' } },
            { path: 'day-closing', redirect: '/admin/payments/day-closing' },
            { path: 'expenses', name: 'expenses-list', component: ExpenseListView, meta: { permission: 'expenses.view' } },

            // Reports & Analytics Routes
            { path: 'reports/sales', name: 'reports-sales', component: SalesReportsView, meta: { permission: 'reports.view' } },
            { path: 'reports/inventory', name: 'reports-inventory', component: InventoryReportsView, meta: { permission: 'reports.view' } },
            { path: 'reports/purchases', name: 'reports-purchases', component: PurchaseReportsView, meta: { permission: 'reports.view' } },
            { path: 'reports/customers', name: 'reports-customers', component: CustomerReportsView, meta: { permission: 'reports.view' } },
            { path: 'reports/payments', name: 'reports-payments', component: PaymentReportsView, meta: { permission: 'reports.view' } },
            { path: 'reports/profit-margin', name: 'reports-profit-margin', component: ProfitMarginView, meta: { permission: 'reports.view' } },

            // Store Management Routes
            { path: 'stores', name: 'stores-list', component: StoreList, meta: { permission: 'stores.manage|stores.view|users.manage' } },
            { path: 'stores/performance', name: 'stores-performance', component: StorePerformanceView, meta: { permission: 'reports.view|stores.manage|stores.view|users.manage' } },

            // Access Control & Security Routes
            { path: 'users', name: 'users-list', component: UserList, meta: { permission: 'users.manage' } },
            { path: 'roles', name: 'roles-list', component: RoleList, meta: { permission: 'roles.manage' } },
            { path: 'profile', name: 'my-profile', component: ProfileView },
            { path: 'store-access', name: 'store-access', component: StoreAccessView, meta: { permission: 'stores.manage|users.manage' } },
            { path: 'audit', name: 'audit-log', component: AuditLogsView, meta: { permission: 'audit.view' } },

            // System Settings Routes
            { path: 'settings/company', name: 'settings-company', component: CompanyProfileView, meta: { permission: 'system.settings|company.settings' } },
            { path: 'settings/invoices', name: 'settings-invoices', component: InvoiceSettingsView, meta: { permission: 'system.settings|invoice.settings' } },
            { path: 'settings/tax', name: 'settings-tax', component: TaxSettingsView, meta: { permission: 'system.settings|tax.settings' } },
            { path: 'settings/payment-methods', name: 'settings-payment-methods', component: PaymentMethodsSettingsView, meta: { permission: 'system.settings|payment_methods.manage' } },
            { path: 'settings/pos', name: 'settings-pos', component: PosSettingsView, meta: { permission: 'system.settings|pos.settings' } },
            { path: 'settings/stock', name: 'settings-stock', component: InventorySettingsView, meta: { permission: 'system.settings|stock.settings' } },
            { path: 'settings/printers', name: 'settings-printers', component: PrinterSettingsView, meta: { permission: 'system.settings|printer.settings' } },
            { path: 'settings/number-series', name: 'settings-number-series', component: NumberSeriesSettingsView, meta: { permission: 'system.settings|number_series.manage' } },
            { path: 'settings/general', name: 'settings-general', component: GeneralSettingsView, meta: { permission: 'system.settings|general.settings' } },
            { path: 'settings/modules', name: 'settings-modules', component: ModuleSettingsView, meta: { permission: 'system.settings|module.settings' } },
            { path: 'settings/database', name: 'settings-database', component: DatabaseManagementView, meta: { permission: 'database.manage|system.settings' } },
            { path: 'settings/recycle-bin', name: 'settings-recycle-bin', component: RecycleBinView, meta: { permission: 'recycle_bin.manage|system.settings' } },
            { path: 'settings/front-website', name: 'settings-front-website', component: FrontWebsiteSettingsView, meta: { permission: 'system.settings' } },

            // Front Website Central Management Routes
            { path: 'front-website', name: 'front-website-dashboard', component: FrontWebsiteSettingsView, props: { initialTab: 'homepage' }, meta: { permission: 'system.settings' } },
            { path: 'front-website/home', name: 'front-website-home', component: FrontWebsiteSettingsView, props: { initialTab: 'homepage' }, meta: { permission: 'system.settings' } },
            { path: 'front-website/header-footer', name: 'front-website-header-footer', component: FrontWebsiteSettingsView, props: { initialTab: 'header_footer' }, meta: { permission: 'system.settings' } },
            { path: 'front-website/pages', name: 'front-website-pages', component: FrontWebsiteSettingsView, props: { initialTab: 'homepage', initialSubTab: 'full_banners' }, meta: { permission: 'system.settings' } },
            { path: 'front-website/banners', name: 'front-website-banners', component: FrontWebsiteSettingsView, props: { initialTab: 'homepage', initialSubTab: 'hero' }, meta: { permission: 'system.settings' } },
            { path: 'front-website/categories', name: 'front-website-categories', component: FrontWebsiteSettingsView, props: { initialTab: 'homepage', initialSubTab: 'categories' }, meta: { permission: 'system.settings' } },
            { path: 'front-website/brands', name: 'front-website-brands', component: FrontWebsiteSettingsView, props: { initialTab: 'homepage', initialSubTab: 'brands' }, meta: { permission: 'system.settings' } },
            { path: 'front-website/contact-info', name: 'front-website-contact-info', component: FrontWebsiteSettingsView, props: { initialTab: 'business' }, meta: { permission: 'system.settings' } },
        ],
    },
    {
        path: '/admin/pos',
        component: PosLayout,
        meta: { requiresAuth: true },
        children: [
            { path: '', name: 'pos-terminal', component: PosTerminalView, meta: { permission: 'pos.billing' } },
        ],
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/admin/dashboard',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to, from, next) => {
    const authStore = useAuthStore();

    const requiresAuth = to.matched.some(record => record.meta.requiresAuth);
    const guestOnly = to.matched.some(record => record.meta.guestOnly);

    // 1. Guest-only routes (e.g. /admin/login) redirect to dashboard if authenticated
    if (guestOnly && authStore.isAuthenticated) {
        return next('/admin/dashboard');
    }

    // 2. Unauthenticated access to protected routes redirects to /admin/login
    if (requiresAuth && !authStore.isAuthenticated) {
        return next('/admin/login');
    }

    // 3. Permission checks for authenticated users
    const requiredPermission = to.meta?.permission;
    if (requiresAuth && authStore.isAuthenticated && requiredPermission) {
        if (authStore.isSuperAdmin || authStore.hasPermission(requiredPermission)) {
            return next();
        } else {
            console.warn(`Access denied to ${to.path}. Required permission: ${requiredPermission}`);
            if (to.path === '/admin/dashboard' || to.path === '/admin') {
                return next();
            }
            return next('/admin/dashboard');
        }
    }

    next();
});

export default router;
