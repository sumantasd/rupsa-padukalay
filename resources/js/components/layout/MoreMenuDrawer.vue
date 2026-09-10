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

      <!-- Scrollable Categorized Accordion List -->
      <div class="flex-1 overflow-y-auto p-4 space-y-3 pb-28 text-xs">
        <!-- Standalone Top-Level Navigation Items (e.g. Dashboard) -->
        <div v-for="item in filteredStandaloneItems" :key="item.name" class="space-y-1">
          <RouterLink
            :to="item.path"
            @click="onItemClick"
            :class="[
              'flex items-center justify-between px-3.5 py-3 rounded-xl font-black text-xs uppercase tracking-wider transition-all min-h-[46px]',
              isRouteActive(item.path)
                ? 'bg-red-600 text-white shadow-lg shadow-red-600/20'
                : 'bg-slate-50 text-slate-800 border border-slate-200/90 hover:bg-slate-100 active:bg-slate-200'
            ]"
          >
            <div class="flex items-center gap-2.5 min-w-0">
              <span class="text-base shrink-0">{{ item.icon }}</span>
              <span class="truncate">{{ item.name }}</span>
            </div>
            <span :class="isRouteActive(item.path) ? 'text-white' : 'text-slate-400'" class="text-xs">➔</span>
          </RouterLink>
        </div>

        <!-- Categorized Accordion Groups -->
        <div v-for="group in filteredGroups" :key="group.title" class="space-y-1">
          <!-- Accordion Header Button -->
          <button
            @click="toggleGroup(group.title)"
            type="button"
            :class="[
              'w-full flex items-center justify-between px-3.5 py-3 rounded-xl font-black text-xs uppercase tracking-wider transition-all select-none cursor-pointer border min-h-[46px]',
              isGroupExpanded(group.title)
                ? 'bg-slate-900 text-white border-slate-800 shadow-md'
                : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'
            ]"
          >
            <div class="flex items-center gap-2.5 min-w-0">
              <span class="text-base shrink-0">{{ group.icon || '📌' }}</span>
              <span class="truncate">{{ group.title }}</span>
            </div>
            <span class="text-xs font-bold text-slate-400 shrink-0 ml-1">
              {{ isGroupExpanded(group.title) ? '▾' : '▸' }}
            </span>
          </button>

          <!-- Accordion Submenu Items -->
          <div v-show="isGroupExpanded(group.title)" class="grid grid-cols-1 gap-1.5 pt-1 pl-2">
            <RouterLink
              v-for="item in group.items"
              :key="item.name"
              :to="item.path"
              @click="onItemClick"
              :class="[
                'flex items-center justify-between px-3.5 py-3 rounded-xl font-bold transition-all min-h-[44px]',
                isRouteActive(item.path)
                  ? 'bg-red-600 text-white shadow-lg shadow-red-600/20'
                  : 'bg-slate-50/80 border border-slate-200/90 text-slate-800 hover:bg-slate-100 active:bg-slate-200'
              ]"
            >
              <div class="flex items-center gap-3 min-w-0">
                <span class="text-base shrink-0">{{ item.icon }}</span>
                <span class="text-xs truncate font-bold">{{ item.name }}</span>
              </div>
              <div class="flex items-center gap-2 shrink-0">
                <span v-if="item.badge" class="px-1.5 py-0.5 text-[8px] font-black bg-emerald-500 text-slate-950 rounded uppercase">
                  {{ item.badge }}
                </span>
                <span :class="isRouteActive(item.path) ? 'text-white' : 'text-slate-400'" class="text-xs">➔</span>
              </div>
            </RouterLink>
          </div>
        </div>

        <div v-if="filteredStandaloneItems.length === 0 && filteredGroups.length === 0" class="p-8 text-center text-slate-400 font-bold text-xs">
          No matching modules found for "{{ searchQuery }}"
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue';
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
const searchQuery = ref('');
const expandedGroups = reactive({});

function toggleGroup(title) {
  const isCurrentlyOpen = !!expandedGroups[title];
  // Accordion behavior: close all groups, then toggle clicked group
  Object.keys(expandedGroups).forEach(k => {
    expandedGroups[k] = false;
  });
  expandedGroups[title] = !isCurrentlyOpen;
}

function onItemClick() {
  uiStore.closeMoreMenu();
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

const filteredStandaloneItems = computed(() => {
  const query = searchQuery.value.trim().toLowerCase();
  if (!query) return visibleStandaloneItems.value;
  return visibleStandaloneItems.value.filter(i => i.name.toLowerCase().includes(query));
});

const visibleGroups = computed(() => {
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

function isGroupExpanded(title) {
  if (searchQuery.value.trim().length > 0) {
    return true; // Auto-expand matching groups when search text is present
  }
  return !!expandedGroups[title];
}

// Auto-expand group containing current route
watch(
  () => route.path,
  (newPath) => {
    let matchedTitle = null;
    visibleGroups.value.forEach(g => {
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


