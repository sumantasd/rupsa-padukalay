<template>
  <div class="bg-white rounded-2xl border border-slate-200/90 p-6 shadow-xs flex flex-col justify-between">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h3 class="text-base font-black text-slate-900">Sales Overview</h3>
        <p class="text-xs text-slate-500 font-medium mt-0.5">Daily sales revenue vs order volume</p>
      </div>

      <!-- Range Filter -->
      <select
        v-model="selectedRange"
        @change="$emit('range-change', selectedRange)"
        class="bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-red-600 cursor-pointer"
      >
        <option value="current_month">This Month</option>
        <option value="previous_month">Last Month</option>
        <option value="current_week">This Week</option>
        <option value="today">Today</option>
      </select>
    </div>

    <!-- Legend -->
    <div class="flex items-center gap-6 mb-4 text-xs font-bold">
      <div class="flex items-center gap-2">
        <span class="h-3 w-3 rounded-full bg-red-600"></span>
        <span class="text-slate-700">Net Sales Revenue (₹)</span>
      </div>
      <div class="flex items-center gap-2">
        <span class="h-3 w-3 rounded-full bg-slate-700"></span>
        <span class="text-slate-700">Completed Orders</span>
      </div>
    </div>

    <!-- Empty State if no trend data -->
    <div v-if="!salesTrend || salesTrend.length === 0" class="w-full h-64 flex flex-col items-center justify-center text-center p-6 bg-slate-50 rounded-xl border border-dashed border-slate-200">
      <span class="text-3xl mb-2">📊</span>
      <h4 class="font-black text-xs text-slate-700">No Sales Trend Data Available</h4>
      <p class="text-[11px] text-slate-400 font-medium mt-0.5">No completed sales recorded for the selected date period.</p>
    </div>

    <!-- Chart Visualization Container -->
    <div v-else class="w-full h-64 relative pt-4">
      <svg class="w-full h-full overflow-visible" viewBox="0 0 500 200" preserveAspectRatio="none">
        <!-- Horizontal Grid Lines -->
        <line x1="0" y1="40" x2="500" y2="40" stroke="#f1f5f9" stroke-width="1" />
        <line x1="0" y1="90" x2="500" y2="90" stroke="#f1f5f9" stroke-width="1" />
        <line x1="0" y1="140" x2="500" y2="140" stroke="#f1f5f9" stroke-width="1" />
        <line x1="0" y1="190" x2="500" y2="190" stroke="#e2e8f0" stroke-width="1.5" />

        <!-- Sales Line (Red) -->
        <path
          :d="salesPath"
          fill="none"
          stroke="#dc2626"
          stroke-width="3"
          stroke-linecap="round"
          stroke-linejoin="round"
        />

        <!-- Orders Line (Dark Slate) -->
        <path
          :d="ordersPath"
          fill="none"
          stroke="#334155"
          stroke-width="2.5"
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-dasharray="4 2"
        />

        <!-- Dots on Sales Line -->
        <circle
          v-for="(pt, i) in salesPoints"
          :key="'s-'+i"
          :cx="pt.x"
          :cy="pt.y"
          r="4"
          fill="#dc2626"
          stroke="#ffffff"
          stroke-width="2"
        />
      </svg>
    </div>

    <!-- Dynamic X-Axis Labels -->
    <div v-if="salesTrend && salesTrend.length > 0" class="flex justify-between text-[10px] font-bold text-slate-400 pt-3 border-t border-slate-100 overflow-x-auto">
      <span v-for="(item, i) in dateLabels" :key="i">{{ item }}</span>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const selectedRange = ref('current_month');

const props = defineProps({
  salesTrend: {
    type: Array,
    default: () => []
  }
});

defineEmits(['range-change']);

const salesPoints = computed(() => {
  if (!props.salesTrend || props.salesTrend.length === 0) return [];
  const vals = props.salesTrend.map(item => Number(item.net_revenue || item.gross_revenue || 0));
  const max = Math.max(...vals, 100);
  const step = props.salesTrend.length > 1 ? 500 / (props.salesTrend.length - 1) : 250;
  return vals.map((val, idx) => ({
    x: idx * step,
    y: 190 - (val / max) * 160
  }));
});

const salesPath = computed(() => {
  if (salesPoints.value.length === 0) return 'M 0 190 L 500 190';
  return salesPoints.value.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ');
});

const ordersPath = computed(() => {
  if (!props.salesTrend || props.salesTrend.length === 0) return 'M 0 190 L 500 190';
  const orders = props.salesTrend.map(item => Number(item.completed_orders || 0));
  const maxOrders = Math.max(...orders, 10);
  const step = props.salesTrend.length > 1 ? 500 / (props.salesTrend.length - 1) : 250;
  return orders.map((val, idx) => `${idx === 0 ? 'M' : 'L'} ${idx * step} ${190 - (val / maxOrders) * 160}`).join(' ');
});

const dateLabels = computed(() => {
  if (!props.salesTrend || props.salesTrend.length === 0) return [];
  return props.salesTrend.map(item => {
    if (!item.period) return '';
    const d = new Date(item.period);
    return isNaN(d.getTime()) ? item.period : d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
  });
});
</script>
