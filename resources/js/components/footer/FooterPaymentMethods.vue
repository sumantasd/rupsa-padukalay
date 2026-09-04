<template>
  <div class="py-4 border-t border-slate-200/80 bg-slate-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-center gap-4 text-xs">
      <span class="font-black text-slate-800 tracking-wider uppercase text-[11px] border-b-2 border-red-600 pb-0.5">WE ACCEPT</span>
      
      <div class="flex flex-wrap items-center justify-center gap-3 font-mono font-bold text-[11px]">
        <div v-for="method in methods" :key="method" class="px-2.5 py-1 bg-white rounded-md border border-slate-200 shadow-2xs text-slate-800 flex items-center gap-1">
          <span v-if="method === 'Visa'" class="text-blue-700 font-black italic">VISA</span>
          <span v-else-if="method === 'Mastercard'" class="text-orange-600 font-black">Mastercard</span>
          <span v-else-if="method === 'RuPay'" class="text-emerald-700 font-black">RuPay</span>
          <span v-else-if="method === 'UPI'" class="text-slate-900 font-black">UPI</span>
          <span v-else-if="method === 'Paytm'" class="text-sky-600 font-black">Paytm</span>
          <span v-else-if="method === 'PhonePe'" class="text-purple-700 font-black">PhonePe</span>
          <span v-else-if="method === 'G Pay'" class="text-slate-900 font-black">G Pay</span>
          <span v-else-if="method === 'Apple Pay'" class="text-slate-900 font-black"> Pay</span>
          <span v-else class="text-slate-700 font-bold">{{ method }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  methods: {
    type: Array,
    default: () => ['Visa', 'Mastercard', 'RuPay', 'UPI', 'Paytm', 'PhonePe', 'G Pay', 'Apple Pay']
  }
});
</script>
