<template>
  <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl p-4 shadow-xs space-y-3 font-sans transition-all">
    <!-- Top Row: Title / Primary ID & Status Badge -->
    <div class="flex items-start justify-between gap-3">
      <div>
        <slot name="title">
          <h3 class="font-black text-sm text-slate-900 dark:text-white font-mono leading-tight">
            {{ title }}
          </h3>
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

    <!-- Body Details Slot / Grid -->
    <div v-if="$slots.default || details?.length" class="text-xs text-slate-700 dark:text-slate-300 space-y-1 bg-slate-50 dark:bg-slate-950/60 p-3 rounded-xl border border-slate-100 dark:border-slate-800">
      <slot>
        <div v-for="(item, idx) in details" :key="idx" class="flex items-center justify-between text-xs">
          <span class="text-slate-500 font-medium">{{ item.label }}:</span>
          <span class="font-bold text-slate-900 dark:text-white">{{ item.value }}</span>
        </div>
      </slot>
    </div>

    <!-- Footer Row: Key Metric & Actions -->
    <div class="flex items-center justify-between pt-1 border-t border-slate-100 dark:border-slate-800/80 gap-2">
      <div>
        <slot name="metric">
          <div v-if="metric" class="font-black text-sm text-slate-900 dark:text-white font-mono">
            {{ metric }}
          </div>
        </slot>
      </div>

      <div class="flex items-center gap-2 shrink-0">
        <slot name="actions"></slot>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  title: String,
  subtitle: String,
  status: String,
  statusType: { type: String, default: 'info' }, // success, warning, error, info
  metric: String,
  details: { type: Array, default: () => [] },
});

const statusClass = computed(() => {
  const type = props.statusType?.toLowerCase() || 'info';
  switch (type) {
    case 'success':
    case 'completed':
    case 'paid':
    case 'received':
      return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300';
    case 'warning':
    case 'pending':
    case 'partial':
      return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
    case 'danger':
    case 'error':
    case 'cancelled':
    case 'damaged':
      return 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300';
    default:
      return 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300';
  }
});
</script>
