<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-16 antialiased font-sans">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Home Page Content Management</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">Configure hero banner slides, featured categories, new arrivals and promo cards.</p>
      </div>
      <button @click="save" class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-black shadow-md shadow-red-600/20 transition-all">
        Save Homepage Settings
      </button>
    </div>

    <div class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-6 text-xs">
      <div class="border-b border-slate-100 pb-3">
        <h3 class="font-black text-sm text-slate-900">Homepage Section Visibility</h3>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 font-bold text-slate-800">
        <label class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between cursor-pointer">
          <span>Hero Banner Slider</span>
          <input type="checkbox" checked class="rounded text-red-600" />
        </label>

        <label class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between cursor-pointer">
          <span>Promo Showcase Cards</span>
          <input type="checkbox" checked class="rounded text-red-600" />
        </label>

        <label class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between cursor-pointer">
          <span>Shop By Category</span>
          <input type="checkbox" checked class="rounded text-red-600" />
        </label>

        <label class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between cursor-pointer">
          <span>New Arrivals Grid</span>
          <input type="checkbox" checked class="rounded text-red-600" />
        </label>

        <label class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between cursor-pointer">
          <span>Why Choose RUPSA</span>
          <input type="checkbox" checked class="rounded text-red-600" />
        </label>

        <label class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between cursor-pointer">
          <span>Newsletter Subscription</span>
          <input type="checkbox" checked class="rounded text-red-600" />
        </label>
      </div>
    </div>
  </div>
</template>

<script setup>
function save() {
  alert('Homepage settings saved successfully.');
}
</script>
