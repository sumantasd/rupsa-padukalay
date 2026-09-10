<template>
  <nav class="lg:hidden fixed bottom-0 left-0 right-0 z-[100] bg-slate-950/95 backdrop-blur-md border-t border-slate-800 text-slate-400 font-sans shadow-2xl safe-pb">
    <div class="max-w-md mx-auto px-2 h-16 flex items-center justify-between relative">
      <!-- 1. HOME -->
      <RouterLink
        to="/admin/dashboard"
        :class="[
          'flex-1 flex flex-col items-center justify-center py-1 transition-colors',
          isRouteActive('/admin/dashboard') ? 'text-red-500 font-bold' : 'hover:text-slate-200'
        ]"
      >
        <span class="text-lg">🏠</span>
        <span class="text-[10px] tracking-tight mt-0.5">Home</span>
      </RouterLink>

      <!-- 2. STOCK -->
      <RouterLink
        to="/admin/inventory/stock"
        :class="[
          'flex-1 flex flex-col items-center justify-center py-1 transition-colors relative',
          isRouteActive('/admin/inventory') ? 'text-red-500 font-bold' : 'hover:text-slate-200'
        ]"
      >
        <span class="text-lg">📦</span>
        <span class="text-[10px] tracking-tight mt-0.5">Stock</span>
        <span v-if="notificationStore.unreadCount > 0" class="absolute top-1 right-3 w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
      </RouterLink>

      <!-- 3. PROMINENT CENTER POS BUTTON (EXACT CENTER) -->
      <div class="flex-1 flex flex-col items-center justify-center relative -top-3">
        <RouterLink
          to="/admin/pos"
          class="w-14 h-14 rounded-full bg-gradient-to-tr from-red-700 via-red-600 to-rose-500 text-white flex flex-col items-center justify-center shadow-lg shadow-red-600/40 border-4 border-slate-950 active:scale-95 transition-transform"
        >
          <span class="text-xl leading-none">🛒</span>
          <span class="text-[9px] font-black tracking-wider uppercase leading-none mt-0.5">POS</span>
        </RouterLink>
      </div>

      <!-- 4. REPORTS -->
      <RouterLink
        to="/admin/reports/sales"
        :class="[
          'flex-1 flex flex-col items-center justify-center py-1 transition-colors',
          isRouteActive('/admin/reports') ? 'text-red-500 font-bold' : 'hover:text-slate-200'
        ]"
      >
        <span class="text-lg">📊</span>
        <span class="text-[10px] tracking-tight mt-0.5">Reports</span>
      </RouterLink>

      <!-- 5. MORE (OPENS FULL RBAC DRAWER) -->
      <button
        type="button"
        @click="uiStore.openMoreMenu()"
        :class="[
          'flex-1 flex flex-col items-center justify-center py-1 transition-colors cursor-pointer',
          uiStore.isMoreMenuOpen ? 'text-red-500 font-bold' : 'hover:text-slate-200'
        ]"
      >
        <span class="text-lg">☰</span>
        <span class="text-[10px] tracking-tight mt-0.5">More</span>
      </button>
    </div>
  </nav>
</template>

<script setup>
import { useRoute } from 'vue-router';
import { useUiStore } from '../../stores/uiStore';
import { useNotificationStore } from '../../stores/notificationStore';

const route = useRoute();
const uiStore = useUiStore();
const notificationStore = useNotificationStore();

function isRouteActive(basePath) {
  if (basePath === '/admin/dashboard') {
    return route.path === '/admin/dashboard' || route.path === '/admin';
  }
  return route.path.startsWith(basePath);
}
</script>

<style scoped>
.safe-pb {
  padding-bottom: env(safe-area-inset-bottom, 0px);
}
</style>
