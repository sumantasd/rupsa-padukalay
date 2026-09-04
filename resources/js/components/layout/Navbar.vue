<template>
  <header class="fixed top-0 left-0 right-0 lg:left-72 z-[200] min-h-[56px] sm:min-h-[64px] border-b border-slate-200/90 dark:border-slate-800 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md flex items-center justify-between px-3 sm:px-6 font-sans shadow-xs safe-pt">
    <!-- Left Section: Back / Hamburger Toggle, Logo & Dynamic Page Title -->
    <div class="flex items-center gap-2 sm:gap-3 shrink-0">
      <!-- Back Button for Child Mobile Pages OR Hamburger Toggle -->
      <button
        v-if="canGoBack"
        type="button"
        @click="goBack"
        class="p-2 rounded-xl text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors touch-target flex items-center justify-center lg:hidden"
        title="Go Back"
      >
        <span class="text-lg font-black">←</span>
      </button>

      <button
        v-else
        type="button"
        @click="uiStore.toggleSidebar()"
        class="p-2 rounded-xl text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors touch-target flex items-center justify-center"
        title="Toggle Menu"
      >
        <svg class="h-6 w-6 sm:h-5 sm:w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
      </button>

      <!-- Brand Logo / Mobile Page Title -->
      <div class="flex items-center gap-2">
        <div class="h-8 w-8 rounded-xl bg-gradient-to-tr from-red-700 to-rose-500 flex items-center justify-center text-white font-black text-sm shadow-md shadow-red-600/20 shrink-0">
          <span>👟</span>
        </div>
        <div class="flex flex-col">
          <h1 class="font-black text-xs sm:text-sm text-slate-900 dark:text-white leading-tight truncate max-w-[150px] sm:max-w-[240px]">
            {{ currentPageTitle }}
          </h1>
          <span class="text-[9px] font-extrabold text-red-600 tracking-wider uppercase">RUPSA ERP</span>
        </div>
      </div>

      <!-- Single-Store System Static Outlet Label -->
      <div class="hidden md:flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-800 dark:text-slate-200 shrink-0">
        <span class="text-sm">🏬</span>
        <span class="truncate max-w-[180px]">Main Outlet (STR-001)</span>
      </div>
    </div>

    <!-- Right Section: Notifications, Dark Mode, User Profile & Logout -->
    <div class="flex items-center gap-2 sm:gap-4 relative">
      <!-- NOTIFICATIONS BELL & DROPDOWN PANEL -->
      <div class="relative" ref="notificationDropdownRef">
        <button
          type="button"
          @click="toggleNotificationPanel"
          class="relative p-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors"
          title="Stock Alerts & Notifications"
        >
          <span class="text-base">🔔</span>
          <span
            v-if="notificationStore.unreadCount > 0"
            class="absolute top-1 right-1 px-1.5 py-0.5 rounded-full bg-red-600 text-white text-[9px] font-black flex items-center justify-center animate-pulse min-w-[18px]"
          >
            {{ notificationStore.unreadCount > 99 ? '99+' : notificationStore.unreadCount }}
          </span>
        </button>

        <!-- Notification Panel Dropdown -->
        <div v-if="showNotifications">
          <!-- Backdrop -->
          <div
            @click="showNotifications = false"
            class="fixed inset-0 bg-slate-950/40 backdrop-blur-xs z-[240]"
          ></div>

          <!-- Drawer Container -->
          <div
            class="fixed inset-x-2 top-16 sm:absolute sm:inset-auto sm:right-0 sm:top-12 sm:w-96 z-[250] max-w-[calc(100vw-1rem)] bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl shadow-2xl overflow-hidden font-sans text-xs flex flex-col max-h-[80vh]"
          >
            <!-- Panel Header -->
            <div class="px-4 py-3 bg-slate-900 text-white flex items-center justify-between shrink-0">
              <div class="flex items-center gap-2">
                <span class="font-black text-xs">🔔 Stock Alerts & Notifications</span>
                <span v-if="notificationStore.unreadCount > 0" class="px-2 py-0.5 rounded-full bg-red-600 text-[10px] font-black">
                  {{ notificationStore.unreadCount }} Unread
                </span>
              </div>
              <button
                v-if="notificationStore.unreadCount > 0"
                @click="markAllRead"
                class="text-[10px] font-bold text-slate-300 hover:text-white underline transition-colors"
              >
                Mark all read
              </button>
            </div>

            <!-- Notification Items List -->
            <div class="max-h-80 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800">
              <div v-if="notificationStore.loading" class="p-6 text-center text-slate-400 font-bold">
                Loading alerts...
              </div>

              <div v-else-if="notificationStore.notificationsList.length === 0" class="p-6 text-center text-slate-500 font-medium space-y-1">
                <div class="text-2xl">🎉</div>
                <div class="font-bold text-slate-800 dark:text-slate-200">No Unread Stock Alerts</div>
                <p class="text-[11px] text-slate-400">All footwear SKUs are currently above minimum stock thresholds.</p>
              </div>

              <div
                v-for="n in notificationStore.notificationsList"
                :key="n.id"
                @click="handleNotificationClick(n)"
                :class="[
                  'p-3.5 hover:bg-slate-50 dark:hover:bg-slate-800/60 cursor-pointer transition-colors flex items-start gap-3 relative',
                  !n.is_read ? 'bg-red-50/40 dark:bg-red-950/20' : ''
                ]"
              >
                <div
                  :class="[
                    'w-8 h-8 rounded-xl flex items-center justify-center text-sm font-bold shrink-0 mt-0.5',
                    n.notification_type === 'out_of_stock' ? 'bg-red-100 text-red-700 border border-red-200' : 'bg-amber-100 text-amber-800 border border-amber-200'
                  ]"
                >
                  {{ n.notification_type === 'out_of_stock' ? '🚨' : '⚠️' }}
                </div>

                <div class="flex-1 space-y-0.5 pr-2">
                  <div class="flex items-center justify-between">
                    <span :class="['font-black text-xs', n.notification_type === 'out_of_stock' ? 'text-red-600' : 'text-amber-700']">
                      {{ n.title }}
                    </span>
                    <span class="text-[9px] text-slate-400 font-semibold">{{ formatTimeAgo(n.created_at) }}</span>
                  </div>
                  <p class="text-slate-800 dark:text-slate-200 text-[11px] font-medium leading-snug">
                    {{ n.message }}
                  </p>
                  <div class="text-[10px] text-slate-500 font-mono font-bold mt-1">
                    Article: {{ n.article_number }} | {{ n.size_display }}
                  </div>
                </div>

                <span v-if="!n.is_read" class="w-2 h-2 rounded-full bg-red-600 absolute right-3 top-4"></span>
              </div>
            </div>

            <!-- Panel Footer -->
            <div class="p-3 bg-slate-50 dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 text-center shrink-0">
              <button
                @click="goToLowStockPage"
                class="w-full py-1.5 bg-slate-900 dark:bg-white text-white dark:text-slate-900 rounded-xl font-bold text-xs transition-colors"
              >
                View All Low Stock Alerts →
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Dark Mode Toggle -->
      <button
        type="button"
        @click="uiStore.toggleDarkMode()"
        class="p-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors"
        title="Toggle Dark Mode"
      >
        <span v-if="uiStore.isDarkMode" class="text-base">☀️</span>
        <span v-else class="text-base">🌙</span>
      </button>

      <!-- User Profile & Avatar -->
      <div class="flex items-center gap-3 border-l border-slate-200 pl-3">
        <div class="h-9 w-9 rounded-full bg-red-700 text-white flex items-center justify-center font-black text-xs shadow-md shadow-red-700/20">
          {{ userInitials }}
        </div>
        <div class="hidden sm:flex flex-col text-left">
          <span class="text-xs font-black text-slate-900 leading-tight">{{ user?.name || 'Admin User' }}</span>
          <span class="text-[10px] font-extrabold text-red-600 uppercase tracking-wider">
            {{ isSuperAdmin ? 'Super Admin' : (user?.roles?.[0]?.name || 'Staff') }}
          </span>
        </div>
        <button
          type="button"
          @click="handleLogout"
          class="px-3 py-1.5 text-xs font-bold rounded-xl bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 transition-colors"
        >
          Logout
        </button>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useUiStore } from '../../stores/uiStore';
import { useModuleStore } from '../../stores/moduleStore';
import { useNotificationStore } from '../../stores/notificationStore';
import { useAuth } from '../../composables/useAuth';
import { useRouter, useRoute } from 'vue-router';

const uiStore = useUiStore();
const moduleStore = useModuleStore();
const notificationStore = useNotificationStore();
const { user, isSuperAdmin, logout } = useAuth();
const router = useRouter();
const route = useRoute();

const showNotifications = ref(false);
const notificationDropdownRef = ref(null);

const canGoBack = computed(() => {
  const rootPaths = ['/admin', '/admin/dashboard', '/admin/pos'];
  return !rootPaths.includes(route.path);
});

function goBack() {
  if (window.history.length > 1) {
    router.back();
  } else {
    router.push('/admin/dashboard');
  }
}

const currentPageTitle = computed(() => {
  if (route.meta && route.meta.title) {
    return route.meta.title;
  }
  const path = route.path;
  if (path.includes('/dashboard')) return 'Dashboard';
  if (path.includes('/pos')) return 'POS Billing';
  if (path.includes('/sales-returns')) return 'Sales Returns';
  if (path.includes('/sales')) return 'Sales Invoices';
  if (path.includes('/exchanges')) return 'Exchanges';
  if (path.includes('/inventory/stock')) return 'Stock Overview';
  if (path.includes('/inventory/movements')) return 'Stock Movements';
  if (path.includes('/inventory/adjustments')) return 'Stock Adjustments';
  if (path.includes('/inventory/transfers')) return 'Stock Transfers';
  if (path.includes('/inventory/low-stock')) return 'Low Stock Alerts';
  if (path.includes('/stock-damage')) return 'Stock Damage';
  if (path.includes('/purchase-orders')) return 'Purchase Orders';
  if (path.includes('/grn')) return 'Goods Receive';
  if (path.includes('/purchase-bills')) return 'Purchase Bills';
  if (path.includes('/purchase-returns')) return 'Purchase Returns';
  if (path.includes('/payments/collections')) return 'Payment Collections';
  if (path.includes('/payments/refunds')) return 'Payment Refunds';
  if (path.includes('/payments/cash-drawer')) return 'Cash Drawer';
  if (path.includes('/payments/day-closing')) return 'Day Closing';
  if (path.includes('/products')) return 'Products';
  if (path.includes('/categories')) return 'Categories';
  if (path.includes('/brands')) return 'Brands';
  if (path.includes('/sizes')) return 'Sizes Matrix';
  if (path.includes('/colors')) return 'Colors';
  if (path.includes('/customers')) return 'Customers';
  if (path.includes('/suppliers')) return 'Suppliers';
  if (path.includes('/reports/sales')) return 'Sales Report';
  if (path.includes('/reports/inventory')) return 'Inventory Report';
  if (path.includes('/reports/purchases')) return 'Purchase Report';
  if (path.includes('/reports/customers')) return 'Customer Report';
  if (path.includes('/reports/payments')) return 'Payment Report';
  if (path.includes('/reports/profit-margin')) return 'Profit & Margin';
  if (path.includes('/settings')) return 'Settings';
  return 'RUPSA ERP';
});

const userInitials = computed(() => {
  if (!user.value || !user.value.name) return 'AD';
  const parts = user.value.name.split(' ');
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
  return user.value.name.substring(0, 2).toUpperCase();
});

function toggleNotificationPanel() {
  showNotifications.value = !showNotifications.value;
  if (showNotifications.value) {
    notificationStore.fetchNotifications();
  }
}

async function markAllRead() {
  await notificationStore.markAllAsRead();
}

async function handleNotificationClick(n) {
  if (!n.is_read) {
    await notificationStore.markAsRead(n.id);
  }
  showNotifications.value = false;
  router.push('/admin/inventory/low-stock');
}

function goToLowStockPage() {
  showNotifications.value = false;
  router.push('/admin/inventory/low-stock');
}

function handleClickOutside(event) {
  if (notificationDropdownRef.value && !notificationDropdownRef.value.contains(event.target)) {
    showNotifications.value = false;
  }
}

function formatTimeAgo(dtStr) {
  if (!dtStr) return 'Just now';
  try {
    const d = new Date(dtStr);
    const diffSec = Math.floor((new Date() - d) / 1000);
    if (diffSec < 60) return 'Just now';
    if (diffSec < 3600) return `${Math.floor(diffSec / 60)}m ago`;
    if (diffSec < 86400) return `${Math.floor(diffSec / 3600)}h ago`;
    return `${Math.floor(diffSec / 86400)}d ago`;
  } catch (e) {
    return 'Recently';
  }
}

onMounted(() => {
  if (!moduleStore.initialized) {
    moduleStore.fetchSettings();
  }
  notificationStore.startPolling(30000);
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  notificationStore.stopPolling();
  document.removeEventListener('click', handleClickOutside);
});

async function handleLogout() {
  await logout();
  router.push('/admin/login');
}
</script>
