<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-16 antialiased font-sans">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">About Page Content Management</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">Edit title, description, brand heritage story and core values.</p>
      </div>
      <button @click="save" class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-black shadow-md shadow-red-600/20 transition-all">
        Save About Page
      </button>
    </div>

    <div class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-4 text-xs">
      <div class="space-y-1">
        <label class="font-bold text-slate-700 block">Page Title</label>
        <input type="text" value="About RUPSA PADUKALAYA" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900" />
      </div>

      <div class="space-y-1">
        <label class="font-bold text-slate-700 block">Brand Heritage Description</label>
        <textarea rows="4" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900">Step into comfort. RUPSA PADUKALAYA is a premier footwear brand dedicated to combining authentic Indian craftsmanship, ergonomic arch support, and modern elegance.</textarea>
      </div>
    </div>
  </div>
</template>

<script setup>
function save() {
  alert('About page saved successfully.');
}
</script>
