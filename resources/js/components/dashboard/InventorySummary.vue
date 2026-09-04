<template>
  <div class="bg-white rounded-2xl border border-slate-200/90 p-5 shadow-xs flex items-center justify-between">
    <div class="flex items-center gap-4">
      <div class="h-12 w-12 rounded-2xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center text-xl shadow-xs">
        📦
      </div>
      <div>
        <div class="flex items-center gap-2">
          <h4 class="text-xs font-black text-slate-900 uppercase tracking-wider">Inventory Summary</h4>
          <router-link to="/admin/reports/inventory-reports" class="text-[10px] font-bold text-emerald-600 hover:underline">View Details ∨</router-link>
        </div>
        <div class="text-2xl font-black text-slate-900 tracking-tight mt-0.5">
          {{ totalSkus }}
        </div>
        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
          Total Active Inventory Units
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  totalSkus: { type: [String, Number], default: '0' }
});
</script>
