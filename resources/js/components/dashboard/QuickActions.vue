<template>
  <div class="bg-white rounded-2xl border border-slate-200/90 p-5 shadow-xs flex flex-col justify-between">
    <div class="flex items-center justify-between mb-3">
      <h4 class="text-xs font-black text-slate-900 uppercase tracking-wider">Quick Actions</h4>
      <span class="text-[10px] font-bold text-slate-400">Shortcuts</span>
    </div>

    <!-- Actions Row -->
    <div class="grid grid-cols-6 gap-2">
      <!-- New Sale -->
      <router-link
        to="/admin/pos"
        class="flex flex-col items-center justify-center p-2 rounded-xl bg-red-50/60 hover:bg-red-100/80 border border-red-100 text-red-600 transition-colors group"
      >
        <span class="text-base group-hover:scale-110 transition-transform">🛍️</span>
        <span class="text-[9px] font-extrabold mt-1 text-slate-700 text-center leading-tight">New Sale</span>
      </router-link>

      <!-- Add Product -->
      <router-link
        to="/admin/products"
        class="flex flex-col items-center justify-center p-2 rounded-xl bg-emerald-50/60 hover:bg-emerald-100/80 border border-emerald-100 text-emerald-600 transition-colors group"
      >
        <span class="text-base group-hover:scale-110 transition-transform">➕</span>
        <span class="text-[9px] font-extrabold mt-1 text-slate-700 text-center leading-tight">Add Product</span>
      </router-link>

      <!-- Add Customer -->
      <router-link
        to="/admin/customers"
        class="flex flex-col items-center justify-center p-2 rounded-xl bg-purple-50/60 hover:bg-purple-100/80 border border-purple-100 text-purple-600 transition-colors group"
      >
        <span class="text-base group-hover:scale-110 transition-transform">👤</span>
        <span class="text-[9px] font-extrabold mt-1 text-slate-700 text-center leading-tight">Add Customer</span>
      </router-link>

      <!-- New PO -->
      <router-link
        to="/admin/procurement/orders"
        class="flex flex-col items-center justify-center p-2 rounded-xl bg-blue-50/60 hover:bg-blue-100/80 border border-blue-100 text-blue-600 transition-colors group"
      >
        <span class="text-base group-hover:scale-110 transition-transform">🛒</span>
        <span class="text-[9px] font-extrabold mt-1 text-slate-700 text-center leading-tight">New PO</span>
      </router-link>

      <!-- Stock Transfer -->
      <router-link
        to="/admin/inventory/transfers"
        class="flex flex-col items-center justify-center p-2 rounded-xl bg-amber-50/60 hover:bg-amber-100/80 border border-amber-100 text-amber-600 transition-colors group"
      >
        <span class="text-base group-hover:scale-110 transition-transform">🚚</span>
        <span class="text-[9px] font-extrabold mt-1 text-slate-700 text-center leading-tight">Stock Transfer</span>
      </router-link>

      <!-- Add Expense -->
      <router-link
        to="/admin/expenses"
        class="flex flex-col items-center justify-center p-2 rounded-xl bg-red-50/60 hover:bg-red-100/80 border border-red-100 text-red-600 transition-colors group"
      >
        <span class="text-base group-hover:scale-110 transition-transform">💸</span>
        <span class="text-[9px] font-extrabold mt-1 text-slate-700 text-center leading-tight">Add Expense</span>
      </router-link>
    </div>
  </div>
</template>

<script setup>
</script>
