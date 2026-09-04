<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-16 antialiased font-sans">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Website Pages Manager</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">Create and manage published or draft pages for the public website.</p>
      </div>
      <button @click="addPage" class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-black shadow-md shadow-red-600/20 transition-all">
        ➕ Create New Page
      </button>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
      <table class="w-full text-left text-xs">
        <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-wider">
          <tr>
            <th class="px-5 py-3.5">Page Name</th>
            <th class="px-5 py-3.5">Slug</th>
            <th class="px-5 py-3.5">Status</th>
            <th class="px-5 py-3.5 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
          <tr v-for="p in pages" :key="p.slug" class="hover:bg-slate-50 transition-colors">
            <td class="px-5 py-3.5 font-bold text-slate-900">{{ p.name }}</td>
            <td class="px-5 py-3.5 font-mono text-slate-500 text-[11px]">{{ p.slug }}</td>
            <td class="px-5 py-3.5">
              <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 text-[10px] font-bold">Published</span>
            </td>
            <td class="px-5 py-3.5 text-right space-x-2">
              <button class="px-2.5 py-1 bg-slate-100 text-slate-800 rounded-lg font-bold text-[11px]">Edit</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const pages = ref([
  { name: 'Home Page', slug: '/' },
  { name: 'About Us', slug: '/about' },
  { name: 'Men Collection', slug: '/categories/men' },
  { name: 'Women Collection', slug: '/categories/women' },
  { name: 'Kids Collection', slug: '/categories/kids' },
  { name: 'Others & Accessories', slug: '/categories/others' },
  { name: 'Contact Us', slug: '/contact' },
]);

function addPage() {
  alert('Create new page modal ready.');
}
</script>
