<template>
  <div class="bg-white rounded-2xl border border-slate-200/90 p-6 shadow-xs flex flex-col justify-between">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-base font-black text-slate-900">Top Selling Products</h3>
        <p class="text-xs text-slate-500 font-medium mt-0.5">Highest volume footwear articles</p>
      </div>
      <router-link to="/admin/products" class="text-xs font-bold text-red-600 hover:underline">
        View All
      </router-link>
    </div>

    <!-- Empty State if no top products -->
    <div v-if="!products || products.length === 0" class="w-full py-8 flex flex-col items-center justify-center text-center p-6 bg-slate-50 rounded-xl border border-dashed border-slate-200">
      <span class="text-3xl mb-2">👟</span>
      <h4 class="font-black text-xs text-slate-700">No Top Selling Products</h4>
      <p class="text-[11px] text-slate-400 font-medium mt-0.5">No product sales recorded for selected period.</p>
    </div>

    <!-- Products List -->
    <div v-else class="space-y-3">
      <div
        v-for="p in products"
        :key="p.sku"
        class="flex items-center justify-between p-3 rounded-xl bg-slate-50/70 border border-slate-100 hover:border-indigo-200 transition-all"
      >
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-xl shrink-0 shadow-xs">
            {{ p.icon || '👟' }}
          </div>
          <div>
            <h4 class="font-extrabold text-xs text-slate-900 leading-snug">{{ p.name }}</h4>
            <div class="text-[10px] font-mono font-bold text-slate-400">SKU: {{ p.sku }}</div>
          </div>
        </div>

        <div class="text-right">
          <div class="text-xs font-black text-slate-900">₹{{ p.revenue }}</div>
          <div class="text-[10px] font-extrabold text-slate-500 mt-0.5">{{ p.units }} sold</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  products: {
    type: Array,
    default: () => []
  }
});
</script>
