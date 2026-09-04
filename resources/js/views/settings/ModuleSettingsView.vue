<template>
  <div class="space-y-6 max-w-5xl mx-auto pb-12 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Module Settings</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Enable or disable optional ERP modules and system features. Configuration is persisted in database.
        </p>
      </div>
      <div class="flex items-center gap-2">
        <span
          :class="[
            'px-3 py-1.5 rounded-xl text-xs font-black flex items-center gap-1.5 border shadow-2xs',
            moduleStore.multiStore
              ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
              : 'bg-amber-50 text-amber-800 border-amber-200'
          ]"
        >
          <span class="h-2 w-2 rounded-full" :class="moduleStore.multiStore ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'"></span>
          <span>{{ moduleStore.multiStore ? 'Multi-Store Mode ON' : 'Single Store Mode (Default)' }}</span>
        </span>
      </div>
    </div>

    <!-- 1. CORE SYSTEM (Protected Toggles) -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
      <div class="px-5 py-3.5 bg-slate-900 text-white flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="text-base">🛡️</span>
          <h2 class="text-xs font-extrabold uppercase tracking-wider">Core System (Protected Security Core)</h2>
        </div>
        <span class="text-[10px] font-bold text-slate-400 bg-slate-800 px-2 py-0.5 rounded">Always Active</span>
      </div>
      <div class="p-5 divide-y divide-slate-100 text-xs">
        <div v-for="item in coreSystemItems" :key="item.name" class="py-3.5 first:pt-0 last:pb-0 flex items-center justify-between gap-4">
          <div class="space-y-0.5 max-w-xl">
            <div class="font-black text-slate-900 flex items-center gap-2">
              <span>{{ item.name }}</span>
              <span class="px-1.5 py-0.5 bg-slate-100 text-slate-600 rounded text-[9px] font-extrabold uppercase">Required</span>
            </div>
            <p class="text-slate-500 leading-relaxed">{{ item.description }}</p>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <span class="text-xs font-bold text-emerald-600">Active</span>
            <div class="w-10 h-6 bg-slate-300 rounded-full cursor-not-allowed opacity-60 relative p-1">
              <div class="w-4 h-4 bg-white rounded-full shadow-xs transform translate-x-4"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 2. OPTIONAL / CONFIGURABLE MODULES -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
      <div class="px-5 py-3.5 bg-red-600 text-white flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="text-base">⚙️</span>
          <h2 class="text-xs font-extrabold uppercase tracking-wider">Optional / Configurable ERP Modules</h2>
        </div>
        <span class="text-[10px] font-bold bg-red-700 px-2 py-0.5 rounded text-red-100">Dynamic UI Toggles</span>
      </div>

      <div class="p-5 space-y-6 divide-y divide-slate-100 text-xs">
        <!-- SINGLE STORE SYSTEM NOTICE -->
        <div class="pt-2 first:pt-0 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div class="space-y-1 max-w-xl">
            <div class="flex items-center gap-2">
              <span class="font-black text-slate-900 text-sm">Store Architecture</span>
              <span class="px-2 py-0.5 text-[9px] font-extrabold rounded uppercase bg-emerald-100 text-emerald-800">
                Single Store Mode (STR-001)
              </span>
            </div>
            <p class="text-slate-600 leading-relaxed">
              RUPSA PADUKALAYA operates exclusively as a Single Store system (Main Outlet: STR-001). All product inventory, POS sales, and transactions automatically target the main store.
            </p>
          </div>
          <div class="flex items-center gap-2 text-xs font-bold text-emerald-600 shrink-0">
            <span>✓ Operational</span>
          </div>
        </div>

        <!-- LOYALTY TOGGLE -->
        <div class="pt-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div class="space-y-1 max-w-xl">
            <div class="font-black text-slate-900 text-sm">Loyalty & Customer Points Engine</div>
            <p class="text-slate-600 leading-relaxed">
              Enable customer points accrual, redemption rules and VIP tiering across POS billing and customer portal accounts.
            </p>
          </div>
          <button
            type="button"
            @click="toggleModule('loyalty')"
            :disabled="saving"
            :class="[
              'relative inline-flex h-7 w-13 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out',
              moduleStore.loyalty ? 'bg-red-600' : 'bg-slate-300'
            ]"
          >
            <span
              :class="[
                'pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow-md transition duration-200 ease-in-out',
                moduleStore.loyalty ? 'translate-x-6' : 'translate-x-0'
              ]"
            ></span>
          </button>
        </div>

        <!-- INTER-STORE TRANSFERS TOGGLE -->
        <div class="pt-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div class="space-y-1 max-w-xl">
            <div class="font-black text-slate-900 text-sm">Inter-Store Stock Transfers</div>
            <p class="text-slate-600 leading-relaxed">
              Enable stock requisitions, transfer dispatches and transit tracking between footwear outlets.
            </p>
          </div>
          <button
            type="button"
            @click="toggleModule('transfers')"
            :disabled="saving"
            :class="[
              'relative inline-flex h-7 w-13 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out',
              moduleStore.transfers ? 'bg-red-600' : 'bg-slate-300'
            ]"
          >
            <span
              :class="[
                'pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow-md transition duration-200 ease-in-out',
                moduleStore.transfers ? 'translate-x-6' : 'translate-x-0'
              ]"
            ></span>
          </button>
        </div>

        <!-- ADVANCED REPORTS TOGGLE -->
        <div class="pt-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div class="space-y-1 max-w-xl">
            <div class="font-black text-slate-900 text-sm">Advanced Analytics & Reports</div>
            <p class="text-slate-600 leading-relaxed">
              Enable executive analytics matrix, RFM segmentation, supplier lead time tracking and inventory turnover metrics.
            </p>
          </div>
          <button
            type="button"
            @click="toggleModule('advancedReports')"
            :disabled="saving"
            :class="[
              'relative inline-flex h-7 w-13 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out',
              moduleStore.advancedReports ? 'bg-red-600' : 'bg-slate-300'
            ]"
          >
            <span
              :class="[
                'pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow-md transition duration-200 ease-in-out',
                moduleStore.advancedReports ? 'translate-x-6' : 'translate-x-0'
              ]"
            ></span>
          </button>
        </div>

        <!-- EXPENSES TOGGLE -->
        <div class="pt-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div class="space-y-1 max-w-xl">
            <div class="font-black text-slate-900 text-sm">Store Expense Ledger</div>
            <p class="text-slate-600 leading-relaxed">
              Enable petty cash expense logging, category breakdown and POS register session linkage.
            </p>
          </div>
          <button
            type="button"
            @click="toggleModule('expenses')"
            :disabled="saving"
            :class="[
              'relative inline-flex h-7 w-13 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out',
              moduleStore.expenses ? 'bg-red-600' : 'bg-slate-300'
            ]"
          >
            <span
              :class="[
                'pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow-md transition duration-200 ease-in-out',
                moduleStore.expenses ? 'translate-x-6' : 'translate-x-0'
              ]"
            ></span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useModuleStore } from '../../stores/moduleStore';

const moduleStore = useModuleStore();
const saving = ref(false);

const coreSystemItems = [
  { name: 'Admin Dashboard', description: 'Central Executive Dashboard with live KPI counters and financial sales analytics.' },
  { name: 'Authentication & Sanctum Security', description: 'Token-based REST API authentication and user session management.' },
  { name: 'RBAC & Permission Authorization', description: 'Role-based access control and granular API endpoint authorization guards.' },
  { name: 'Immutable Audit Trail', description: 'Security log capturing system events, IP addresses, and user activity history.' },
];

async function toggleMultiStore() {
  const nextState = !moduleStore.multiStore;
  saving.value = true;
  try {
    await moduleStore.updateModule('multiStore', nextState);
  } catch (err) {
    alert('Failed to update Multi Store setting. Please try again.');
  } finally {
    saving.value = false;
  }
}

async function toggleModule(key) {
  const currentState = moduleStore[key];
  saving.value = true;
  try {
    await moduleStore.updateModule(key, !currentState);
  } catch (err) {
    alert('Failed to update module setting.');
  } finally {
    saving.value = false;
  }
}

onMounted(() => {
  moduleStore.fetchSettings();
});
</script>
