<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-10">
    <!-- Header Banner -->
    <div class="rounded-3xl bg-gradient-to-r from-red-900 via-red-800 to-slate-900 text-white p-8 sm:p-12 shadow-xl border border-red-700">
      <span class="inline-block px-3 py-1 rounded-full bg-white/20 text-white text-[11px] font-extrabold uppercase tracking-wider mb-3">
        Active Discount Campaigns
      </span>
      <h1 class="text-3xl sm:text-5xl font-black tracking-tight">RUPSA PADUKALAYA Offers</h1>
      <p class="text-xs sm:text-sm text-red-100 mt-2 max-w-xl">
        Exclusive seasonal discounts on genuine leather shoes, ethnic sandals & daily comfort slippers. Valid online and across all retail outlets.
      </p>
    </div>

    <!-- Promotional Offers Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div v-for="promo in activePromos" :key="promo.title" class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-3 hover:border-red-600 transition-all">
        <div class="flex items-center justify-between">
          <span class="text-xs font-mono font-bold bg-red-100 text-red-700 px-2.5 py-1 rounded-md border border-red-200">
            CODE: {{ promo.code }}
          </span>
          <span class="text-xs font-extrabold text-emerald-600">{{ promo.discountTag }}</span>
        </div>
        <h3 class="font-black text-lg text-slate-900">{{ promo.title }}</h3>
        <p class="text-xs text-slate-500 leading-relaxed">{{ promo.description }}</p>
        <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400">
          <span>Valid till: {{ promo.expiry }}</span>
          <router-link to="/products" class="font-bold text-red-700 hover:underline">Shop Eligible Footwear →</router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const activePromos = [
  { code: 'LEATHER20', discountTag: 'FLAT 20% OFF', title: 'Executive Leather Spring Sale', description: 'Get flat 20% discount on all executive oxfords, derbies & monk straps.', expiry: '31st March 2026' },
  { code: 'FESTIVAL15', discountTag: 'FLAT 15% OFF', title: 'Ethnic Sandal Festival Offer', description: 'Flat 15% discount on women ethnic embroidered sandals & heels.', expiry: '15th April 2026' },
  { code: 'ORTHO10', discountTag: 'FLAT 10% OFF', title: 'Daily Comfort Slippers Promo', description: 'Extra 10% discount on ortho support daily slippers and flip-flops.', expiry: '30th April 2026' },
];
</script>
