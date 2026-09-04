<template>
  <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6 antialiased font-sans">
    <!-- Greeting & Title -->
    <div>
      <h1 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2">
        <span>Good morning, {{ displayGreetingName }}!</span>
        <span class="inline-block animate-bounce">👋</span>
      </h1>
      <p class="text-xs text-slate-500 font-medium mt-0.5">
        Real-time ERP Analytics & Executive Dashboard
      </p>
    </div>

    <!-- Actions Toolbar & Filters -->
    <div class="flex items-center gap-2.5 flex-wrap">
      <!-- Store Filter Selector -->
      <div class="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 shadow-xs">
        <span>🏢</span>
        <select
          v-model="selectedStoreId"
          @change="emitFilterChange"
          class="bg-transparent text-xs font-bold text-slate-900 focus:outline-none cursor-pointer"
        >
          <option :value="null">All Outlets</option>
          <option v-for="st in stores" :key="st.id" :value="st.id">
            {{ st.name }}
          </option>
        </select>
      </div>

      <!-- Date Range Preset Selector -->
      <div class="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 shadow-xs">
        <span>📅</span>
        <select
          v-model="selectedPeriod"
          @change="emitFilterChange"
          class="bg-transparent text-xs font-bold text-slate-900 focus:outline-none cursor-pointer"
        >
          <option value="today">Today</option>
          <option value="yesterday">Yesterday</option>
          <option value="current_week">This Week</option>
          <option value="current_month">This Month</option>
          <option value="current_year">This Year</option>
          <option value="custom">Custom Date Range</option>
        </select>
      </div>

      <!-- Custom Date Inputs if Custom Selected -->
      <div v-if="selectedPeriod === 'custom'" class="flex items-center gap-1.5">
        <input
          type="date"
          v-model="startDate"
          @change="emitFilterChange"
          class="px-2 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-900 shadow-xs focus:outline-none"
        />
        <span class="text-xs text-slate-400 font-bold">to</span>
        <input
          type="date"
          v-model="endDate"
          @change="emitFilterChange"
          class="px-2 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-900 shadow-xs focus:outline-none"
        />
      </div>

      <!-- Refresh Button -->
      <button
        @click="$emit('refresh')"
        :disabled="refreshing"
        class="flex items-center gap-1.5 px-3 py-2 bg-white hover:bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 shadow-xs transition-colors disabled:opacity-50 cursor-pointer"
      >
        <span :class="['inline-block', refreshing ? 'animate-spin' : '']">🔄</span>
        <span>Refresh</span>
      </button>

      <!-- Open POS CTA -->
      <router-link
        to="/admin/pos"
        class="flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-extrabold shadow-md shadow-red-600/20 transition-all uppercase tracking-wider"
      >
        <span>🛒</span>
        <span>Open POS</span>
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '../../stores/authStore';
import api from '../../services/api';

const authStore = useAuthStore();

const props = defineProps({
  userName: { type: String, default: '' },
  refreshing: { type: Boolean, default: false },
});

const emit = defineEmits(['refresh', 'filter-change']);

const stores = ref([]);
const selectedStoreId = ref(null);
const selectedPeriod = ref('current_month');
const startDate = ref('');
const endDate = ref('');

const displayGreetingName = computed(() => {
  if (authStore.isSuperAdmin) return 'Super Admin';
  return props.userName || authStore.user?.name || 'Admin User';
});

async function loadStores() {
  try {
    const res = await api.get('/stores');
    if (res.data) {
      stores.value = Array.isArray(res.data) ? res.data : (res.data.data || []);
    }
  } catch (e) {
    console.error('Failed to load stores for dashboard filter:', e);
  }
}

function emitFilterChange() {
  emit('filter-change', {
    store_id: selectedStoreId.value,
    period: selectedPeriod.value,
    start_date: startDate.value,
    end_date: endDate.value,
  });
}

onMounted(() => {
  loadStores();
});
</script>
