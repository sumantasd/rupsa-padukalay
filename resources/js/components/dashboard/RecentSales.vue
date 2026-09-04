<template>
  <div class="bg-white rounded-2xl border border-slate-200/90 p-6 shadow-xs flex flex-col justify-between">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-base font-black text-slate-900">Recent Sales</h3>
        <p class="text-xs text-slate-500 font-medium mt-0.5">Live completed POS transactions</p>
      </div>
      <router-link to="/admin/pos" class="text-xs font-bold text-red-600 hover:underline">
        View All
      </router-link>
    </div>

    <!-- Empty State if no recent sales -->
    <div v-if="!sales || sales.length === 0" class="w-full py-8 flex flex-col items-center justify-center text-center p-6 bg-slate-50 rounded-xl border border-dashed border-slate-200">
      <span class="text-3xl mb-2">🛒</span>
      <h4 class="font-black text-xs text-slate-700">No Recent Sales Found</h4>
      <p class="text-[11px] text-slate-400 font-medium mt-0.5">No recent completed POS sales transactions.</p>
    </div>

    <!-- Table -->
    <div v-else class="overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead>
          <tr class="border-b border-slate-100 text-slate-400 font-bold uppercase tracking-wider">
            <th class="pb-3 pl-1">Invoice</th>
            <th class="pb-3">Customer</th>
            <th class="pb-3 hidden sm:table-cell">Store</th>
            <th class="pb-3 text-center">Items</th>
            <th class="pb-3 text-right">Amount</th>
            <th class="pb-3 text-center">Status</th>
            <th class="pb-3 text-right hidden md:table-cell">Time</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="sale in sales" :key="sale.invoice" class="hover:bg-slate-50/80 transition-colors">
            <td class="py-3 pl-1 font-mono font-bold text-red-700">{{ sale.invoice }}</td>
            <td class="py-3 font-semibold text-slate-900">{{ sale.customer }}</td>
            <td class="py-3 text-slate-600 hidden sm:table-cell">{{ sale.store }}</td>
            <td class="py-3 text-center font-bold text-slate-700">{{ sale.items }}</td>
            <td class="py-3 text-right font-black text-slate-900">₹{{ sale.amount }}</td>
            <td class="py-3 text-center">
              <span :class="['px-2 py-0.5 rounded text-[10px] font-extrabold uppercase', getPaymentBadgeClass(sale.payment)]">
                {{ sale.payment }}
              </span>
            </td>
            <td class="py-3 text-right text-slate-400 font-mono text-[11px] hidden md:table-cell">{{ sale.time }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  sales: {
    type: Array,
    default: () => []
  }
});

function getPaymentBadgeClass(mode) {
  if (mode === 'PAID' || mode === 'COMPLETED' || mode === 'Card') return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
  if (mode === 'UPI') return 'bg-blue-50 text-blue-700 border border-blue-200';
  return 'bg-amber-50 text-amber-800 border border-amber-200';
}
</script>
