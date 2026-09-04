<template>
  <div class="py-6 border-t border-slate-200/80 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 divide-y md:divide-y-0 md:divide-x divide-slate-200">
        <div
          v-for="(feature, idx) in features"
          :key="feature.id || idx"
          class="flex items-center gap-3 p-3 md:px-6 justify-start md:justify-center"
        >
          <div class="h-10 w-10 rounded-full bg-slate-100 flex items-center justify-center text-xl shrink-0 border border-slate-200">
            <span>{{ feature.icon }}</span>
          </div>
          <div>
            <h4 class="font-black text-xs text-slate-900 uppercase tracking-tight">{{ feature.title }}</h4>
            <p class="text-[11px] text-slate-500 font-medium leading-tight mt-0.5">{{ feature.subtitle }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  features: {
    type: Array,
    default: () => []
  }
});
</script>
