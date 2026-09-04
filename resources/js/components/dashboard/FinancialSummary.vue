<template>
  <div class="bg-white rounded-2xl border border-slate-200/90 p-5 shadow-xs flex items-center justify-between">
    <div class="flex items-center gap-4">
      <div class="h-12 w-12 rounded-2xl bg-red-50 border border-red-100 text-red-600 flex items-center justify-center font-black text-xl shadow-xs">
        ₹
      </div>
      <div>
        <div class="flex items-center gap-2">
          <h4 class="text-xs font-black text-slate-900 uppercase tracking-wider">Financial Summary</h4>
          <router-link to="/admin/reports/sales-reports" class="text-[10px] font-bold text-red-600 hover:underline">View Details ∨</router-link>
        </div>
        <div class="text-2xl font-black text-slate-900 tracking-tight mt-0.5">
          ₹{{ netRevenue }}
        </div>
        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
          Net Revenue Across Stores
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  netRevenue: { type: [String, Number], default: '0' }
});
</script>
