<template>
  <div class="space-y-6 max-w-5xl mx-auto pb-16 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 p-6 rounded-2xl border border-slate-800 shadow-xl">
      <div class="flex items-center gap-3">
        <div class="h-10 w-10 rounded-xl bg-blue-600/20 text-blue-400 flex items-center justify-center font-bold text-xl border border-blue-500/30">
          🧾
        </div>
        <div>
          <h1 class="text-xl font-extrabold text-white">Invoice & Receipt Template Settings</h1>
          <p class="text-xs text-slate-400 mt-0.5">
            Configure invoice prefixes, thermal receipt layout, terms & conditions, and item display rules
          </p>
        </div>
      </div>
      <button
        @click="saveSettings"
        :disabled="settingsStore.saving"
        class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-red-600/20 transition-all cursor-pointer disabled:opacity-50 shrink-0"
      >
        <span v-if="settingsStore.saving" class="h-4 w-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
        <span>💾 Save Invoice Settings</span>
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="settingsStore.loading" class="py-12 flex justify-center items-center">
      <div class="flex items-center gap-3 text-slate-400 text-sm">
        <span class="h-5 w-5 border-2 border-red-500 border-t-transparent rounded-full animate-spin"></span>
        Loading invoice settings...
      </div>
    </div>

    <!-- Settings Form Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- 1. Header & Numbering Rules -->
      <div class="bg-slate-900/80 p-6 rounded-2xl border border-slate-800 space-y-4">
        <div class="border-b border-slate-800 pb-3">
          <h2 class="font-extrabold text-sm text-white uppercase tracking-wider">Invoice Header & Numbering Format</h2>
          <p class="text-[11px] text-slate-400">Controls receipt title, series prefix, and date format</p>
        </div>

        <div class="space-y-4 text-xs">
          <div>
            <label class="block font-bold text-slate-300 mb-1">Invoice Document Title *</label>
            <input
              v-model="form.invoice_title"
              type="text"
              placeholder="TAX INVOICE"
              class="w-full bg-slate-950 border border-slate-800 text-white font-bold rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-red-500"
            />
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Invoice Prefix *</label>
            <input
              v-model="form.invoice_prefix"
              type="text"
              placeholder="INV-"
              class="w-full bg-slate-950 border border-slate-800 text-white font-mono font-bold rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-red-500 uppercase"
            />
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Date Format *</label>
            <select
              v-model="form.date_format"
              class="w-full bg-slate-950 border border-slate-800 text-white font-medium rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-red-500"
            >
              <option value="DD/MM/YYYY">DD/MM/YYYY (e.g. 05/09/2026)</option>
              <option value="YYYY-MM-DD">YYYY-MM-DD (e.g. 2026-09-05)</option>
              <option value="DD MMM YYYY">DD MMM YYYY (e.g. 05 Sep 2026)</option>
            </select>
          </div>
        </div>
      </div>

      <!-- 2. Display Toggles & Visibility -->
      <div class="bg-slate-900/80 p-6 rounded-2xl border border-slate-800 space-y-4">
        <div class="border-b border-slate-800 pb-3">
          <h2 class="font-extrabold text-sm text-white uppercase tracking-wider">Item & Customer Field Display</h2>
          <p class="text-[11px] text-slate-400">Toggle visible sections on customer receipts</p>
        </div>

        <div class="space-y-3 text-xs">
          <label class="flex items-center justify-between p-3 rounded-xl bg-slate-950 border border-slate-800/80 cursor-pointer">
            <span class="font-bold text-slate-300">Show Customer Phone & Details</span>
            <input type="checkbox" v-model="form.show_customer_phone" class="rounded border-slate-700 bg-slate-900 text-red-600 focus:ring-red-500" />
          </label>

          <label class="flex items-center justify-between p-3 rounded-xl bg-slate-950 border border-slate-800/80 cursor-pointer">
            <span class="font-bold text-slate-300">Show Article SKU / Code</span>
            <input type="checkbox" v-model="form.show_item_sku" class="rounded border-slate-700 bg-slate-900 text-red-600 focus:ring-red-500" />
          </label>

          <label class="flex items-center justify-between p-3 rounded-xl bg-slate-950 border border-slate-800/80 cursor-pointer">
            <span class="font-bold text-slate-300">Show Footwear Size Tag</span>
            <input type="checkbox" v-model="form.show_item_size" class="rounded border-slate-700 bg-slate-900 text-red-600 focus:ring-red-500" />
          </label>

          <label class="flex items-center justify-between p-3 rounded-xl bg-slate-950 border border-slate-800/80 cursor-pointer">
            <span class="font-bold text-slate-300">Show Detailed GST Tax Breakdown</span>
            <input type="checkbox" v-model="form.show_tax_breakdown" class="rounded border-slate-700 bg-slate-900 text-red-600 focus:ring-red-500" />
          </label>

          <label class="flex items-center justify-between p-3 rounded-xl bg-slate-950 border border-slate-800/80 cursor-pointer">
            <span class="font-bold text-slate-300">Show Savings / Discount Summary</span>
            <input type="checkbox" v-model="form.show_discount_summary" class="rounded border-slate-700 bg-slate-900 text-red-600 focus:ring-red-500" />
          </label>
        </div>
      </div>

      <!-- 3. Footer Note & Terms -->
      <div class="bg-slate-900/80 p-6 rounded-2xl border border-slate-800 space-y-4 md:col-span-2">
        <div class="border-b border-slate-800 pb-3">
          <h2 class="font-extrabold text-sm text-white uppercase tracking-wider">Footer Notes & Return Policy</h2>
          <p class="text-[11px] text-slate-400">Printed at the bottom of customer sales invoices</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
          <div>
            <label class="block font-bold text-slate-300 mb-1">Footer Greeting Note</label>
            <textarea
              v-model="form.footer_note"
              rows="3"
              placeholder="Thank you for shopping with RUPSA PADUKALAYA!"
              class="w-full bg-slate-950 border border-slate-800 text-white rounded-xl p-3 focus:outline-none focus:border-red-500"
            ></textarea>
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Terms & Return Policy</label>
            <textarea
              v-model="form.terms_conditions"
              rows="3"
              placeholder="1. Exchange within 7 days with bill. 2. No cash refund."
              class="w-full bg-slate-950 border border-slate-800 text-white rounded-xl p-3 focus:outline-none focus:border-red-500"
            ></textarea>
          </div>
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
  invoice_prefix: 'INV-',
  invoice_title: 'TAX INVOICE',
  date_format: 'DD/MM/YYYY',
  show_customer_phone: true,
  show_customer_address: true,
  show_item_sku: true,
  show_item_size: true,
  show_item_color: false,
  show_tax_breakdown: true,
  show_discount_summary: true,
  footer_note: '',
  terms_conditions: '',
  signature_label: 'Authorized Signatory',
  enable_signature: true,
});

onMounted(async () => {
  try {
    const data = await settingsStore.fetchInvoiceSettings();
    if (data) {
      Object.assign(form, data);
    }
  } catch (e) {
    console.error('Failed to load invoice settings:', e);
  }
});

async function saveSettings() {
  try {
    await settingsStore.saveInvoiceSettings(form);
    notificationStore.showNotification('Invoice settings saved successfully.', 'success');
  } catch (e) {
    const msg = e.response?.data?.message || 'Failed to save invoice settings.';
    notificationStore.showNotification(msg, 'error');
  }
}
</script>
