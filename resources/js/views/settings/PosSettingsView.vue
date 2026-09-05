<template>
  <div class="space-y-6 max-w-5xl mx-auto pb-16 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 p-6 rounded-2xl border border-slate-800 shadow-xl">
      <div class="flex items-center gap-3">
        <div class="h-10 w-10 rounded-xl bg-orange-600/20 text-orange-400 flex items-center justify-center font-bold text-xl border border-orange-500/30">
          ⚙️
        </div>
        <div>
          <h1 class="text-xl font-extrabold text-white">POS Terminal Operational Settings</h1>
          <p class="text-xs text-slate-400 mt-0.5">
            Configure register behavior, cashier workflow controls, discount caps & negative stock safety rules
          </p>
        </div>
      </div>
      <button
        @click="saveSettings"
        :disabled="settingsStore.saving"
        class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-red-600/20 transition-all cursor-pointer disabled:opacity-50 shrink-0"
      >
        <span v-if="settingsStore.saving" class="h-4 w-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
        <span>💾 Save POS Settings</span>
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="settingsStore.loading" class="py-12 flex justify-center items-center">
      <div class="flex items-center gap-3 text-slate-400 text-sm">
        <span class="h-5 w-5 border-2 border-red-500 border-t-transparent rounded-full animate-spin"></span>
        Loading POS terminal settings...
      </div>
    </div>

    <!-- Settings Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- 1. Stock & Discount Caps -->
      <div class="bg-slate-900/80 p-6 rounded-2xl border border-slate-800 space-y-4">
        <div class="border-b border-slate-800 pb-3">
          <h2 class="font-extrabold text-sm text-white uppercase tracking-wider">Inventory & Discount Controls</h2>
          <p class="text-[11px] text-slate-400">Rules for stock validation & cashier discount caps</p>
        </div>

        <div class="space-y-4 text-xs">
          <label class="flex items-center justify-between p-3 rounded-xl bg-slate-950 border border-slate-800/80 cursor-pointer">
            <div>
              <span class="font-bold text-slate-200 block">Allow Billing on Negative Stock</span>
              <span class="text-[10px] text-slate-500">Permit sale completion when inventory balance is zero</span>
            </div>
            <input type="checkbox" v-model="form.allow_negative_stock" class="rounded border-slate-700 bg-slate-950 text-red-600 focus:ring-red-500" />
          </label>

          <label class="flex items-center justify-between p-3 rounded-xl bg-slate-950 border border-slate-800/80 cursor-pointer">
            <div>
              <span class="font-bold text-slate-200 block">Require Customer Selection</span>
              <span class="text-[10px] text-slate-500">Mandate customer selection before bill settlement</span>
            </div>
            <input type="checkbox" v-model="form.require_customer_details" class="rounded border-slate-700 bg-slate-950 text-red-600 focus:ring-red-500" />
          </label>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Maximum Cashier Discount Cap (%) *</label>
            <input
              v-model.number="form.max_discount_percentage"
              type="number"
              min="0"
              max="100"
              class="w-full bg-slate-950 border border-slate-800 text-white font-bold rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-red-500"
            />
            <span class="text-[10px] text-slate-500 mt-1 block">Maximum manual percentage discount cashier can apply</span>
          </div>
        </div>
      </div>

      <!-- 2. Terminal Workflow & Auto-Print -->
      <div class="bg-slate-900/80 p-6 rounded-2xl border border-slate-800 space-y-4">
        <div class="border-b border-slate-800 pb-3">
          <h2 class="font-extrabold text-sm text-white uppercase tracking-wider">Register & Print Automation</h2>
          <p class="text-[11px] text-slate-400">Session float rules & receipt triggers</p>
        </div>

        <div class="space-y-3 text-xs">
          <label class="flex items-center justify-between p-3 rounded-xl bg-slate-950 border border-slate-800/80 cursor-pointer">
            <div>
              <span class="font-bold text-slate-200 block">Require Opening Cash Float Entry</span>
              <span class="text-[10px] text-slate-500">Mandate cash drawer count when starting POS session</span>
            </div>
            <input type="checkbox" v-model="form.require_session_opening_float" class="rounded border-slate-700 bg-slate-950 text-red-600 focus:ring-red-500" />
          </label>

          <label class="flex items-center justify-between p-3 rounded-xl bg-slate-950 border border-slate-800/80 cursor-pointer">
            <div>
              <span class="font-bold text-slate-200 block">Auto-Print Receipt Upon Settlement</span>
              <span class="text-[10px] text-slate-500">Trigger thermal printer immediately when bill is paid</span>
            </div>
            <input type="checkbox" v-model="form.auto_print_receipt_on_settle" class="rounded border-slate-700 bg-slate-950 text-red-600 focus:ring-red-500" />
          </label>

          <label class="flex items-center justify-between p-3 rounded-xl bg-slate-950 border border-slate-800/80 cursor-pointer">
            <div>
              <span class="font-bold text-slate-200 block">Barcode Auto-Add to Cart</span>
              <span class="text-[10px] text-slate-500">Add article immediately on barcode scanner trigger</span>
            </div>
            <input type="checkbox" v-model="form.barcode_auto_add_to_cart" class="rounded border-slate-700 bg-slate-950 text-red-600 focus:ring-red-500" />
          </label>

          <label class="flex items-center justify-between p-3 rounded-xl bg-slate-950 border border-slate-800/80 cursor-pointer">
            <div>
              <span class="font-bold text-slate-200 block">Enable POS Beep & Sound Effects</span>
              <span class="text-[10px] text-slate-500">Play audio chime on item scan and checkout</span>
            </div>
            <input type="checkbox" v-model="form.enable_sound_effects" class="rounded border-slate-700 bg-slate-950 text-red-600 focus:ring-red-500" />
          </label>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useSettingsStore } from '../../stores/settingsStore';
import { useNotificationStore } from '../../stores/notificationStore';

const settingsStore = useSettingsStore();
const notificationStore = useNotificationStore();

const form = reactive({
  allow_negative_stock: false,
  require_customer_details: false,
  allow_manual_item_discount: true,
  allow_bill_level_discount: true,
  max_discount_percentage: 20,
  require_session_opening_float: true,
  auto_print_receipt_on_settle: true,
  enable_sound_effects: true,
  barcode_auto_add_to_cart: true,
  enable_quick_cash_buttons: true,
  holding_cart_limit: 10,
  cashier_can_void_item: true,
});

onMounted(async () => {
  try {
    const data = await settingsStore.fetchPosSettings();
    if (data) {
      Object.assign(form, data);
    }
  } catch (e) {
    console.error('Failed to load POS settings:', e);
  }
});

async function saveSettings() {
  try {
    await settingsStore.savePosSettings(form);
    notificationStore.showNotification('POS operational settings saved successfully.', 'success');
  } catch (e) {
    const msg = e.response?.data?.message || 'Failed to save POS settings.';
    notificationStore.showNotification(msg, 'error');
  }
}
</script>
