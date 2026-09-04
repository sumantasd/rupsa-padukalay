<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-16 antialiased font-sans">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">{{ categoryName }} Landing Page Management</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">Manage category header title, promotional banner, and featured product listings for {{ categoryName }}.</p>
      </div>
      <button @click="save" class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-black shadow-md shadow-red-600/20 transition-all">
        Save Category Page
      </button>
    </div>

    <div class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-4 text-xs">
      <div class="space-y-1">
        <label class="font-bold text-slate-700 block">Category Heading Title</label>
        <input type="text" :value="`${categoryName} Footwear Collection`" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900" />
      </div>

      <div class="space-y-1">
        <label class="font-bold text-slate-700 block">Category Subtitle / Description</label>
        <textarea rows="3" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900">Discover hand-finished quality footwear designed for daily style & ergonomic arch comfort.</textarea>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

const categoryName = computed(() => {
  const path = route.path;
  if (path.includes('/men')) return 'Men\'s';
  if (path.includes('/women')) return 'Women\'s';
  if (path.includes('/kids')) return 'Kids';
  return 'Accessories & Others';
});

function save() {
  alert(`${categoryName.value} page settings saved.`);
}
</script>
