<template>
  <div class="space-y-6 max-w-5xl mx-auto pb-16 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 p-6 rounded-2xl border border-slate-800 shadow-xl">
      <div class="flex items-center gap-3">
        <div class="h-10 w-10 rounded-xl bg-slate-800 text-slate-300 flex items-center justify-center font-bold text-xl border border-slate-700">
          🔧
        </div>
        <div>
          <h1 class="text-xl font-extrabold text-white">General Application Preferences</h1>
          <p class="text-xs text-slate-400 mt-0.5">
            Global system preferences, currency symbol, timezone & session timeout parameters
          </p>
        </div>
      </div>
      <button
        @click="saveSettings"
        :disabled="settingsStore.saving"
        class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-red-600/20 transition-all cursor-pointer disabled:opacity-50 shrink-0"
      >
        <span v-if="settingsStore.saving" class="h-4 w-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
        <span>💾 Save General Settings</span>
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="settingsStore.loading" class="py-12 flex justify-center items-center">
      <div class="flex items-center gap-3 text-slate-400 text-sm">
        <span class="h-5 w-5 border-2 border-red-500 border-t-transparent rounded-full animate-spin"></span>
        Loading general preferences...
      </div>
    </div>

    <!-- Settings Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- 1. Regional & Currency Settings -->
      <div class="bg-slate-900/80 p-6 rounded-2xl border border-slate-800 space-y-4">
        <div class="border-b border-slate-800 pb-3">
          <h2 class="font-extrabold text-sm text-white uppercase tracking-wider">Regional & Currency Formatting</h2>
          <p class="text-[11px] text-slate-400">Localization & currency display rules</p>
        </div>

        <div class="space-y-4 text-xs">
          <div>
            <label class="block font-bold text-slate-300 mb-1">Application Title *</label>
            <input
              v-model="form.app_title"
              type="text"
              class="w-full bg-slate-950 border border-slate-800 text-white font-bold rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-red-500"
            />
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Timezone *</label>
            <select
              v-model="form.timezone"
              class="w-full bg-slate-950 border border-slate-800 text-white font-bold rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-red-500"
            >
              <option value="Asia/Kolkata">Asia/Kolkata (IST +5:30)</option>
              <option value="UTC">UTC (Universal Coordinated Time)</option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Currency Symbol *</label>
              <input
                v-model="form.currency_symbol"
                type="text"
                class="w-full bg-slate-950 border border-slate-800 text-white font-bold rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-red-500"
              />
            </div>
            <div>
              <label class="block font-bold text-slate-300 mb-1">Currency Code *</label>
              <input
                v-model="form.currency_code"
                type="text"
                class="w-full bg-slate-950 border border-slate-800 text-white font-bold font-mono rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-red-500 uppercase"
              />
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-300 mb-1">Decimal Precision *</label>
            <select
              v-model.number="form.decimal_precision"
              class="w-full bg-slate-950 border border-slate-800 text-white font-bold rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-red-500"
            >
              <option :value="0">0 Decimals (e.g. ₹1250)</option>
              <option :value="2">2 Decimals (e.g. ₹1250.00)</option>
            </select>
          </div>
        </div>
      </div>

      <!-- 2. Security & Session Preferences -->
      <div class="bg-slate-900/80 p-6 rounded-2xl border border-slate-800 space-y-4">
        <div class="border-b border-slate-800 pb-3">
          <h2 class="font-extrabold text-sm text-white uppercase tracking-wider">Session Security & Notifications</h2>
          <p class="text-[11px] text-slate-400">Idle timeouts & alert settings</p>
        </div>

        <div class="space-y-4 text-xs">
          <div>
            <label class="block font-bold text-slate-300 mb-1">Session Inactivity Timeout (Minutes) *</label>
            <input
              v-model.number="form.session_timeout_minutes"
              type="number"
              min="15"
              max="1440"
              class="w-full bg-slate-950 border border-slate-800 text-white font-bold rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-red-500"
            />
          </div>

          <label class="flex items-center justify-between p-3 rounded-xl bg-slate-950 border border-slate-800/80 cursor-pointer">
            <div>
              <span class="font-bold text-slate-200 block">Browser Desktop Notifications</span>
              <span class="text-[10px] text-slate-500">Show native browser popups for critical stock alerts</span>
            </div>
            <input type="checkbox" v-model="form.enable_browser_notifications" class="rounded border-slate-700 bg-slate-950 text-red-600 focus:ring-red-500" />
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
  app_title: 'RUPSA PADUKALAYA ERP',
  timezone: 'Asia/Kolkata',
  currency_symbol: '₹',
  currency_code: 'INR',
  date_format: 'DD/MM/YYYY',
  time_format: '12h',
  decimal_precision: 2,
  default_language: 'en',
  session_timeout_minutes: 60,
  enable_email_notifications: true,
  enable_browser_notifications: true,
});

onMounted(async () => {
  try {
    const data = await settingsStore.fetchGeneralSettings();
    if (data) {
      Object.assign(form, data);
    }
  } catch (e) {
    console.error('Failed to load general settings:', e);
  }
});

async function saveSettings() {
  try {
    await settingsStore.saveGeneralSettings(form);
    notificationStore.showNotification('General settings saved successfully.', 'success');
  } catch (e) {
    const msg = e.response?.data?.message || 'Failed to save general settings.';
    notificationStore.showNotification(msg, 'error');
  }
}
</script>
