<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[400] flex items-end sm:items-center justify-center p-0 sm:p-4 font-sans">
      <!-- Backdrop -->
      <div
        @click="close"
        class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs transition-opacity duration-200"
      ></div>

      <!-- Sheet Container -->
      <div
        :class="[
          'relative w-full sm:max-w-lg bg-white dark:bg-slate-900 rounded-t-3xl sm:rounded-3xl shadow-2xl border-t sm:border border-slate-200/80 dark:border-slate-800 flex flex-col transition-all duration-300 z-10 overflow-hidden',
          fullScreen ? 'h-[92vh] sm:h-auto sm:max-h-[85vh]' : 'max-h-[85vh] sm:max-h-[80vh]'
        ]"
      >
        <!-- Drag Handle Indicator -->
        <div class="py-2.5 flex items-center justify-center shrink-0">
          <div class="w-12 h-1.5 rounded-full bg-slate-300 dark:bg-slate-700"></div>
        </div>

        <!-- Header -->
        <div class="px-5 pb-3 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between shrink-0">
          <div>
            <h3 class="font-black text-slate-900 dark:text-white text-base leading-tight">
              {{ title }}
            </h3>
            <p v-if="subtitle" class="text-xs text-slate-500 font-medium mt-0.5">
              {{ subtitle }}
            </p>
          </div>
          <button
            @click="close"
            class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-slate-900 dark:hover:text-white flex items-center justify-center font-bold text-sm cursor-pointer"
          >
            ✕
          </button>
        </div>

        <!-- Body -->
        <div class="flex-1 overflow-y-auto p-5 space-y-4">
          <slot></slot>
        </div>

        <!-- Footer / Actions -->
        <div v-if="$slots.footer" class="p-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 shrink-0 safe-pb">
          <slot name="footer"></slot>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { watch, onUnmounted } from 'vue';

const props = defineProps({
  show: Boolean,
  title: String,
  subtitle: String,
  fullScreen: Boolean,
});

const emit = defineEmits(['update:show', 'close']);

function close() {
  emit('update:show', false);
  emit('close');
}

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
  padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 1rem);
}
</style>
