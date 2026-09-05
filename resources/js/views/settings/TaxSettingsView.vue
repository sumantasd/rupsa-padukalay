<template>
  <div class="space-y-6 max-w-5xl mx-auto pb-16 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 p-6 rounded-2xl border border-slate-800 shadow-xl">
      <div class="flex items-center gap-3">
        <div class="h-10 w-10 rounded-xl bg-emerald-600/20 text-emerald-400 flex items-center justify-center font-bold text-xl border border-emerald-500/30">
          📑
        </div>
        <div>
          <h1 class="text-xl font-extrabold text-white">GST & Tax Configuration</h1>
          <p class="text-xs text-slate-400 mt-0.5">
            Configure global GST enablement, CGST/SGST/IGST tax calculation modes & HSN tax slabs
          </p>
        </div>
      </div>
      <button
        @click="saveSettings"
        :disabled="saving"
        class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-red-600/20 transition-all cursor-pointer disabled:opacity-50 shrink-0"
      >
        <span v-if="saving" class="h-4 w-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
        <span>💾 Save Tax Settings</span>
      </button>
    </div>

    <!-- Main Tax Settings Card -->
    <div class="bg-slate-900/80 p-6 rounded-2xl border border-slate-800 space-y-6">
      <div class="border-b border-slate-800 pb-4 flex items-center justify-between">
        <div>
          <h2 class="font-extrabold text-sm text-white uppercase tracking-wider">Global GST Status</h2>
          <p class="text-[11px] text-slate-400">When enabled, GST calculations apply across POS sales, invoices, and purchase bills</p>
        </div>
        <label class="flex items-center gap-3 cursor-pointer">
          <span class="text-xs font-extrabold" :class="form.gst_enabled ? 'text-emerald-400' : 'text-slate-500'">
            {{ form.gst_enabled ? 'GST Enabled' : 'GST Disabled' }}
          </span>
          <input
            type="checkbox"
            v-model="form.gst_enabled"
            class="h-5 w-5 rounded border-slate-700 bg-slate-950 text-red-600 focus:ring-red-500 cursor-pointer"
          />
        </label>
      </div>

      <!-- Information Box -->
      <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 text-xs space-y-2 text-slate-300">
        <h3 class="font-bold text-white flex items-center gap-2">
          <span>ℹ️</span>
          <span>Footwear GST Slabs & Rules (India GST)</span>
        </h3>
        <ul class="list-disc list-inside text-slate-400 space-y-1 pl-1">
          <li>Footwear below ₹1,000 MRP: Standard 5% GST (2.5% CGST + 2.5% SGST)</li>
          <li>Footwear above ₹1,000 MRP: Standard 12% GST (6% CGST + 6% SGST)</li>
          <li>Inter-state transactions automatically calculate 100% IGST</li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '../../services/api';
import { useNotificationStore } from '../../stores/notificationStore';

const notificationStore = useNotificationStore();
const saving = ref(false);

const form = reactive({
  gst_enabled: true,
});

onMounted(async () => {
  try {
    const res = await api.get('/tax-settings');
    if (res.data) {
      form.gst_enabled = !!res.data.gst_enabled;
    }
  } catch (e) {
    console.error('Failed to fetch tax settings:', e);
  }
});

async function saveSettings() {
  saving.value = true;
  try {
    await api.put('/tax-settings', {
      gst_enabled: form.gst_enabled ? '1' : '0',
    });
    notificationStore.showNotification('Tax settings saved successfully.', 'success');
  } catch (e) {
    const msg = e.response?.data?.message || 'Failed to save tax settings.';
    notificationStore.showNotification(msg, 'error');
  } finally {
    saving.value = false;
  }
}
</script>
