<template>
  <header class="fixed top-0 left-0 right-0 lg:left-72 z-[200] min-h-[56px] sm:min-h-[64px] border-b border-slate-200/90 dark:border-slate-800 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md flex items-center justify-between px-2 sm:px-4 lg:px-6 font-sans shadow-xs safe-pt overflow-x-hidden">
    <!-- Left Section: Back / Hamburger Toggle, Logo & Dynamic Page Title -->
    <div class="flex items-center gap-1.5 sm:gap-2.5 min-w-0">
      <!-- Back Button for Child Mobile Pages OR Hamburger Toggle -->
      <button
        v-if="canGoBack"
        type="button"
        @click="goBack"
        class="p-2 rounded-xl text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors touch-target flex items-center justify-center lg:hidden shrink-0"
        title="Go Back"
      >
        <span class="text-base sm:text-lg font-black">←</span>
      </button>

      <button
        v-else
        type="button"
        @click="handleMenuToggle"
        class="p-2 rounded-xl text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors touch-target flex items-center justify-center shrink-0"
        title="Toggle Menu"
      >
        <svg class="h-5 w-5 sm:h-5 sm:w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
      </button>

      <!-- Brand Logo -->
      <div v-if="companyStore.primaryLogo" class="h-7 sm:h-8 flex items-center shrink-0">
        <img
          :src="companyStore.primaryLogo"
          :alt="companyStore.companyName"
          class="max-h-7 sm:max-h-8 w-auto object-contain max-w-[70px] xs:max-w-[100px] sm:max-w-[140px]"
        />
      </div>
      <div v-else class="h-7 w-7 sm:h-8 sm:w-8 rounded-xl bg-gradient-to-tr from-red-700 to-rose-500 flex items-center justify-center text-white font-black text-xs sm:text-sm shadow-md shadow-red-600/20 shrink-0">
        <span>👟</span>
      </div>

      <!-- Page Title (Shows ONLY "Dashboard" on dashboard route) -->
      <div class="flex items-center min-w-0">
        <h1 class="font-black text-xs sm:text-sm text-slate-900 dark:text-white leading-tight truncate max-w-[85px] xs:max-w-[120px] sm:max-w-[220px]">
          {{ currentPageTitle }}
        </h1>
      </div>

      <!-- Single-Store System Outlet Label (Visible on Medium+ screens) -->
      <div class="hidden lg:flex items-center gap-1.5 px-2.5 py-1 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-800 dark:text-slate-200 shrink-0 ml-1">
        <span class="text-xs">🏬</span>
        <span class="truncate max-w-[140px]">STR-001</span>
      </div>
    </div>

    <!-- Right Section: Notifications, Dark Mode, Super Admin Account & Logout -->
    <div class="flex items-center gap-1 sm:gap-2.5 shrink-0">
      <!-- 1. NOTIFICATION BELL BUTTON -->
      <button
        type="button"
        @click="toggleNotificationPanel"
        class="relative p-2 rounded-xl text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer shrink-0"
        title="Stock Alerts & Notifications"
      >
        <span class="text-base sm:text-lg">🔔</span>
        <span
          v-if="notificationStore.unreadCount > 0"
          class="absolute top-1 right-1 px-1 py-0.2 rounded-full bg-red-600 text-white text-[8px] sm:text-[9px] font-black flex items-center justify-center animate-pulse min-w-[16px]"
        >
          {{ notificationStore.unreadCount > 99 ? '99+' : notificationStore.unreadCount }}
        </span>
      </button>

      <!-- 2. THEME CONTROL BUTTON -->
      <button
        type="button"
        @click="uiStore.toggleDarkMode()"
        class="p-2 rounded-xl text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer shrink-0"
        :title="uiStore.isDarkMode ? 'Switch to Light Theme' : 'Switch to Dark Theme'"
      >
        <span v-if="uiStore.isDarkMode" class="text-base sm:text-lg">☀️</span>
        <span v-else class="text-base sm:text-lg">🌙</span>
      </button>

      <!-- 3. SUPER ADMIN ACCOUNT CONTROL BUTTON -->
      <div class="flex items-center gap-2 border-l border-slate-200 dark:border-slate-800 pl-1.5 sm:pl-2.5">
        <button
          type="button"
          @click="toggleAccountMenu"
          class="flex items-center gap-2 p-1 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
          title="Account Menu"
        >
          <div class="h-8 w-8 sm:h-9 sm:w-9 rounded-full bg-red-700 text-white flex items-center justify-center font-black text-xs shadow-md shadow-red-700/20 shrink-0">
            {{ userInitials }}
          </div>
          <div class="hidden sm:flex flex-col text-left">
            <span class="text-xs font-black text-slate-900 dark:text-white leading-tight truncate max-w-[100px]">
              {{ user?.name || 'Super Admin' }}
            </span>
            <span class="text-[9px] font-extrabold text-red-600 uppercase tracking-wider">
              {{ isSuperAdmin ? 'Super Admin' : (user?.roles?.[0]?.name || 'Staff') }}
            </span>
          </div>
        </button>

        <!-- Standalone Desktop Logout Button -->
        <button
          type="button"
          @click="handleLogout"
          class="hidden md:flex px-2.5 py-1.5 text-xs font-bold rounded-xl bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/60 border border-red-200 dark:border-red-800/60 transition-colors shrink-0"
        >
          Logout
        </button>
      </div>
    </div>
  </header>

  <!-- TELEPORTED NOTIFICATION PANEL DROPDOWN -->
  <Teleport to="body">
    <div v-if="showNotifications">
      <!-- Backdrop -->
      <div
        @click="showNotifications = false"
        class="fixed inset-0 bg-slate-950/40 backdrop-blur-xs z-[340]"
      ></div>

      <!-- Drawer Container -->
      <div
        class="fixed right-2 sm:right-6 top-16 z-[350] w-80 sm:w-96 max-w-[calc(100vw-1rem)] bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl shadow-2xl overflow-hidden font-sans text-xs flex flex-col max-h-[80vh]"
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
  </Teleport>

  <!-- TELEPORTED SUPER ADMIN ACCOUNT MENU POPOVER -->
  <Teleport to="body">
    <div v-if="showAccountMenu">
      <div class="fixed inset-0 bg-transparent z-[340]" @click="showAccountMenu = false"></div>
      <div
        class="fixed right-2 sm:right-6 top-16 z-[350] w-64 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl p-2 font-sans text-xs space-y-1"
      >
        <!-- Account Info Header -->
        <div class="p-3 bg-slate-50 dark:bg-slate-800/80 rounded-xl mb-1 border border-slate-100 dark:border-slate-700/60">
          <div class="font-black text-slate-900 dark:text-white text-xs truncate">{{ user?.name || 'Super Admin' }}</div>
          <div class="text-[10px] font-bold text-slate-500 dark:text-slate-400 truncate">{{ user?.email }}</div>
          <div class="text-[9px] font-extrabold text-red-600 uppercase tracking-wider mt-1">
            {{ isSuperAdmin ? 'Super Admin' : (user?.roles?.[0]?.name || 'Staff') }}
          </div>
        </div>

        <!-- My Profile -->
        <button
          @click="openProfileModal"
          class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs text-left transition-colors cursor-pointer min-h-[44px]"
        >
          <span class="text-sm">👤</span>
          <span>My Profile</span>
        </button>

        <!-- Change Password -->
        <button
          @click="openPasswordModal"
          class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs text-left transition-colors cursor-pointer min-h-[44px]"
        >
          <span class="text-sm">🔑</span>
          <span>Change Password</span>
        </button>

        <div class="border-t border-slate-100 dark:border-slate-800 my-1"></div>

        <!-- Logout -->
        <button
          @click="handleLogout"
          class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl font-bold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 text-xs text-left transition-colors cursor-pointer min-h-[44px]"
        >
          <span class="text-sm">🚪</span>
          <span>Logout</span>
        </button>
      </div>
    </div>
  </Teleport>

  <!-- TELEPORTED MY PROFILE MODAL -->
  <Teleport to="body">
    <div v-if="showProfileModal" class="fixed inset-0 z-[500] bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl max-w-md w-full p-6 space-y-4 text-xs font-sans">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
          <div>
            <h3 class="font-black text-sm text-slate-900 dark:text-white">My Profile Details</h3>
            <p class="text-[11px] text-slate-500">Update your account name and email address</p>
          </div>
          <button @click="showProfileModal = false" class="text-slate-400 hover:text-slate-800 dark:hover:text-white font-black text-base">✕</button>
        </div>

        <div class="space-y-3">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Full Name</label>
            <input
              v-model="profileForm.name"
              type="text"
              class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-bold text-slate-900 dark:text-white focus:outline-none"
            />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Email Address</label>
            <input
              v-model="profileForm.email"
              type="email"
              class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-bold text-slate-900 dark:text-white focus:outline-none"
            />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Phone Number</label>
            <input
              v-model="profileForm.phone"
              type="text"
              placeholder="+91 9830012345"
              class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-bold text-slate-900 dark:text-white focus:outline-none"
            />
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
          <button @click="showProfileModal = false" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl font-bold">
            Cancel
          </button>
          <button
            @click="submitProfileUpdate"
            :disabled="savingProfile"
            class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black uppercase tracking-wider"
          >
            {{ savingProfile ? 'Saving...' : 'Save Profile' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- TELEPORTED CHANGE PASSWORD MODAL -->
  <Teleport to="body">
    <div v-if="showPasswordModal" class="fixed inset-0 z-[500] bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl max-w-md w-full p-6 space-y-4 text-xs font-sans">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
          <div>
            <h3 class="font-black text-sm text-slate-900 dark:text-white">Change Account Password</h3>
            <p class="text-[11px] text-slate-500">Securely update your admin login password</p>
          </div>
          <button @click="showPasswordModal = false" class="text-slate-400 hover:text-slate-800 dark:hover:text-white font-black text-base">✕</button>
        </div>

        <div class="space-y-3">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Current Password</label>
            <input
              v-model="passwordForm.current_password"
              type="password"
              class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-mono text-slate-900 dark:text-white focus:outline-none"
            />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">New Password (Min 8 Chars)</label>
            <input
              v-model="passwordForm.new_password"
              type="password"
              class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-mono text-slate-900 dark:text-white focus:outline-none"
            />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Confirm New Password</label>
            <input
              v-model="passwordForm.new_password_confirmation"
              type="password"
              class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-mono text-slate-900 dark:text-white focus:outline-none"
            />
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
          <button @click="showPasswordModal = false" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl font-bold">
            Cancel
          </button>
          <button
            @click="submitPasswordChange"
            :disabled="savingPassword"
            class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black uppercase tracking-wider"
          >
            {{ savingPassword ? 'Updating...' : 'Update Password' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import { useUiStore } from '../../stores/uiStore';
import { useModuleStore } from '../../stores/moduleStore';
import { useNotificationStore } from '../../stores/notificationStore';
import { useCompanyStore } from '../../stores/companyStore';
import { useAuth } from '../../composables/useAuth';
import { useRouter, useRoute } from 'vue-router';
import api from '../../services/api';

const uiStore = useUiStore();
const moduleStore = useModuleStore();
const notificationStore = useNotificationStore();
const companyStore = useCompanyStore();
const { user, isSuperAdmin, logout, setUser } = useAuth();
const router = useRouter();
const route = useRoute();

const showNotifications = ref(false);
const showAccountMenu = ref(false);

const showProfileModal = ref(false);
const savingProfile = ref(false);
const profileForm = reactive({
  name: '',
  email: '',
  phone: '',
});

const showPasswordModal = ref(false);
const savingPassword = ref(false);
const passwordForm = reactive({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
});

function handleMenuToggle() {
  if (window.innerWidth < 1024) {
    uiStore.toggleMoreMenu();
  } else {
    uiStore.toggleSidebar();
  }
}

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
  const path = route.path;
  if (path === '/admin' || path === '/admin/dashboard') return 'Dashboard';
  if (route.meta && route.meta.title) return route.meta.title;
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
  return 'Dashboard';
});

const userInitials = computed(() => {
  if (!user.value || !user.value.name) return 'SA';
  const parts = user.value.name.split(' ');
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
  return user.value.name.substring(0, 2).toUpperCase();
});

function toggleNotificationPanel() {
  showAccountMenu.value = false;
  showNotifications.value = !showNotifications.value;
  if (showNotifications.value) {
    notificationStore.fetchNotifications();
  }
}

function toggleAccountMenu() {
  showNotifications.value = false;
  showAccountMenu.value = !showAccountMenu.value;
}

function openProfileModal() {
  showAccountMenu.value = false;
  profileForm.name = user.value?.name || '';
  profileForm.email = user.value?.email || '';
  profileForm.phone = user.value?.phone || '';
  showProfileModal.value = true;
}

async function submitProfileUpdate() {
  savingProfile.value = true;
  try {
    const res = await api.put('/auth/profile', profileForm);
    const updatedUser = res.data?.data || res.data || res;
    if (updatedUser) {
      setUser(updatedUser);
    }
    alert('Profile details updated successfully!');
    showProfileModal.value = false;
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to update profile.');
  } finally {
    savingProfile.value = false;
  }
}

function openPasswordModal() {
  showAccountMenu.value = false;
  passwordForm.current_password = '';
  passwordForm.new_password = '';
  passwordForm.new_password_confirmation = '';
  showPasswordModal.value = true;
}

async function submitPasswordChange() {
  if (!passwordForm.current_password) {
    alert('Please enter your current password.');
    return;
  }
  if (!passwordForm.new_password || passwordForm.new_password.length < 8) {
    alert('New password must be at least 8 characters long.');
    return;
  }
  if (passwordForm.new_password !== passwordForm.new_password_confirmation) {
    alert('New password and confirmation do not match.');
    return;
  }

  savingPassword.value = true;
  try {
    await api.put('/auth/change-password', passwordForm);
    alert('Password updated successfully!');
    showPasswordModal.value = false;
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to change password.');
  } finally {
    savingPassword.value = false;
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
});

onUnmounted(() => {
  notificationStore.stopPolling();
});

async function handleLogout() {
  showAccountMenu.value = false;
  await logout();
  router.push('/admin/login');
}
</script>
