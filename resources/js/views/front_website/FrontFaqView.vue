<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-16 antialiased font-sans">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Website FAQ Management</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">Manage frequently asked questions, size guide FAQs and return policies.</p>
      </div>
      <button @click="add" class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-black shadow-md shadow-red-600/20 transition-all">
        ➕ Add FAQ Question
      </button>
    </div>
  </div>
</template>

<script setup>
function add() {
  alert('Add FAQ feature ready.');
}
</script>
