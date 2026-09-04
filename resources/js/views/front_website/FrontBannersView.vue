<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-16 antialiased font-sans">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Website Banners & Hero Sliders</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">Manage auto-rotating homepage hero slides and promotional banners.</p>
      </div>
      <button @click="addBanner" class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-black shadow-md shadow-red-600/20 transition-all">
        ➕ Add New Banner
      </button>
    </div>

    <div class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-4">
      <div class="space-y-3">
        <div v-for="(b, idx) in banners" :key="idx" class="p-4 bg-slate-50 border border-slate-200 rounded-2xl flex items-center justify-between gap-4 text-xs">
          <div class="flex items-center gap-3">
            <div class="h-12 w-12 rounded-xl bg-slate-900 text-white font-black text-xl flex items-center justify-center">
              🖼️
            </div>
            <div>
              <div class="font-black text-slate-900">{{ b.title }}</div>
              <div class="text-[10px] text-slate-500 font-bold">{{ b.subtitle }}</div>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 text-[10px] font-bold">Active</span>
            <button class="px-3 py-1 bg-slate-200 text-slate-800 rounded-lg font-bold text-[10px]">Edit</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const banners = ref([
  { title: "ITS RAINING DISCOUNTS! UP TO 50% OFF", subtitle: "Monsoon footwear deals" },
  { title: "MEN'S EXECUTIVE LEATHER", subtitle: "Comfort & style everyday" },
  { title: "WOMEN'S ETHNIC & HEELS", subtitle: "Premium quality built to last" },
  { title: "KIDS FOOTWEAR COLLECTION", subtitle: "Fun, comfy, colorful" },
]);

function addBanner() {
  alert('Add banner feature dialog ready.');
}
</script>
