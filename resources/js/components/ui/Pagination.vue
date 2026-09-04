<template>
  <div v-if="totalPages > 1" class="flex flex-col sm:flex-row items-center justify-between gap-4 px-2 py-3">
    <div class="text-xs text-slate-500 dark:text-slate-400">
      Showing page <span class="font-semibold text-slate-700 dark:text-slate-200">{{ currentPage }}</span> of <span class="font-semibold text-slate-700 dark:text-slate-200">{{ totalPages }}</span>
    </div>
    <div class="flex items-center gap-1.5">
      <button
        type="button"
        :disabled="currentPage <= 1"
        @click="$emit('change-page', currentPage - 1)"
        class="px-2.5 py-1 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
      >
        Previous
      </button>
      <button
        type="button"
        :disabled="currentPage >= totalPages"
        @click="$emit('change-page', currentPage + 1)"
        class="px-2.5 py-1 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
      >
        Next
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  currentPage: { type: Number, default: 1 },
  totalPages: { type: Number, default: 1 },
});

defineEmits(['change-page']);
</script>
