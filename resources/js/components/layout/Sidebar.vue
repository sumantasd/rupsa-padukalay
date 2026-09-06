<template>
  <aside
    :class="[
      'fixed inset-y-0 left-0 z-[301] w-72 bg-slate-950 text-slate-300 transform transition-transform duration-200 ease-in-out border-r border-slate-800/90 flex flex-col font-sans shadow-xl',
      uiStore.isSidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
    ]"
  >
    <!-- Brand Header -->
    <div class="h-16 flex items-center px-5 border-b border-slate-800/80 bg-slate-950 shrink-0">
      <div v-if="companyStore.whiteLogo" class="h-10 flex items-center max-w-full overflow-hidden">
        <img
          :src="companyStore.whiteLogo"
          :alt="companyStore.companyName"
          class="max-h-10 w-auto object-contain max-w-[200px]"
        />
      </div>
      <div v-else class="flex items-center gap-3">
        <div class="h-9 w-9 rounded-xl bg-red-600 flex items-center justify-center text-white font-black text-lg shadow-md shadow-red-600/30 shrink-0">
          <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
            <path d="M21.7 14.3c-.4-.4-.9-.7-1.5-.9l-3.2-1.1c-.8-.3-1.7-.1-2.3.4l-1.4 1.2c-.4.3-.9.5-1.4.5H8.5c-.8 0-1.5-.7-1.5-1.5 0-.6.4-1.1 1-1.4l4.2-1.8c.6-.3 1-.8 1.1-1.5l.3-1.8c.1-.8-.4-1.5-1.2-1.7l-3.2-.8c-.7-.2-1.5.1-2 .7L3.4 8.2C2.5 9.3 2 10.7 2 12.1V17c0 1.7 1.3 3 3 3h13.5c1.4 0 2.6-.9 2.9-2.3l.5-2.2c.2-.4.1-.9-.2-1.2z"/>
          </svg>
        </div>
        <div>
          <h1 class="font-black text-sm text-white tracking-wide leading-none">{{ companyStore.companyName }}</h1>
          <p class="text-[8px] font-extrabold text-red-500 tracking-wider uppercase mt-0.5">— {{ companyStore.tagline }} —</p>
        </div>
      </div>
    </div>

    <!-- Categorized Navigation Menu (Independent Vertical Scrollbar) -->
    <nav class="flex-1 px-3 py-4 overflow-y-auto space-y-3 text-xs">
      <div v-for="group in visibleMenuGroups" :key="group.title" class="space-y-1">
        <!-- Section Header -->
        <button
          @click="toggleGroup(group.title)"
          class="w-full flex items-center justify-between px-3 py-1 text-[9px] font-extrabold text-slate-500 uppercase tracking-widest hover:text-slate-300 transition-colors"
        >
          <span>{{ group.title }}</span>
          <span v-if="group.items.length > 1" class="text-[8px]">
            {{ expandedGroups[group.title] ? '▼' : '▶' }}
          </span>
        </button>

        <!-- Submenu Items -->
        <div v-show="expandedGroups[group.title] || group.items.length === 1" class="space-y-0.5 pt-0.5">
          <RouterLink
            v-for="item in group.items"
            :key="item.name"
            :to="item.path"
            @click="handleNavClick"
            :class="[
              'flex items-center gap-2.5 px-3 py-2 rounded-xl font-bold transition-all',
              route.path === item.path || (item.path !== '/admin' && route.path.startsWith(item.path + '/'))
                ? 'bg-red-600 text-white shadow-md shadow-red-600/20'
                : 'text-slate-400 hover:bg-slate-900 hover:text-white'
            ]"
          >
            <span class="text-sm shrink-0">{{ item.icon }}</span>
            <span class="truncate">{{ item.name }}</span>
            <span v-if="item.badge" class="ml-auto px-1.5 py-0.5 text-[8px] font-black bg-emerald-500 text-slate-950 rounded uppercase">
              {{ item.badge }}
            </span>
          </RouterLink>
        </div>
      </div>
    </nav>

    <!-- Footer Status -->
    <div class="p-4 border-t border-slate-800/80 bg-slate-950/80 text-[10px] text-slate-500 flex items-center justify-between shrink-0">
      <span>RUPSA ERP v1.0</span>
      <span class="flex items-center gap-1.5 text-emerald-400 font-extrabold">
        <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
        API Online
      </span>
    </div>
  </aside>
</template>

<script setup>
import { reactive, computed, watch } from 'vue';
import { useUiStore } from '../../stores/uiStore';
import { useCompanyStore } from '../../stores/companyStore';
import { useAuth } from '../../composables/useAuth';
import { useRoute } from 'vue-router';

const uiStore = useUiStore();
const companyStore = useCompanyStore();
const { hasPermission, isSuperAdmin } = useAuth();
const route = useRoute();

const expandedGroups = reactive({
  'DASHBOARD': true,
  'MASTER DATA': true,
  'SALES': true,
  'INVENTORY': true,
  'PURCHASES': true,
  'PAYMENTS': true,
  'REPORTS': true,
  'STORE MANAGEMENT': true,
  'ACCESS CONTROL': true,
  'SETTINGS': true,
  'FRONT WEBSITE': true,
});

function toggleGroup(title) {
  expandedGroups[title] = !expandedGroups[title];
}

function handleNavClick() {
  if (window.innerWidth < 1024) {
    uiStore.closeSidebar();
  }
}

// Admin Navigation Menu Groups with FRONT WEBSITE as final main section with submenus
const allMenuGroups = [
  {
    title: 'DASHBOARD',
    items: [
      { name: 'Dashboard', path: '/admin/dashboard', icon: '📊', permission: 'products.view' },
    ]
  },
  {
    title: 'MASTER DATA',
    items: [
      { name: 'Products', path: '/admin/products', icon: '👟', permission: 'products.view' },
      { name: 'Categories', path: '/admin/categories', icon: '🏷️', permission: 'products.view' },
      { name: 'Brands', path: '/admin/brands', icon: '🏅', permission: 'products.view' },
      { name: 'Sizes', path: '/admin/sizes', icon: '📏', permission: 'products.view' },
      { name: 'Colors', path: '/admin/colors', icon: '🎨', permission: 'products.view' },
      { name: 'Customers', path: '/admin/customers', icon: '👥', permission: 'customers.view' },
      { name: 'Suppliers', path: '/admin/suppliers', icon: '🏢', permission: 'suppliers.view' },
    ]
  },
  {
    title: 'SALES',
    items: [
      { name: 'New Sale / POS', path: '/admin/pos', icon: '🛒', badge: 'POS', permission: 'pos.billing' },
      { name: 'Sales Invoices', path: '/admin/sales', icon: '🧾', permission: 'sales.view|pos.billing' },
      { name: 'Sales Returns', path: '/admin/sales-returns', icon: '↩️', permission: 'sales_returns.view|pos.returns' },
      { name: 'Exchanges', path: '/admin/exchanges', icon: '🔄', permission: 'exchanges.view|pos.exchanges' },
    ]
  },
  {
    title: 'INVENTORY',
    items: [
      { name: 'Stock Overview', path: '/admin/inventory/stock', icon: '📦', permission: 'inventory.view' },
      { name: 'Stock Movements', path: '/admin/inventory/movements', icon: '📈', permission: 'inventory.view' },
      { name: 'Stock Adjustments', path: '/admin/inventory/adjustments', icon: '⚙️', permission: 'inventory.adjust' },
      { name: 'Stock Transfers', path: '/admin/inventory/transfers', icon: '🚚', permission: 'inventory.transfer' },
      { name: 'Low Stock', path: '/admin/inventory/low-stock', icon: '⚠️', permission: 'inventory.view' },
      { name: 'Stock Out – Damage', path: '/admin/stock-damage', icon: '🗑️', permission: 'inventory.view' },
    ]
  },
  {
    title: 'PURCHASES',
    items: [
      { name: 'Purchase Orders', path: '/admin/purchase-orders', icon: '📝', permission: 'procurement.view' },
      { name: 'Goods Receive', path: '/admin/grn', icon: '📥', permission: 'procurement.receive' },
      { name: 'Purchase Bills', path: '/admin/purchase-bills', icon: '📑', permission: 'procurement.view' },
      { name: 'Purchase Returns', path: '/admin/purchase-returns', icon: '↩️', permission: 'procurement.view' },
    ]
  },
  {
    title: 'PAYMENTS & EXPENSES',
    items: [
      { name: 'Payment Collections', path: '/admin/payments/collections', icon: '💳', permission: 'pos.billing' },
      { name: 'Refunds', path: '/admin/payments/refunds', icon: '💸', permission: 'pos.returns' },
      { name: 'Expenses', path: '/admin/expenses', icon: '💸', permission: 'expenses.view' },
      { name: 'Cash Drawer', path: '/admin/payments/cash-drawer', icon: '💵', permission: 'pos.sessions' },
      { name: 'Day Closing', path: '/admin/payments/day-closing', icon: '🔒', permission: 'pos.sessions' },
    ]
  },
  {
    title: 'REPORTS',
    items: [
      { name: 'Sales Reports', path: '/admin/reports/sales', icon: '📊', permission: 'reports.view' },
      { name: 'Inventory Reports', path: '/admin/reports/inventory', icon: '📦', permission: 'reports.view' },
      { name: 'Purchase Reports', path: '/admin/reports/purchases', icon: '📝', permission: 'reports.view' },
      { name: 'Customer Reports', path: '/admin/reports/customers', icon: '👥', permission: 'reports.view' },
      { name: 'Payment Reports', path: '/admin/reports/payments', icon: '💳', permission: 'reports.view' },
      { name: 'Profit & Margin', path: '/admin/reports/profit-margin', icon: '📈', permission: 'reports.view' },
    ]
  },
  {
    title: 'STORE MANAGEMENT',
    items: [
      { name: 'Stores', path: '/admin/stores', icon: '🏬', permission: 'stores.manage' },
      { name: 'Store Performance', path: '/admin/stores/performance', icon: '🎯', permission: 'reports.view' },
    ]
  },
  {
    title: 'ACCESS CONTROL',
    items: [
      { name: 'Users', path: '/admin/users', icon: '🔑', permission: 'users.manage' },
      { name: 'Roles & Permissions', path: '/admin/roles', icon: '🛡️', permission: 'roles.manage' },
      { name: 'Store Access', path: '/admin/store-access', icon: '🔒', permission: 'users.manage' },
      { name: 'Audit Log', path: '/admin/audit', icon: '📜', permission: 'audit.view' },
    ]
  },
  {
    title: 'SETTINGS',
    items: [
      { name: 'Company Profile', path: '/admin/settings/company', icon: '🏢', permission: 'system.settings|company.settings' },
      { name: 'Invoice Settings', path: '/admin/settings/invoices', icon: '🧾', permission: 'system.settings|invoice.settings' },
      { name: 'Tax Settings', path: '/admin/settings/tax', icon: '📑', permission: 'system.settings|tax.settings' },
      { name: 'Payment Methods', path: '/admin/settings/payment-methods', icon: '💳', permission: 'system.settings|payment_methods.manage' },
      { name: 'POS Settings', path: '/admin/settings/pos', icon: '⚙️', permission: 'system.settings|pos.settings' },
      { name: 'Stock Settings', path: '/admin/settings/stock', icon: '📦', permission: 'system.settings|stock.settings' },
      { name: 'Printer Settings', path: '/admin/settings/printers', icon: '🖨️', permission: 'system.settings|printer.settings' },
      { name: 'Number Series', path: '/admin/settings/number-series', icon: '🔢', permission: 'system.settings|number_series.manage' },
      { name: 'General Settings', path: '/admin/settings/general', icon: '🔧', permission: 'system.settings|general.settings' },
      { name: 'Module Settings', path: '/admin/settings/modules', icon: '🧩', permission: 'system.settings|module.settings' },
      { name: 'Database Management', path: '/admin/settings/database', icon: '🗄️', permission: 'database.manage|system.settings' },
      { name: 'Recycle Bin', path: '/admin/settings/recycle-bin', icon: '🗑️', permission: 'recycle_bin.manage|system.settings' },
    ]
  },
  {
    title: 'FRONT WEBSITE',
    items: [
      { name: 'Home Page', path: '/admin/front-website/home', icon: '🏠', permission: 'system.settings' },
      { name: 'Header & Footer', path: '/admin/front-website/header-footer', icon: '🎨', permission: 'system.settings' },
      { name: 'Pages', path: '/admin/front-website/pages', icon: '📄', permission: 'system.settings' },
      { name: 'Banners', path: '/admin/front-website/banners', icon: '🖼️', permission: 'system.settings' },
      { name: 'Shop Categories', path: '/admin/front-website/categories', icon: '🏷️', permission: 'system.settings' },
      { name: 'Brands / Our Partners', path: '/admin/front-website/brands', icon: '🏅', permission: 'system.settings' },
      { name: 'Contact & Business Info', path: '/admin/front-website/contact-info', icon: '📞', permission: 'system.settings' },
    ]
  },
];

const visibleMenuGroups = computed(() => {
  if (isSuperAdmin.value) return allMenuGroups;

  return allMenuGroups
    .map(g => ({
      ...g,
      items: g.items.filter(item => !item.permission || hasPermission(item.permission))
    }))
    .filter(g => g.items.length > 0);
});

// Automatically expand parent group when active route changes
watch(() => route.path, (newPath) => {
  allMenuGroups.forEach(g => {
    if (g.items.some(item => item.path === newPath)) {
      expandedGroups[g.title] = true;
    }
  });
}, { immediate: true });
</script>
