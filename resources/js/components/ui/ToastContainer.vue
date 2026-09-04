<template>
  <div class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none">
    <TransitionGroup
      enter-active-class="transition duration-300 ease-out transform"
      enter-from-class="translate-y-2 opacity-0 scale-95"
      enter-to-class="translate-y-0 opacity-100 scale-100"
      leave-active-class="transition duration-200 ease-in transform"
      leave-from-class="translate-y-0 opacity-100 scale-100"
      leave-to-class="translate-y-2 opacity-0 scale-95"
    >
      <div
        v-for="toast in toasts"
        :key="toast.id"
        :class="[
          'pointer-events-auto flex items-start gap-3 p-4 rounded-xl shadow-lg border text-sm backdrop-blur-md',
          typeClasses[toast.type] || typeClasses.info
        ]"
      >
        <div class="flex-1">
          <h4 v-if="toast.title" class="font-semibold mb-0.5">{{ toast.title }}</h4>
          <p class="text-xs opacity-90">{{ toast.message }}</p>
        </div>
        <button
          type="button"
          @click="removeToast(toast.id)"
          class="opacity-70 hover:opacity-100 p-0.5 rounded transition-opacity"
        >
          ✕
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup>
import { useUiStore } from '../../stores/uiStore';
import { computed } from 'vue';

const uiStore = useUiStore();
const toasts = computed(() => uiStore.toasts);
const removeToast = uiStore.removeToast;

const typeClasses = {
  success: 'bg-emerald-900/90 text-emerald-100 border-emerald-700',
  error: 'bg-rose-900/90 text-rose-100 border-rose-700',
  warning: 'bg-amber-900/90 text-amber-100 border-amber-700',
  info: 'bg-slate-900/90 text-slate-100 border-slate-700',
};
</script>
