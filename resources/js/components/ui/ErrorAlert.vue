<template>
  <div v-if="message" class="rounded-lg bg-rose-950/80 border border-rose-800 p-3 text-xs text-rose-200 flex items-start gap-2">
    <span class="text-sm">⚠️</span>
    <div class="flex-1">
      <p class="font-semibold">{{ message }}</p>
    </div>
  </div>
</template>

<script setup>
defineProps({
  message: { type: String, default: '' },
});
</script>
