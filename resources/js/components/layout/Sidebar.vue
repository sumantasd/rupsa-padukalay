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

    <!-- Navigation Menu -->
    <nav class="flex-1 px-3 py-4 overflow-y-auto space-y-2 text-xs">
      <!-- Standalone Top-Level Items (Dashboard) -->
      <div v-for="item in visibleStandaloneItems" :key="item.name" class="mb-1">
        <RouterLink
          :to="item.path"
          @click="handleNavClick"
          :class="[
            'flex items-center gap-2.5 px-3 py-2.5 rounded-xl font-black uppercase text-xs tracking-wider transition-all min-h-[44px]',
            isRouteActive(item.path)
              ? 'bg-red-600 text-white shadow-md shadow-red-600/20'
              : 'text-slate-400 hover:bg-slate-900 hover:text-white'
          ]"
        >
          <span class="text-sm shrink-0">{{ item.icon }}</span>
          <span class="truncate">{{ item.name }}</span>
        </RouterLink>
      </div>

      <!-- Categorized Collapsible Accordion Groups -->
      <div v-for="group in visibleMenuGroups" :key="group.title" class="space-y-1">
        <!-- Section Header Button (Accordion Toggle) -->
        <button
          @click="toggleGroup(group.title)"
          type="button"
          :class="[
            'w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition-all select-none cursor-pointer border min-h-[44px]',
            expandedGroups[group.title]
              ? 'bg-slate-900 text-white border-slate-700/80 shadow-inner'
              : 'bg-transparent text-slate-400 border-transparent hover:bg-slate-900/60 hover:text-slate-200'
          ]"
        >
          <div class="flex items-center gap-2.5 min-w-0">
            <span class="text-sm shrink-0">{{ group.icon || '📌' }}</span>
            <span class="truncate">{{ group.title }}</span>
          </div>
          <span class="text-xs font-bold text-slate-500 transition-transform duration-200 shrink-0 ml-1">
            {{ expandedGroups[group.title] ? '▾' : '▸' }}
          </span>
        </button>

        <!-- Submenu Items -->
        <div v-show="expandedGroups[group.title]" class="space-y-1 pt-1 pl-2 border-l border-slate-800/80 ml-3">
          <RouterLink
            v-for="item in group.items"
            :key="item.name"
            :to="item.path"
            @click="handleNavClick"
            :class="[
              'flex items-center gap-2.5 px-3 py-2 rounded-xl font-bold transition-all min-h-[40px]',
              isRouteActive(item.path)
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
import { standaloneItems, menuGroups } from '../../config/menuConfig';

import { useModuleStore } from '../../stores/moduleStore';

const uiStore = useUiStore();
const companyStore = useCompanyStore();
const moduleStore = useModuleStore();
const { hasPermission, isSuperAdmin } = useAuth();
const route = useRoute();

const expandedGroups = reactive({});

function toggleGroup(title) {
  const isCurrentlyOpen = !!expandedGroups[title];
  // Accordion behavior: close all groups, then toggle clicked group
  Object.keys(expandedGroups).forEach(k => {
    expandedGroups[k] = false;
  });
  expandedGroups[title] = !isCurrentlyOpen;
}

function handleNavClick() {
  if (window.innerWidth < 1024) {
    uiStore.closeSidebar();
  }
}

function isRouteActive(path) {
  if (path === '/admin/dashboard') {
    return route.path === '/admin/dashboard' || route.path === '/admin' || route.path === '/admin/';
  }
  return route.path === path || (path !== '/admin' && route.path.startsWith(path + '/'));
}

const visibleStandaloneItems = computed(() => {
  return standaloneItems.filter(item => isSuperAdmin.value || !item.permission || hasPermission(item.permission));
});

const visibleMenuGroups = computed(() => {
  return menuGroups
    .map(g => ({
      ...g,
      items: g.items.filter(item => {
        const hasPerm = isSuperAdmin.value || !item.permission || hasPermission(item.permission);
        const hasModule = !item.moduleKey || moduleStore[item.moduleKey] === true;
        return hasPerm && hasModule;
      })
    }))
    .filter(g => g.items.length > 0);
});

// Auto-expand group containing current route
watch(
  () => route.path,
  (newPath) => {
    let matchedTitle = null;
    visibleMenuGroups.value.forEach(g => {
      if (g.items.some(item => isRouteActive(item.path))) {
        matchedTitle = g.title;
      }
    });

    Object.keys(expandedGroups).forEach(k => {
      expandedGroups[k] = false;
    });

    if (matchedTitle) {
      expandedGroups[matchedTitle] = true;
    }
  },
  { immediate: true }
);
</script>

<style scoped>
.safe-pb {
  padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 2rem);
}
</style>
