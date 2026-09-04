<template>
  <div class="min-h-screen mobile-min-dvh max-w-full bg-slate-100 dark:bg-slate-950 text-slate-900 dark:text-slate-100 font-sans antialiased flex flex-col overflow-x-hidden pb-24 lg:pb-0 safe-pb">
    <!-- Mobile Sidebar Backdrop Overlay -->
    <div
      v-if="uiStore.isSidebarOpen"
      @click="uiStore.closeSidebar()"
      class="fixed inset-0 z-[300] bg-slate-950/60 backdrop-blur-xs lg:hidden transition-opacity"
    ></div>

    <!-- Sidebar Component (Desktop & Drawer) -->
    <Sidebar />

    <!-- Mobile Full-Screen More Menu Drawer -->
    <MoreMenuDrawer />

    <!-- Main Area (Padded left on desktop by 288px sidebar width & top padded for fixed header) -->
    <div class="flex-1 flex flex-col min-w-0 lg:pl-72 pt-16 sm:pt-20 lg:pt-16 transition-all duration-200 max-w-full">
      <!-- Top Header Navbar (Fixed top-0) -->
      <Navbar />

      <!-- Main Page Content View -->
      <main class="flex-1 p-3 sm:p-5 md:p-6 max-w-full">
        <div class="max-w-7xl mx-auto w-full">
          <Breadcrumbs />
          <RouterView />
        </div>
      </main>

      <!-- Admin ERP Footer Bar with Tech Googly Developer Credit -->
      <footer class="bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 py-3 px-6 text-xs text-slate-500 flex flex-col sm:flex-row items-center justify-between gap-2 shrink-0">
        <div class="flex items-center gap-3">
          <span class="font-black text-slate-700 dark:text-slate-200">RUPSA ERP v1.0</span>
          <span class="inline-flex items-center gap-1 text-[11px] text-emerald-600 font-bold">
            <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>API Online</span>
          </span>
        </div>
        <div class="text-[11px] font-medium text-slate-500">
          <span>Developed By </span>
          <a href="https://techgoogly.com" target="_blank" rel="noopener" class="font-bold text-red-600 hover:text-red-700 hover:underline">Tech Googly</a>
        </div>
      </footer>
    </div>

    <!-- Fixed Mobile Bottom Navigation (POS Prominently Centered) -->
    <MobileBottomNav />

    <!-- Toast Notification Container -->
    <ToastContainer />
  </div>
</template>

<script setup>
import { watch } from 'vue';
import { useRoute } from 'vue-router';
import Sidebar from '../components/layout/Sidebar.vue';
import Navbar from '../components/layout/Navbar.vue';
import Breadcrumbs from '../components/layout/Breadcrumbs.vue';
import MobileBottomNav from '../components/layout/MobileBottomNav.vue';
import MoreMenuDrawer from '../components/layout/MoreMenuDrawer.vue';
import ToastContainer from '../components/ui/ToastContainer.vue';
import { useUiStore } from '../stores/uiStore';

const uiStore = useUiStore();
const route = useRoute();

// Automatically close all drawers whenever route changes on mobile
watch(() => route.path, () => {
  uiStore.closeAllDrawers();
});

// Lock/unlock body scroll when mobile drawer is open
watch(
  [() => uiStore.isSidebarOpen, () => uiStore.isMoreMenuOpen],
  ([sidebarOpen, moreOpen]) => {
    if (typeof window !== 'undefined' && window.innerWidth < 1024) {
      if (sidebarOpen || moreOpen) {
        document.body.style.overflow = 'hidden';
      } else {
        document.body.style.overflow = '';
      }
    }
  }
);
</script>
