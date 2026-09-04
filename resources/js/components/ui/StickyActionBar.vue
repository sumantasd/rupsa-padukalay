<template>
  <div class="lg:hidden fixed bottom-16 left-0 right-0 z-30 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-t border-slate-200 dark:border-slate-800 p-3.5 shadow-xl safe-pb font-sans">
    <div class="max-w-7xl mx-auto flex items-center justify-between gap-3">
      <slot></slot>
    </div>
  </div>
</template>

<script setup>
</script>

<style scoped>
.safe-pb {
  padding-bottom: env(safe-area-inset-bottom, 0px);
}
</style>
