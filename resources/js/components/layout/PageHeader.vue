<template>
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200/80 dark:border-slate-800 pb-4 mb-4 font-sans">
    <div class="flex items-center gap-3">
      <button
        v-if="showBack"
        @click="goBack"
        type="button"
        class="w-9 h-9 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center font-bold text-sm cursor-pointer shrink-0 shadow-xs"
        title="Go Back"
      >
        ←
      </button>

      <div>
        <h1 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
          <span v-if="icon">{{ icon }}</span>
          <span>{{ title }}</span>
        </h1>
        <p v-if="subtitle" class="text-xs text-slate-500 font-medium mt-0.5">
          {{ subtitle }}
        </p>
      </div>
    </div>

    <div class="flex items-center gap-2 shrink-0">
      <slot name="actions"></slot>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router';

const props = defineProps({
  title: { type: String, required: true },
  subtitle: String,
  icon: String,
  showBack: Boolean,
});

const router = useRouter();

function goBack() {
  router.back();
}
</script>
