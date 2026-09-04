<template>
  <div class="bg-white rounded-2xl border border-slate-200/90 p-6 shadow-xs flex flex-col justify-between">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-base font-black text-slate-900">Store Performance</h3>
        <p class="text-xs text-slate-500 font-medium mt-0.5">Branch sales & margin rankings</p>
      </div>
      <router-link to="/admin/settings/stores" class="text-xs font-bold text-red-600 hover:underline">
        View All Stores
      </router-link>
    </div>

    <!-- Empty State if no stores or data -->
    <div v-if="!stores || stores.length === 0" class="w-full py-12 flex flex-col items-center justify-center text-center p-6 bg-slate-50 rounded-xl border border-dashed border-slate-200">
      <span class="text-3xl mb-2">🏢</span>
      <h4 class="font-black text-xs text-slate-700">No Store Performance Data</h4>
      <p class="text-[11px] text-slate-400 font-medium mt-0.5">No store sales records found for the selected filter.</p>
    </div>

    <!-- Table -->
    <div v-else class="overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead>
          <tr class="border-b border-slate-100 text-slate-400 font-bold uppercase tracking-wider">
            <th class="pb-3 pl-1">Store</th>
            <th class="pb-3 text-right">Sales (₹)</th>
            <th class="pb-3 text-right hidden sm:table-cell">Orders</th>
            <th class="pb-3 text-right">Profit (₹)</th>
            <th class="pb-3 text-right">Margin</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="st in stores" :key="st.name" class="hover:bg-slate-50/80 transition-colors">
            <td class="py-3 pl-1 font-bold text-slate-900">{{ st.name }}</td>
            <td class="py-3 text-right font-black text-slate-900">₹{{ st.sales }}</td>
            <td class="py-3 text-right text-slate-600 font-semibold hidden sm:table-cell">{{ st.orders }}</td>
            <td class="py-3 text-right text-emerald-600 font-bold">₹{{ st.profit }}</td>
            <td class="py-3 text-right">
              <div class="flex items-center justify-end gap-2">
                <span class="font-extrabold text-[11px] text-slate-700">{{ st.targetPct }}%</span>
                <div class="w-16 h-2 bg-slate-100 rounded-full overflow-hidden">
                  <div
                    class="h-full rounded-full transition-all"
                    :style="{ width: Math.min(100, Math.max(0, st.targetPct)) + '%', backgroundColor: getProgressBarColor(st.targetPct) }"
                  ></div>
                </div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  stores: {
    type: Array,
    default: () => []
  }
});

function getProgressBarColor(pct) {
  if (pct >= 40) return '#16a34a'; // Emerald
  if (pct >= 20) return '#ca8a04'; // Amber
  return '#dc2626'; // Red
}
</script>
