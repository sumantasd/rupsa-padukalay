<template>
  <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl p-4 shadow-xs space-y-3 font-sans transition-all hover:border-slate-300 dark:hover:border-slate-700">
    <!-- Top Row: ID/Title & Status Badge -->
    <div class="flex items-start justify-between gap-3">
      <div>
        <slot name="title">
          <h4 class="font-black text-sm text-slate-900 dark:text-white font-mono leading-tight">
            {{ title }}
          </h4>
        </slot>
        <p v-if="subtitle" class="text-xs text-slate-500 font-medium mt-0.5">
          {{ subtitle }}
        </p>
      </div>

      <div v-if="status" class="shrink-0">
        <span :class="['px-2.5 py-1 text-[10px] font-black rounded-lg uppercase tracking-wider', statusClass]">
          {{ status }}
        </span>
      </div>
    </div>

    <!-- Key Metrics Grid -->
    <div v-if="details && details.length" class="grid grid-cols-2 gap-2 bg-slate-50 dark:bg-slate-950/80 p-3 rounded-xl border border-slate-100 dark:border-slate-800/80 text-xs">
      <div v-for="(item, idx) in details" :key="idx" class="flex flex-col">
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ item.label }}</span>
        <span class="font-black text-slate-900 dark:text-white mt-0.5 truncate">{{ item.value }}</span>
      </div>
    </div>

    <!-- Expandable Detailed View -->
    <div v-if="isExpanded" class="pt-2 border-t border-slate-100 dark:border-slate-800/80 space-y-2 animate-in fade-in duration-150">
      <slot name="details"></slot>
    </div>

    <!-- Footer Row: Primary Metric & Expand Toggle / Actions -->
    <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800/60 gap-2">
      <div>
        <slot name="metric">
          <div v-if="metric" class="flex flex-col">
            <span v-if="metricLabel" class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">{{ metricLabel }}</span>
            <span class="font-black text-sm text-slate-900 dark:text-white font-mono">{{ metric }}</span>
          </div>
        </slot>
      </div>

      <div class="flex items-center gap-2 shrink-0">
        <slot name="actions"></slot>

        <button
          v-if="expandable || $slots.details"
          type="button"
          @click="isExpanded = !isExpanded"
          class="flex items-center gap-1 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold text-xs transition-all cursor-pointer min-h-[36px]"
        >
          <span>{{ isExpanded ? 'Hide Details' : 'View Details' }}</span>
          <span class="text-[10px] transition-transform" :class="{ 'rotate-180': isExpanded }">▼</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  title: String,
  subtitle: String,
  status: String,
  statusType: { type: String, default: 'info' },
  metric: String,
  metricLabel: { type: String, default: 'Amount' },
  details: { type: Array, default: () => [] },
  expandable: { type: Boolean, default: true },
  defaultExpanded: { type: Boolean, default: false },
});

const isExpanded = ref(props.defaultExpanded);

const statusClass = computed(() => {
  const type = props.statusType?.toLowerCase() || 'info';
  switch (type) {
    case 'success':
    case 'completed':
    case 'paid':
    case 'received':
    case 'in_stock':
      return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300';
    case 'warning':
    case 'pending':
    case 'partial':
    case 'low_stock':
      return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
    case 'danger':
    case 'error':
    case 'cancelled':
    case 'damaged':
    case 'out_of_stock':
      return 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300';
    default:
      return 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300';
  }
});
</script>
