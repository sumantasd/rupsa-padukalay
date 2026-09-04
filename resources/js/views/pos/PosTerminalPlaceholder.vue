<template>
  <div class="flex-1 flex w-full h-full overflow-hidden">
    <!-- Left Pane: Barcode Search & Catalog Grid -->
    <div class="w-7/12 border-r border-slate-800 p-4 flex flex-col gap-4 bg-slate-950">
      <div class="flex items-center gap-2">
        <input
          type="text"
          placeholder="Scan barcode or type SKU / article name... (F2)"
          class="flex-1 bg-slate-900 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono"
        />
        <Button variant="secondary" size="md">Search</Button>
      </div>

      <!-- Catalog Grid Placeholder -->
      <div class="flex-1 overflow-y-auto grid grid-cols-3 gap-3">
        <div v-for="i in 6" :key="i" class="p-3 bg-slate-900 border border-slate-800 rounded-xl hover:border-indigo-500 cursor-pointer transition-colors">
          <div class="h-20 bg-slate-800 rounded-lg flex items-center justify-center text-xl">👟</div>
          <h4 class="text-xs font-bold text-slate-200 mt-2">Men Formal Shoe</h4>
          <p class="text-[10px] text-slate-400 font-mono">ART-805 | Size 08</p>
          <div class="text-xs font-black text-indigo-400 mt-1">₹1,792.00</div>
        </div>
      </div>
    </div>

    <!-- Right Pane: Cart & Payment Action Drawer -->
    <div class="w-5/12 p-4 flex flex-col justify-between bg-slate-900 border-l border-slate-800">
      <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <h3 class="font-bold text-sm text-white">Current POS Order Cart</h3>
          <Badge variant="info">Session #14 Active</Badge>
        </div>

        <div class="space-y-2">
          <div class="p-3 bg-slate-950 rounded-lg border border-slate-800 flex items-center justify-between">
            <div>
              <div class="text-xs font-bold text-white">Men Formal Shoe (Black / 08)</div>
              <div class="text-[10px] text-slate-400 font-mono">Qty: 1 x ₹1,600.00 (+12% GST)</div>
            </div>
            <div class="text-xs font-bold text-indigo-400">₹1,792.00</div>
          </div>
        </div>
      </div>

      <!-- Financial Totals Block -->
      <div class="border-t border-slate-800 pt-4 space-y-2">
        <div class="flex justify-between text-xs text-slate-400">
          <span>Subtotal:</span>
          <span>₹1,600.00</span>
        </div>
        <div class="flex justify-between text-xs text-slate-400">
          <span>GST (Total Tax):</span>
          <span>₹192.00</span>
        </div>
        <div class="flex justify-between text-base font-black text-white pt-2 border-t border-slate-800">
          <span>Grand Total:</span>
          <span class="text-emerald-400">₹1,792.00</span>
        </div>

        <Button variant="primary" block size="lg" class="mt-4">
          Proceed to Checkout (F12)
        </Button>
      </div>
    </div>
  </div>
</template>

<script setup>
import Button from '../../components/ui/Button.vue';
import Badge from '../../components/ui/Badge.vue';
</script>
