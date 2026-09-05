<template>
  <div class="space-y-6 max-w-5xl mx-auto pb-16 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 p-6 rounded-2xl border border-slate-800 shadow-xl">
      <div class="flex items-center gap-3">
        <div class="h-10 w-10 rounded-xl bg-teal-600/20 text-teal-400 flex items-center justify-center font-bold text-xl border border-teal-500/30">
          🔢
        </div>
        <div>
          <h1 class="text-xl font-extrabold text-white">Document Number Series Configuration</h1>
          <p class="text-xs text-slate-400 mt-0.5">
            Configure prefixes, sequential counters, and zero-padding lengths for transactional documents
          </p>
        </div>
      </div>
      <button
        @click="saveSeries"
        :disabled="settingsStore.saving"
        class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-red-600/20 transition-all cursor-pointer disabled:opacity-50 shrink-0"
      >
        <span v-if="settingsStore.saving" class="h-4 w-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
        <span>💾 Save Number Series</span>
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="settingsStore.loading" class="py-12 flex justify-center items-center">
      <div class="flex items-center gap-3 text-slate-400 text-sm">
        <span class="h-5 w-5 border-2 border-red-500 border-t-transparent rounded-full animate-spin"></span>
        Loading document series configuration...
      </div>
    </div>

    <!-- Series Grid -->
    <div v-else class="space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div
          v-for="(item, key) in seriesForm"
          :key="key"
          class="p-5 rounded-2xl border border-slate-800 bg-slate-900/80 space-y-4 hover:border-slate-700 transition-all shadow-lg"
        >
          <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 class="font-extrabold text-sm text-white flex items-center gap-2">
              <span>📄</span>
              <span>{{ item.name }}</span>
            </h3>
            <span class="px-2.5 py-1 text-[11px] font-mono font-black text-emerald-400 bg-slate-950 rounded-lg border border-slate-800">
              Sample: {{ computeSample(item) }}
            </span>
          </div>

          <div class="grid grid-cols-3 gap-3 text-xs">
            <div>
              <label class="block font-bold text-slate-400 mb-1">Prefix *</label>
              <input
                v-model="item.prefix"
                type="text"
                class="w-full bg-slate-950 border border-slate-800 text-white font-mono font-bold rounded-xl px-3 py-2 focus:outline-none focus:border-red-500 uppercase"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-400 mb-1">Current No. *</label>
              <input
                v-model.number="item.current_number"
                type="number"
                min="1"
                class="w-full bg-slate-950 border border-slate-800 text-white font-mono font-bold rounded-xl px-3 py-2 focus:outline-none focus:border-red-500"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-400 mb-1">Padding *</label>
              <input
                v-model.number="item.padding"
                type="number"
                min="3"
                max="10"
                class="w-full bg-slate-950 border border-slate-800 text-white font-mono font-bold rounded-xl px-3 py-2 focus:outline-none focus:border-red-500"
              />
            </div>
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

const seriesForm = reactive({});

onMounted(async () => {
  try {
    const data = await settingsStore.fetchNumberSeries();
    if (data) {
      Object.assign(seriesForm, JSON.parse(JSON.stringify(data)));
    }
  } catch (e) {
    console.error('Failed to load number series:', e);
  }
});

function computeSample(item) {
  if (!item) return '';
  const num = String(item.current_number || 1).padStart(item.padding || 6, '0');
  return (item.prefix || '') + num;
}

async function saveSeries() {
  try {
    await settingsStore.saveNumberSeries(seriesForm);
    notificationStore.showNotification('Number series saved successfully.', 'success');
  } catch (e) {
    const msg = e.response?.data?.message || 'Failed to save number series.';
    notificationStore.showNotification(msg, 'error');
  }
}
</script>
