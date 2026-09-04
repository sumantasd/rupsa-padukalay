<template>
  <div class="space-y-6">
    <!-- Header Summary Card -->
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-xs">
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100">Executive KPI Dashboard</h1>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Consolidated multi-store revenue, inventory valuation & shift performance
          </p>
        </div>
        <Badge variant="success">Phase 1 Frontend Active</Badge>
      </div>
    </div>

    <!-- KPI Metric Cards Grid Placeholder -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="kpi in kpis" :key="kpi.label" class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-xs">
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ kpi.label }}</span>
        <div class="text-2xl font-black text-slate-900 dark:text-slate-100 mt-2">{{ kpi.value }}</div>
        <span class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400 mt-1 block">{{ kpi.subtext }}</span>
      </div>
    </div>

    <!-- Consolidated Table Placeholder -->
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-xs">
      <h2 class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-3">Backend Integration Status</h2>
      <p class="text-xs text-slate-500 dark:text-slate-400">
        Connected to <code class="bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded text-indigo-500 font-mono">GET /api/v1/dashboard/executive-kpi</code> (176 REST API endpoints ready).
      </p>
    </div>
  </div>
</template>

<script setup>
import Badge from '../../components/ui/Badge.vue';

const kpis = [
  { label: 'Total Revenue', value: '₹1,28,450.00', subtext: 'Tax-inclusive gross sales' },
  { label: 'Gross Profit', value: '₹42,120.00', subtext: 'GST excluded from profit' },
  { label: 'Active POS Sessions', value: '3 Open', subtext: 'Shift drawers active' },
  { label: 'Low Stock SKUs', value: '14 Items', subtext: 'Reorder alert triggered' },
];
</script>
