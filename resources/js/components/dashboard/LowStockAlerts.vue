<template>
  <div class="bg-white rounded-2xl border border-slate-200/90 p-6 shadow-xs flex flex-col justify-between">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-base font-black text-slate-900">Low Stock Alerts</h3>
        <p class="text-xs text-slate-500 font-medium mt-0.5">Footwear items requiring replenishment</p>
      </div>
      <router-link to="/admin/reports/inventory-reports" class="text-xs font-bold text-red-600 hover:underline">
        View All
      </router-link>
    </div>

    <!-- Empty State if no low stock items -->
    <div v-if="!items || items.length === 0" class="w-full py-8 flex flex-col items-center justify-center text-center p-6 bg-slate-50 rounded-xl border border-dashed border-slate-200">
      <span class="text-3xl mb-2">✅</span>
      <h4 class="font-black text-xs text-slate-700">All Stock Levels Healthy</h4>
      <p class="text-[11px] text-slate-400 font-medium mt-0.5">No products currently below reorder threshold.</p>
    </div>

    <!-- Items List -->
    <div v-else class="space-y-3">
      <div
        v-for="item in items"
        :key="item.sku"
        class="flex items-center justify-between p-3 rounded-xl bg-slate-50/70 border border-slate-100 hover:border-red-200 transition-all"
      >
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-xl shrink-0 shadow-xs">
            {{ item.icon || '👟' }}
          </div>
          <div>
            <h4 class="font-extrabold text-xs text-slate-900 leading-snug">{{ item.name }}</h4>
            <div class="text-[10px] font-mono font-bold text-slate-400">SKU: {{ item.sku }}</div>
            <div class="text-[10px] text-slate-500 font-medium flex items-center gap-1 mt-0.5">
              <span>📍 {{ item.store }}</span>
            </div>
          </div>
        </div>

        <div class="text-right">
          <div class="text-base font-black text-red-600 leading-none">{{ item.stock }}</div>
          <div class="text-[9px] font-bold text-slate-400 mt-1">Min: {{ item.min }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  items: {
    type: Array,
    default: () => []
  }
});
</script>
