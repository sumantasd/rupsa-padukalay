<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-16 antialiased font-sans">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Contact Page Management</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">Edit store address, customer support phone, email and business hours.</p>
      </div>
      <button @click="save" class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-black shadow-md shadow-red-600/20 transition-all">
        Save Contact Settings
      </button>
    </div>

    <div class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-4 text-xs">
      <div class="space-y-1">
        <label class="font-bold text-slate-700 block">Store HQ Address</label>
        <textarea rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-medium">123 Footwear Market Road, College Street Hub, Kolkata 700001</textarea>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="space-y-1">
          <label class="font-bold text-slate-700 block">Support Phone</label>
          <input type="text" value="+91 98765 43210" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-mono text-xs" />
        </div>

        <div class="space-y-1">
          <label class="font-bold text-slate-700 block">Support Email</label>
          <input type="email" value="support@rupsapadukalaya.com" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-mono text-xs" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
function save() {
  alert('Contact page settings saved.');
}
</script>
