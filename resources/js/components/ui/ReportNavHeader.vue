<template>
  <div class="space-y-3 mb-5 font-sans">
    <!-- Top Bar: Mobile App Header & Module Selector -->
    <div class="bg-slate-950 text-white rounded-2xl p-4 border border-slate-800 shadow-xl flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
      <!-- Title & Dropdown Switcher Button -->
      <div class="relative">
        <button
          @click="isDropdownOpen = !isDropdownOpen"
          class="w-full sm:w-auto flex items-center justify-between sm:justify-start gap-2 px-3 py-2 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 active:scale-98 transition-all cursor-pointer"
        >
          <div class="flex items-center gap-2">
            <div class="h-7 w-7 rounded-lg bg-red-600 flex items-center justify-center text-white font-black text-xs shadow-md shadow-red-600/30">
              {{ currentReportIcon }}
            </div>
            <div class="text-left">
              <div class="flex items-center gap-1">
                <span class="font-black text-xs tracking-wider uppercase text-white">REPORTS</span>
                <span class="text-[10px] text-red-500 font-bold transition-transform" :class="{ 'rotate-180': isDropdownOpen }">▼</span>
              </div>
              <p class="text-[10px] font-extrabold text-slate-400 truncate max-w-[180px] sm:max-w-xs">
                {{ currentReportTitle }}
              </p>
            </div>
          </div>
        </button>

        <!-- Dropdown Menu for Reports -->
        <Teleport to="body">
          <div
            v-if="isDropdownOpen"
            @click="isDropdownOpen = false"
            class="fixed inset-0 z-[400] bg-slate-950/60 backdrop-blur-xs"
          ></div>
          <div
            v-if="isDropdownOpen"
            class="fixed top-20 left-4 right-4 sm:left-auto sm:right-auto sm:w-80 z-[401] bg-slate-950 border border-slate-800 rounded-2xl p-2 shadow-2xl space-y-1 font-sans animate-in fade-in zoom-in-95 duration-150"
          >
            <div class="px-3 py-2 border-b border-slate-800 flex items-center justify-between text-[10px] font-black uppercase text-red-500 tracking-wider">
              <span>SELECT REPORT MODULE</span>
              <button @click="isDropdownOpen = false" class="text-slate-400 hover:text-white font-black">✕</button>
            </div>

            <div class="space-y-1 pt-1">
              <RouterLink
                v-for="item in reportsList"
                :key="item.path"
                :to="item.path"
                @click="isDropdownOpen = false"
                :class="[
                  'flex items-center justify-between px-3.5 py-3 rounded-xl font-bold transition-all min-h-[48px]',
                  route.path === item.path
                    ? 'bg-red-600 text-white shadow-md shadow-red-600/30'
                    : 'bg-slate-900/80 text-slate-300 hover:bg-slate-800 hover:text-white border border-slate-850'
                ]"
              >
                <div class="flex items-center gap-3">
                  <span class="text-lg">{{ item.icon }}</span>
                  <span class="text-xs font-bold">{{ item.name }}</span>
                </div>
                <span v-if="route.path === item.path" class="text-xs text-white">✓</span>
              </RouterLink>
            </div>
          </div>
        </Teleport>
      </div>

      <!-- Action Buttons: View PDF, Download PDF, Open Bottom Sheet Filter & Refresh -->
      <div class="flex items-center justify-end gap-1.5 sm:gap-2 flex-wrap">
        <button
          v-if="showExportBtn"
          @click="$emit('view-pdf')"
          class="flex items-center gap-1 px-2.5 py-2 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 active:scale-95 text-slate-200 hover:text-white font-extrabold text-[11px] transition-all cursor-pointer min-h-[38px]"
          title="Open PDF Document in Browser"
        >
          <span>📄</span>
          <span class="uppercase tracking-wider text-[10px]">View PDF</span>
        </button>

        <button
          v-if="showExportBtn"
          @click="$emit('download-pdf')"
          class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-red-600 hover:bg-red-700 active:scale-95 text-white font-black text-[11px] shadow-md shadow-red-600/30 transition-all cursor-pointer min-h-[38px]"
          title="Download PDF File"
        >
          <span>⬇️</span>
          <span class="uppercase tracking-wider text-[10px]">EXPORT PDF</span>
        </button>

        <button
          v-if="showFilterBtn"
          @click="$emit('open-filter')"
          class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-red-600 hover:bg-red-700 active:scale-95 text-white font-black text-xs shadow-md shadow-red-600/30 transition-all cursor-pointer min-h-[38px]"
        >
          <span>🔍</span>
          <span class="uppercase tracking-wider text-[10px]">Filters</span>
          <span v-if="activeFilterCount > 0" class="ml-1 px-1.5 py-0.5 rounded-full bg-white text-red-700 text-[9px] font-black">
            {{ activeFilterCount }}
          </span>
        </button>

        <button
          @click="$emit('refresh')"
          class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-300 hover:text-white flex items-center justify-center font-bold text-sm cursor-pointer active:scale-95 transition-all"
          title="Refresh Data"
        >
          🔄
        </button>
      </div>
    </div>

    <!-- Quick Module Navigation Tabs (Desktop & Tablet horizontal pill bar) -->
    <div class="hidden sm:flex items-center gap-1.5 p-1.5 bg-slate-950/90 rounded-2xl border border-slate-800 overflow-x-auto text-xs shrink-0 no-scrollbar">
      <RouterLink
        v-for="item in reportsList"
        :key="item.path"
        :to="item.path"
        :class="[
          'flex items-center gap-2 px-3.5 py-2 rounded-xl font-bold whitespace-nowrap transition-all min-h-[40px]',
          route.path === item.path
            ? 'bg-red-600 text-white shadow-md shadow-red-600/30'
            : 'text-slate-400 hover:text-white hover:bg-slate-900'
        ]"
      >
        <span>{{ item.icon }}</span>
        <span>{{ item.name }}</span>
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRoute } from 'vue-router';

const props = defineProps({
  showFilterBtn: { type: Boolean, default: true },
  showExportBtn: { type: Boolean, default: true },
  activeFilterCount: { type: Number, default: 0 },
});

defineEmits(['open-filter', 'refresh', 'view-pdf', 'download-pdf']);

const route = useRoute();
const isDropdownOpen = ref(false);

const reportsList = [
  { name: 'Sales Reports', path: '/admin/reports/sales', icon: '📊' },
  { name: 'Inventory Reports', path: '/admin/reports/inventory', icon: '📦' },
  { name: 'Purchase Reports', path: '/admin/reports/purchases', icon: '🧾' },
  { name: 'Customer Reports', path: '/admin/reports/customers', icon: '👥' },
  { name: 'Payment Reports', path: '/admin/reports/payments', icon: '💳' },
  { name: 'Profit & Margin', path: '/admin/reports/profit-margin', icon: '📈' },
];

const currentReportTitle = computed(() => {
  const current = reportsList.find(r => r.path === route.path);
  return current ? current.name : 'Reports';
});

const currentReportIcon = computed(() => {
  const current = reportsList.find(r => r.path === route.path);
  return current ? current.icon : '📊';
});
</script>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
