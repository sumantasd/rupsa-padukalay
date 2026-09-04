<template>
  <div class="bg-white rounded-2xl border border-slate-200/90 p-4 sm:p-5 shadow-xs flex flex-col justify-between relative overflow-hidden group hover:border-slate-300 transition-all min-w-0">
    <!-- Top Row: Icon & Action/Badge -->
    <div class="flex items-center justify-between mb-3">
      <div :class="['h-10 w-10 sm:h-11 sm:w-11 rounded-xl flex items-center justify-center text-base sm:text-lg shadow-sm shrink-0', iconBgClass]">
        {{ icon }}
      </div>
      <div v-if="trendText" :class="['text-[10px] sm:text-[11px] font-extrabold flex items-center gap-1 shrink-0', trendColorClass]">
        <span>{{ trendText }}</span>
      </div>
    </div>

    <!-- Middle: Title & Main Value -->
    <div class="space-y-1 z-10 min-w-0">
      <span class="text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider block truncate">
        {{ title }}
      </span>
      <div class="text-xl sm:text-2xl xl:text-3xl font-black text-slate-900 tracking-tight truncate">
        {{ value }}
      </div>
      <div v-if="supportingText" class="text-[10px] sm:text-[11px] font-semibold text-slate-400 truncate">
        {{ supportingText }}
      </div>
    </div>

    <!-- Bottom Sparkline SVG Chart -->
    <div class="mt-3 sm:mt-4 pt-2 flex items-end justify-between border-t border-slate-100 min-w-0">
      <div class="w-full h-7 sm:h-8">
        <svg class="w-full h-full overflow-visible" viewBox="0 0 100 25" preserveAspectRatio="none">
          <path
            :d="sparklineD"
            fill="none"
            :stroke="sparklineColor"
            stroke-width="2.5"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  title: { type: String, required: true },
  value: { type: [String, Number], required: true },
  icon: { type: String, default: '📊' },
  iconBgClass: { type: String, default: 'bg-red-50 text-red-600' },
  supportingText: { type: String, default: '' },
  trendText: { type: String, default: '' },
  trendColorClass: { type: String, default: 'text-emerald-600' },
  sparklineColor: { type: String, default: '#dc2626' },
  sparklinePoints: { type: Array, default: () => [15, 18, 12, 22, 19, 25, 20, 28] },
});

const sparklineD = computed(() => {
  const pts = props.sparklinePoints;
  if (!pts || pts.length === 0) return 'M 0 15 L 100 15';
  const min = Math.min(...pts);
  const max = Math.max(...pts) || 1;
  const range = max - min || 1;
  const step = 100 / (pts.length - 1);

  return pts
    .map((val, idx) => {
      const x = idx * step;
      const y = 25 - ((val - min) / range) * 20;
      return `${idx === 0 ? 'M' : 'L'} ${x.toFixed(1)} ${y.toFixed(1)}`;
    })
    .join(' ');
});
</script>
