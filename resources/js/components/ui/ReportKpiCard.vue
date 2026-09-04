<template>
  <div
    :class="[
      'p-4 sm:p-5 rounded-2xl border transition-all duration-200 shadow-sm flex flex-col justify-between font-sans relative overflow-hidden',
      containerClass
    ]"
  >
    <!-- Background Watermark Glow -->
    <div
      v-if="icon"
      class="absolute -right-2 -bottom-2 text-4xl opacity-10 select-none pointer-events-none"
    >
      {{ icon }}
    </div>

    <!-- Top Row: Title & Badge/Icon -->
    <div class="flex items-start justify-between gap-2 mb-2">
      <div class="space-y-0.5">
        <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 block">
          {{ title }}
        </span>
        <p v-if="subtitle" class="text-[10px] text-slate-400 font-medium leading-tight">
          {{ subtitle }}
        </p>
      </div>

      <div class="flex items-center gap-1.5 shrink-0">
        <span v-if="badge" :class="['px-2 py-0.5 text-[9px] font-black rounded-md uppercase tracking-wide', badgeClass]">
          {{ badge }}
        </span>
        <div v-if="icon" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-sm shadow-xs">
          {{ icon }}
        </div>
      </div>
    </div>

    <!-- Main Metric Value -->
    <div class="mt-1">
      <div class="text-xl sm:text-2xl font-black tracking-tight text-slate-900 dark:text-white font-mono">
        {{ formattedValue }}
      </div>
      <div v-if="subtext" class="text-[10px] font-extrabold mt-1" :class="subtextClass">
        {{ subtext }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  title: { type: String, required: true },
  value: { type: [String, Number], default: 0 },
  subtitle: { type: String, default: '' },
  subtext: { type: String, default: '' },
  subtextType: { type: String, default: 'neutral' }, // success, danger, warning, neutral
  icon: { type: String, default: '' },
  badge: { type: String, default: '' },
  badgeType: { type: String, default: 'info' },
  isCurrency: { type: Boolean, default: false },
  accentColor: { type: String, default: 'slate' }, // red, emerald, indigo, amber, slate
});

const formattedValue = computed(() => {
  if (props.isCurrency) {
    const num = Number(props.value) || 0;
    return '₹' + num.toLocaleString('en-IN', { maximumFractionDigits: 2, minimumFractionDigits: 0 });
  }
  if (typeof props.value === 'number') {
    return props.value.toLocaleString('en-IN');
  }
  return props.value || '0';
});

const containerClass = computed(() => {
  switch (props.accentColor) {
    case 'red':
      return 'bg-gradient-to-br from-red-500/10 via-slate-900/5 to-transparent border-red-500/30 dark:border-red-600/40';
    case 'emerald':
      return 'bg-gradient-to-br from-emerald-500/10 via-slate-900/5 to-transparent border-emerald-500/30 dark:border-emerald-600/40';
    case 'indigo':
      return 'bg-gradient-to-br from-indigo-500/10 via-slate-900/5 to-transparent border-indigo-500/30 dark:border-indigo-600/40';
    case 'amber':
      return 'bg-gradient-to-br from-amber-500/10 via-slate-900/5 to-transparent border-amber-500/30 dark:border-amber-600/40';
    default:
      return 'bg-white dark:bg-slate-900 border-slate-200/90 dark:border-slate-800';
  }
});

const badgeClass = computed(() => {
  switch (props.badgeType) {
    case 'success':
      return 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400';
    case 'danger':
      return 'bg-red-500/15 text-red-600 dark:text-red-400';
    case 'warning':
      return 'bg-amber-500/15 text-amber-600 dark:text-amber-400';
    default:
      return 'bg-slate-500/15 text-slate-600 dark:text-slate-400';
  }
});

const subtextClass = computed(() => {
  switch (props.subtextType) {
    case 'success':
      return 'text-emerald-600 dark:text-emerald-400';
    case 'danger':
      return 'text-red-600 dark:text-red-400';
    case 'warning':
      return 'text-amber-600 dark:text-amber-400';
    default:
      return 'text-slate-500 dark:text-slate-400';
  }
});
</script>
