<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div v-if="show" class="fixed inset-0 z-[500] bg-slate-950/70 backdrop-blur-xs flex items-end sm:items-center justify-center p-0 sm:p-4 overflow-hidden">
        <!-- Backdrop click listener -->
        <div class="absolute inset-0" @click="$emit('close')"></div>

        <!-- Modal Dialog Container -->
        <div
          class="relative w-full max-h-[100dvh] sm:max-h-[90vh] rounded-t-2xl sm:rounded-2xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-2xl border-t sm:border border-slate-200 dark:border-slate-800 transform transition-all flex flex-col overflow-hidden z-10"
          :class="maxWidthClass[maxWidth]"
          @click.stop
        >
          <!-- Modal Header -->
          <div v-if="title || $slots.header" class="flex items-center justify-between px-4 sm:px-6 py-3.5 sm:py-4 border-b border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 shrink-0">
            <slot name="header">
              <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-slate-100 truncate pr-2">{{ title }}</h3>
            </slot>
            <button
              type="button"
              @click="$emit('close')"
              class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors flex items-center justify-center font-bold text-base cursor-pointer shrink-0"
            >
              ✕
            </button>
          </div>

          <!-- Modal Body -->
          <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4">
            <slot></slot>
          </div>

          <!-- Modal Footer -->
          <div v-if="$slots.footer" class="flex items-center justify-end gap-2.5 sm:gap-3 px-4 sm:px-6 py-3 sm:py-4 bg-slate-50 dark:bg-slate-950 border-t border-slate-100 dark:border-slate-800 shrink-0 safe-pb">
            <slot name="footer"></slot>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { watch, onUnmounted } from 'vue';

const props = defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: '' },
  maxWidth: { type: String, default: 'md' }, // sm, md, lg, xl, 2xl, full
});

defineEmits(['close']);

const maxWidthClass = {
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-lg',
  xl: 'max-w-xl',
  '2xl': 'max-w-2xl',
  full: 'max-w-5xl',
};

watch(() => props.show, (val) => {
  if (val) {
    document.body.style.overflow = 'hidden';
  } else {
    document.body.style.overflow = '';
  }
});

onUnmounted(() => {
  document.body.style.overflow = '';
});
</script>

<style scoped>
.safe-pb {
  padding-bottom: max(1rem, env(safe-area-inset-bottom, 0px));
}
</style>

