<template>
  <div class="space-y-6 max-w-4xl mx-auto pb-16 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">⚙️ Inventory & Low Stock Settings</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Configure default low-stock alert thresholds, out-of-stock triggers, dashboard bell notification preferences for STR-001
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          @click="saveSettings"
          :disabled="saving"
          class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md shadow-red-600/20 transition-all uppercase tracking-wider flex items-center gap-1.5 disabled:opacity-50"
        >
          <span>💾</span>
          <span>{{ saving ? 'Saving Settings...' : 'Save Settings' }}</span>
        </button>
      </div>
    </div>

    <!-- Feedback Toast Notification -->
    <div v-if="toastMessage" class="p-4 bg-emerald-50 border-2 border-emerald-300 text-emerald-900 rounded-2xl font-extrabold text-xs flex items-center justify-between shadow-xs">
      <div class="flex items-center gap-2">
        <span>✅</span>
        <span>{{ toastMessage }}</span>
      </div>
      <button @click="toastMessage = ''" class="text-emerald-700 hover:text-emerald-950 font-black">✕</button>
    </div>

    <div v-if="loading" class="p-12 text-center text-slate-400 font-bold">
      Loading inventory settings...
    </div>

    <div v-else class="space-y-6">
      <!-- GLOBAL THRESHOLDS CARD -->
      <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-xs space-y-5">
        <div class="border-b border-slate-100 pb-3">
          <h2 class="text-sm font-black text-slate-900 flex items-center gap-2">
            <span>📦</span>
            <span>Default Low Stock & Out of Stock Thresholds</span>
          </h2>
          <p class="text-xs text-slate-500 font-medium mt-0.5">
            Set the default minimum stock quantities that trigger Low Stock and Out of Stock alerts across footwear SKUs.
          </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 text-xs">
          <!-- Default Low Stock Threshold -->
          <div class="space-y-1.5">
            <label class="block font-black text-slate-800">Default Low Stock Threshold (Pairs)</label>
            <input
              type="number"
              v-model.number="form.default_low_stock_threshold"
              min="1"
              max="1000"
              class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl font-mono font-black text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500/20"
            />
            <p class="text-[11px] text-slate-500 font-medium">
              SKUs with stock quantity <strong>&le; {{ form.default_low_stock_threshold }}</strong> will be marked as <strong>LOW STOCK</strong> (unless a custom SKU threshold override is set).
            </p>
          </div>

          <!-- Default Out of Stock Threshold -->
          <div class="space-y-1.5">
            <label class="block font-black text-slate-800">Default Out of Stock Threshold (Pairs)</label>
            <input
              type="number"
              v-model.number="form.default_out_of_stock_threshold"
              min="0"
              max="100"
              class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl font-mono font-black text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500/20"
            />
            <p class="text-[11px] text-slate-500 font-medium">
              SKUs with stock quantity <strong>&le; {{ form.default_out_of_stock_threshold }}</strong> will be marked as <strong>OUT OF STOCK</strong>.
            </p>
          </div>
        </div>

        <!-- EXAMPLE COMPUTED CARD -->
        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 text-xs space-y-2">
          <div class="font-black text-slate-900 uppercase text-[10px] tracking-wider text-slate-500">Live Rule Evaluation Example:</div>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 font-mono font-bold text-[11px]">
            <div class="p-2 bg-white rounded-xl border border-slate-200 text-emerald-700">Stock {{ form.default_low_stock_threshold + 5 }} &rarr; Normal</div>
            <div class="p-2 bg-white rounded-xl border border-slate-200 text-amber-800">Stock {{ form.default_low_stock_threshold }} &rarr; LOW STOCK</div>
            <div class="p-2 bg-white rounded-xl border border-slate-200 text-amber-800">Stock 1 &rarr; LOW STOCK</div>
            <div class="p-2 bg-white rounded-xl border border-slate-200 text-red-700">Stock 0 &rarr; OUT OF STOCK</div>
          </div>
        </div>
      </div>

      <!-- NOTIFICATION PREFERENCES CARD -->
      <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-xs space-y-5 text-xs">
        <div class="border-b border-slate-100 pb-3">
          <h2 class="text-sm font-black text-slate-900 flex items-center gap-2">
            <span>🔔</span>
            <span>Alert & Notification System Preferences</span>
          </h2>
          <p class="text-xs text-slate-500 font-medium mt-0.5">
            Enable or disable automatic alert generation and header bell dropdown notifications.
          </p>
        </div>

        <div class="space-y-4">
          <!-- Enable Low Stock Alerts -->
          <label class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-200 cursor-pointer">
            <div>
              <div class="font-black text-slate-900">Enable Low Stock Alert Engine</div>
              <div class="text-slate-500 font-medium text-[11px]">Automatically evaluate low stock and out-of-stock states during POS sales, purchases, returns & adjustments</div>
            </div>
            <input type="checkbox" v-model="form.enable_low_stock_alerts" class="w-5 h-5 accent-red-600 rounded" />
          </label>

          <!-- Enable Dashboard Notifications -->
          <label class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-200 cursor-pointer">
            <div>
              <div class="font-black text-slate-900">Enable Dashboard Bell Notifications</div>
              <div class="text-slate-500 font-medium text-[11px]">Display real-time unread badges and drop-down notification cards in the top header navbar</div>
            </div>
            <input type="checkbox" v-model="form.enable_dashboard_notifications" class="w-5 h-5 accent-red-600 rounded" />
          </label>

          <!-- Enable Notification Sound -->
          <label class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-200 cursor-pointer">
            <div>
              <div class="font-black text-slate-900">Enable Notification Audio Chime</div>
              <div class="text-slate-500 font-medium text-[11px]">Play audio alert when new critical out-of-stock notification arrives</div>
            </div>
            <input type="checkbox" v-model="form.enable_sound_notifications" class="w-5 h-5 accent-red-600 rounded" />
          </label>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '../../services/api';

const loading = ref(false);
const saving = ref(false);
const toastMessage = ref('');

const form = reactive({
  enable_low_stock_alerts: true,
  default_low_stock_threshold: 5,
  default_out_of_stock_threshold: 0,
  enable_dashboard_notifications: true,
  enable_sound_notifications: false,
});

async function fetchSettings() {
  loading.value = true;
  try {
    const res = await api.get('/settings/inventory');
    const data = res.data?.data || res.data;
    if (data) {
      Object.assign(form, data);
    }
  } catch (err) {
    console.error('Failed to load inventory settings:', err);
  } finally {
    loading.value = false;
  }
}

async function saveSettings() {
  saving.value = true;
  toastMessage.value = '';
  try {
    const res = await api.post('/settings/inventory', form);
    const data = res.data?.data || res.data;
    if (data) {
      Object.assign(form, data);
    }
    toastMessage.value = 'Low stock threshold settings updated successfully! All future stock movements will use the new rules.';
    setTimeout(() => {
      toastMessage.value = '';
    }, 5000);
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to save inventory settings.');
  } finally {
    saving.value = false;
  }
}

onMounted(() => {
  fetchSettings();
});
</script>
