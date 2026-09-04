<template>
  <span
    :class="[
      'inline-flex items-center font-medium rounded-full px-2.5 py-0.5 text-xs',
      variantClasses[variant] || variantClasses.neutral
    ]"
  >
    <slot></slot>
  </span>
</template>

<script setup>
defineProps({
  variant: { type: String, default: 'neutral' }, // success, warning, danger, info, neutral
});

const variantClasses = {
  success: 'bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800',
  warning: 'bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
  danger: 'bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800',
  info: 'bg-sky-100 dark:bg-sky-950 text-sky-800 dark:text-sky-300 border border-sky-200 dark:border-sky-800',
  neutral: 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700',
};
</script>
