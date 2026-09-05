<template>
  <Teleport to="body">
    <!-- Backdrop Blur -->
    <div
      v-if="uiStore.isMoreMenuOpen"
      @click="uiStore.closeMoreMenu()"
      class="fixed inset-0 z-[300] bg-slate-950/70 backdrop-blur-sm lg:hidden transition-opacity"
    ></div>

    <!-- Full-Screen Slide-Over Mobile Sheet (White Background Theme) -->
    <div
      :class="[
        'fixed inset-y-0 right-0 z-[301] w-full max-w-md bg-white text-slate-900 flex flex-col font-sans shadow-2xl transition-transform duration-300 ease-in-out lg:hidden border-l border-slate-200',
        uiStore.isMoreMenuOpen ? 'translate-x-0' : 'translate-x-full'
      ]"
    >
      <!-- Header Bar -->
      <div class="h-16 px-5 border-b border-slate-200 flex items-center justify-between bg-white shrink-0">
        <div class="flex items-center gap-3">
          <div v-if="companyStore.primaryLogo" class="h-9 flex items-center shrink-0">
            <img :src="companyStore.primaryLogo" :alt="companyStore.companyName" class="max-h-9 w-auto object-contain max-w-[150px]" />
          </div>
          <div v-else class="h-9 w-9 rounded-xl bg-red-600 flex items-center justify-center text-white font-black text-lg shadow-md shadow-red-600/30 shrink-0">
            <span>☰</span>
          </div>
          <div>
            <h2 class="font-black text-sm text-slate-900 tracking-wide leading-none">ALL ERP MODULES</h2>
            <p class="text-[9px] font-extrabold text-red-600 uppercase tracking-widest mt-0.5">{{ companyStore.companyName }} NAVIGATOR</p>
          </div>
        </div>
        <button
          @click="uiStore.closeMoreMenu()"
          class="w-9 h-9 rounded-xl bg-slate-100 border border-slate-200 text-slate-700 hover:bg-slate-200 flex items-center justify-center font-black text-base cursor-pointer"
        >
          ✕
        </button>
      </div>

      <!-- Quick Search Input -->
      <div class="p-4 bg-slate-50 border-b border-slate-200 shrink-0">
        <div class="relative">
          <span class="absolute left-3 top-3 text-xs text-slate-400">🔍</span>
          <input
            type="text"
            v-model="searchQuery"
            placeholder="Search module or page (e.g. Damage, PO, Day Closing)..."
            class="w-full bg-white border border-slate-300 rounded-xl pl-9 pr-4 py-2.5 text-xs font-bold text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-red-500/20"
          />
          <button v-if="searchQuery" @click="searchQuery = ''" class="absolute right-3 top-2.5 text-xs text-slate-400 font-bold">✕</button>
        </div>
      </div>

      <!-- Scrollable Categorized List -->
      <div class="flex-1 overflow-y-auto p-4 space-y-5 pb-28 text-xs">
        <div v-for="group in filteredGroups" :key="group.title" class="space-y-1.5">
          <div class="text-[10px] font-black text-red-600 uppercase tracking-widest px-2 flex items-center gap-1.5">
            <span>{{ group.icon || '📌' }}</span>
            <span>{{ group.title }}</span>
          </div>

          <div class="grid grid-cols-1 gap-1.5">
            <RouterLink
              v-for="item in group.items"
              :key="item.name"
              :to="item.path"
              @click="onItemClick"
              :class="[
                'flex items-center justify-between px-3.5 py-3 rounded-xl font-bold transition-all min-h-[44px]',
                route.path === item.path || (item.path !== '/admin' && route.path.startsWith(item.path + '/'))
                  ? 'bg-red-600 text-white shadow-lg shadow-red-600/20'
                  : 'bg-slate-50 border border-slate-200/90 text-slate-800 hover:bg-slate-100 active:bg-slate-200'
              ]"
            >
              <div class="flex items-center gap-3">
                <span class="text-base shrink-0">{{ item.icon }}</span>
                <span class="text-xs truncate font-bold">{{ item.name }}</span>
              </div>
              <div class="flex items-center gap-2">
                <span v-if="item.badge" class="px-1.5 py-0.5 text-[8px] font-black bg-emerald-500 text-slate-950 rounded uppercase">
                  {{ item.badge }}
                </span>
                <span :class="route.path === item.path ? 'text-white' : 'text-slate-400'" class="text-xs">➔</span>
              </div>
            </RouterLink>
          </div>
        </div>

        <div v-if="filteredGroups.length === 0" class="p-8 text-center text-slate-400 font-bold text-xs">
          No matching modules found for "{{ searchQuery }}"
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useUiStore } from '../../stores/uiStore';
import { useCompanyStore } from '../../stores/companyStore';
import { useAuth } from '../../composables/useAuth';
import { useRoute } from 'vue-router';

const uiStore = useUiStore();
const companyStore = useCompanyStore();
const { hasPermission, isSuperAdmin } = useAuth();
const route = useRoute();
const searchQuery = ref('');

function onItemClick() {
  uiStore.closeMoreMenu();
}

const allGroups = [
  {
    title: 'SALES & POS',
    icon: '🛒',
    items: [
      { name: 'New Sale / POS', path: '/admin/pos', icon: '🛒', badge: 'POS', permission: 'pos.billing' },
      { name: 'Sales Invoices', path: '/admin/sales', icon: '🧾', permission: 'sales.view|pos.billing' },
      { name: 'Sales Returns', path: '/admin/sales-returns', icon: '↩️', permission: 'sales_returns.view|pos.returns' },
      { name: 'Exchanges', path: '/admin/exchanges', icon: '🔄', permission: 'exchanges.view|pos.exchanges' },
    ]
  },
  {
    title: 'INVENTORY CONTROL',
    icon: '📦',
    items: [
      { name: 'Stock Overview', path: '/admin/inventory/stock', icon: '📦', permission: 'inventory.view' },
      { name: 'Stock Movements', path: '/admin/inventory/movements', icon: '📈', permission: 'inventory.view' },
      { name: 'Stock Adjustments', path: '/admin/inventory/adjustments', icon: '⚙️', permission: 'inventory.adjust' },
      { name: 'Stock Transfers', path: '/admin/inventory/transfers', icon: '🚚', permission: 'inventory.transfer' },
      { name: 'Low Stock Alerts', path: '/admin/inventory/low-stock', icon: '⚠️', permission: 'inventory.view' },
      { name: 'Stock Out – Damage', path: '/admin/stock-damage', icon: '🗑️', permission: 'inventory.view' },
    ]
  },
  {
    title: 'PURCHASES & PROCUREMENT',
    icon: '📝',
    items: [
      { name: 'Purchase Orders', path: '/admin/purchase-orders', icon: '📝', permission: 'procurement.view' },
      { name: 'Goods Receive (GRN)', path: '/admin/grn', icon: '📥', permission: 'procurement.receive' },
      { name: 'Purchase Bills', path: '/admin/purchase-bills', icon: '📑', permission: 'procurement.view' },
      { name: 'Purchase Returns', path: '/admin/purchase-returns', icon: '↩️', permission: 'procurement.view' },
    ]
  },
  {
    title: 'PAYMENTS & CASH DRAWER',
    icon: '💳',
    items: [
      { name: 'Payment Collections', path: '/admin/payments/collections', icon: '💳', permission: 'pos.billing' },
      { name: 'Refunds', path: '/admin/payments/refunds', icon: '💸', permission: 'pos.returns' },
      { name: 'Cash Drawer Status', path: '/admin/payments/cash-drawer', icon: '💵', permission: 'pos.sessions' },
      { name: 'Day Closing', path: '/admin/payments/day-closing', icon: '🔒', permission: 'pos.sessions' },
    ]
  },
  {
    title: 'MASTER DATA & CRM',
    icon: '👥',
    items: [
      { name: 'Products Directory', path: '/admin/products', icon: '👟', permission: 'products.view' },
      { name: 'Categories', path: '/admin/categories', icon: '🏷️', permission: 'products.view' },
      { name: 'Brands', path: '/admin/brands', icon: '🏅', permission: 'products.view' },
      { name: 'Sizes Matrix', path: '/admin/sizes', icon: '📏', permission: 'products.view' },
      { name: 'Colors Palette', path: '/admin/colors', icon: '🎨', permission: 'products.view' },
      { name: 'Customers Directory', path: '/admin/customers', icon: '👥', permission: 'customers.view' },
      { name: 'Suppliers Directory', path: '/admin/suppliers', icon: '🏢', permission: 'suppliers.view' },
    ]
  },
  {
    title: 'REPORTS ▼',
    icon: '📊',
    items: [
      { name: 'Sales Reports', path: '/admin/reports/sales', icon: '📊', permission: 'reports.view' },
      { name: 'Inventory Reports', path: '/admin/reports/inventory', icon: '📦', permission: 'reports.view' },
      { name: 'Purchase Reports', path: '/admin/reports/purchases', icon: '📄', permission: 'reports.view' },
      { name: 'Customer Reports', path: '/admin/reports/customers', icon: '👥', permission: 'reports.view' },
      { name: 'Payment Reports', path: '/admin/reports/payments', icon: '💳', permission: 'reports.view' },
      { name: 'Profit & Margin', path: '/admin/reports/profit-margin', icon: '📈', permission: 'reports.view' },
    ]
  },
  {
    title: 'STORE MANAGEMENT',
    icon: '🏬',
    items: [
      { name: 'Stores List', path: '/admin/stores', icon: '🏬', permission: 'stores.manage' },
      { name: 'Store Performance', path: '/admin/stores/performance', icon: '🎯', permission: 'reports.view' },
    ]
  },
  {
    title: 'ACCESS CONTROL & AUDIT',
    icon: '🛡️',
    items: [
      { name: 'Users List', path: '/admin/users', icon: '🔑', permission: 'users.manage' },
      { name: 'Roles & Permissions', path: '/admin/roles', icon: '🛡️', permission: 'roles.manage' },
      { name: 'Store Access Matrix', path: '/admin/store-access', icon: '🔒', permission: 'users.manage' },
      { name: 'System Audit Log', path: '/admin/audit', icon: '📜', permission: 'audit.view' },
    ]
  },
  {
    title: 'SYSTEM SETTINGS',
    icon: '⚙️',
    items: [
      { name: 'Company Profile', path: '/admin/settings/company', icon: '🏢', permission: 'system.settings' },
      { name: 'Invoice Settings', path: '/admin/settings/invoices', icon: '🧾', permission: 'system.settings' },
      { name: 'Tax Settings', path: '/admin/settings/tax', icon: '📑', permission: 'system.settings' },
      { name: 'Payment Methods', path: '/admin/settings/payment-methods', icon: '💳', permission: 'system.settings' },
      { name: 'POS Settings', path: '/admin/settings/pos', icon: '⚙️', permission: 'system.settings' },
      { name: 'Stock Settings', path: '/admin/settings/stock', icon: '📦', permission: 'system.settings' },
      { name: 'Printer Settings', path: '/admin/settings/printers', icon: '🖨️', permission: 'system.settings' },
      { name: 'Number Series', path: '/admin/settings/number-series', icon: '🔢', permission: 'system.settings' },
      { name: 'General Settings', path: '/admin/settings/general', icon: '🔧', permission: 'system.settings' },
      { name: 'Module Settings', path: '/admin/settings/modules', icon: '🧩', permission: 'system.settings' },
    ]
  },
];

const visibleGroups = computed(() => {
  return allGroups
    .map(g => ({
      ...g,
      items: g.items.filter(item => isSuperAdmin.value || !item.permission || hasPermission(item.permission))
    }))
    .filter(g => g.items.length > 0);
});

const filteredGroups = computed(() => {
  const query = searchQuery.value.trim().toLowerCase();
  if (!query) return visibleGroups.value;

  return visibleGroups.value
    .map(g => ({
      ...g,
      items: g.items.filter(i => i.name.toLowerCase().includes(query) || g.title.toLowerCase().includes(query))
    }))
    .filter(g => g.items.length > 0);
});
</script>

<style scoped>
.safe-pb {
  padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 2rem);
}
</style>
