<template>
  <div class="space-y-6 antialiased font-sans">
    
    <!-- Top Action Bar & Filters -->
    <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-4">
      <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
        <div class="relative w-full sm:w-64">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
          <input
            v-model="searchQuery"
            @input="fetchCharts"
            type="text"
            placeholder="Search size charts..."
            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white transition-all"
          />
        </div>

        <select
          v-model="categoryFilter"
          @change="fetchCharts"
          class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600"
        >
          <option value="">All Categories</option>
          <option v-for="cat in categories" :key="cat.id" :value="cat.id">
            {{ cat.name }}
          </option>
        </select>

        <select
          v-model="genderFilter"
          @change="fetchCharts"
          class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600"
        >
          <option value="">All Genders</option>
          <option value="unisex">Unisex</option>
          <option value="men">Men</option>
          <option value="women">Women</option>
          <option value="kids">Kids</option>
        </select>
      </div>

      <div class="flex items-center gap-3 w-full sm:w-auto justify-between sm:justify-end">
        <div class="text-xs font-black text-slate-700">
          {{ charts.length }} Size Chart(s)
        </div>

        <button
          @click="openCreateModal"
          class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-black shadow-md shadow-red-600/20 transition-all flex items-center gap-2"
        >
          <span>➕</span>
          <span>Add Size Chart</span>
        </button>
      </div>
    </div>

    <!-- Desktop Table / Mobile Grid Cards -->
    <div v-if="loading" class="bg-white p-12 rounded-2xl border border-slate-200 text-center text-slate-400 font-bold animate-pulse">
      Loading size charts...
    </div>

    <div v-else-if="charts.length === 0" class="bg-white p-12 rounded-2xl border border-slate-200 text-center text-slate-400">
      <p class="font-bold text-slate-700">No size charts configured yet.</p>
      <p class="text-xs text-slate-400 mt-1">Click "+ Add Size Chart" to configure footwear sizing matrices.</p>
    </div>

    <div v-else class="space-y-4">
      <!-- Desktop Table -->
      <div class="hidden lg:block bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
        <table class="w-full text-left text-xs border-collapse">
          <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-wider">
            <tr>
              <th class="px-5 py-3.5">Chart Name</th>
              <th class="px-5 py-3.5">Category</th>
              <th class="px-5 py-3.5">Gender / Unit</th>
              <th class="px-5 py-3.5">Structure</th>
              <th class="px-5 py-3.5">Status</th>
              <th class="px-5 py-3.5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
            <tr v-for="chart in charts" :key="chart.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="px-5 py-3.5">
                <div class="flex items-center gap-2">
                  <span class="font-black text-slate-900 text-sm">{{ chart.name }}</span>
                  <span v-if="chart.is_default" class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-black uppercase border border-amber-200">
                    ⭐ Default
                  </span>
                </div>
                <p class="text-[11px] text-slate-400 font-normal truncate max-w-xs" v-if="chart.description">
                  {{ chart.description }}
                </p>
              </td>
              <td class="px-5 py-3.5 font-bold text-slate-800">
                {{ chart.category_name || 'All Categories' }}
              </td>
              <td class="px-5 py-3.5">
                <span class="px-2 py-1 rounded-md bg-slate-100 text-slate-700 font-bold text-[10px] uppercase border border-slate-200">
                  {{ chart.gender || 'Unisex' }} • {{ chart.unit || 'CM' }}
                </span>
              </td>
              <td class="px-5 py-3.5 font-mono text-slate-600 font-bold">
                {{ chart.column_count }} Cols × {{ chart.row_count }} Sizes
              </td>
              <td class="px-5 py-3.5">
                <button
                  @click="toggleStatus(chart)"
                  :class="chart.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'"
                  class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase border transition-colors"
                >
                  {{ chart.is_active ? 'Active' : 'Inactive' }}
                </button>
              </td>
              <td class="px-5 py-3.5 text-right space-x-1.5">
                <button
                  @click="previewChart(chart)"
                  class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg font-bold text-[11px] transition-colors"
                >
                  👁️ Preview
                </button>
                <button
                  @click="editChart(chart)"
                  class="px-2.5 py-1 bg-slate-800 hover:bg-slate-900 text-white rounded-lg font-bold text-[11px] transition-colors"
                >
                  ✏️ Edit
                </button>
                <button
                  @click="duplicateChart(chart)"
                  class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg font-bold text-[11px] transition-colors"
                >
                  📑 Clone
                </button>
                <button
                  v-if="!chart.is_default"
                  @click="setDefault(chart)"
                  class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 rounded-lg font-bold text-[11px] transition-colors"
                >
                  ⭐ Default
                </button>
                <button
                  @click="confirmDelete(chart)"
                  class="px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 rounded-lg font-bold text-[11px] transition-colors"
                >
                  🗑️
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Cards Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:hidden gap-4">
        <div
          v-for="chart in charts"
          :key="chart.id"
          class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-3"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <div class="flex items-center gap-2 flex-wrap">
                <h4 class="font-black text-slate-900 text-base">{{ chart.name }}</h4>
                <span v-if="chart.is_default" class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-black uppercase border border-amber-200">
                  ⭐ Default
                </span>
              </div>
              <p class="text-xs text-slate-500 font-medium mt-0.5" v-if="chart.description">
                {{ chart.description }}
              </p>
            </div>
            <button
              @click="toggleStatus(chart)"
              :class="chart.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'"
              class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase border shrink-0"
            >
              {{ chart.is_active ? 'Active' : 'Inactive' }}
            </button>
          </div>

          <div class="flex items-center gap-3 text-xs text-slate-600 font-bold bg-slate-50 p-2.5 rounded-xl border border-slate-100">
            <span>Category: <strong class="text-slate-900">{{ chart.category_name || 'Global' }}</strong></span>
            <span>•</span>
            <span>{{ chart.gender || 'Unisex' }} ({{ chart.unit || 'CM' }})</span>
            <span>•</span>
            <span>{{ chart.row_count }} Sizes</span>
          </div>

          <div class="flex flex-wrap items-center justify-end gap-2 pt-2 border-t border-slate-100">
            <button
              @click="previewChart(chart)"
              class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl font-bold text-xs"
            >
              👁️ Preview
            </button>
            <button
              @click="editChart(chart)"
              class="px-3 py-1.5 bg-slate-800 hover:bg-slate-900 text-white rounded-xl font-bold text-xs"
            >
              ✏️ Edit
            </button>
            <button
              @click="duplicateChart(chart)"
              class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl font-bold text-xs"
            >
              📑 Clone
            </button>
            <button
              @click="confirmDelete(chart)"
              class="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 rounded-xl font-bold text-xs"
            >
              🗑️ Delete
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Size Chart Builder Modal -->
    <SizeChartBuilderModal
      :isOpen="isModalOpen"
      :chartId="editingChartId"
      :categories="categories"
      @close="isModalOpen = false"
      @saved="fetchCharts"
    />

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../services/api';
import { useToast } from '../../composables/useToast';
import SizeChartBuilderModal from './SizeChartBuilderModal.vue';

const toast = useToast();

const charts = ref([]);
const categories = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const categoryFilter = ref('');
const genderFilter = ref('');

const isModalOpen = ref(false);
const editingChartId = ref(null);

onMounted(() => {
  fetchCategories();
  fetchCharts();
});

async function fetchCategories() {
  try {
    const res = await api.get('/categories');
    categories.value = res.data || [];
  } catch (err) {
    console.error('Failed to load categories:', err);
  }
}

async function fetchCharts() {
  loading.value = true;
  try {
    const params = {};
    if (searchQuery.value) params.search = searchQuery.value;
    if (categoryFilter.value) params.category_id = categoryFilter.value;
    if (genderFilter.value) params.gender = genderFilter.value;

    const res = await api.get('/size-charts', { params });
    charts.value = res.data || [];
  } catch (err) {
    console.error('Failed to fetch size charts:', err);
    toast.error(err.message || 'Failed to fetch size charts.');
  } finally {
    loading.value = false;
  }
}

function openCreateModal() {
  editingChartId.value = null;
  isModalOpen.value = true;
}

function editChart(chart) {
  editingChartId.value = chart.id;
  isModalOpen.value = true;
}

function previewChart(chart) {
  editingChartId.value = chart.id;
  isModalOpen.value = true;
}

async function toggleStatus(chart) {
  try {
    const res = await api.post(`/size-charts/${chart.id}/toggle-status`);
    toast.success(res.message || 'Size chart status updated.');
    fetchCharts();
  } catch (err) {
    toast.error(err.message || 'Failed to toggle status.');
  }
}

async function setDefault(chart) {
  try {
    const res = await api.post(`/size-charts/${chart.id}/set-default`);
    toast.success(res.message || 'Set as default size chart.');
    fetchCharts();
  } catch (err) {
    toast.error(err.message || 'Failed to set default.');
  }
}

async function duplicateChart(chart) {
  try {
    const res = await api.post(`/size-charts/${chart.id}/duplicate`);
    toast.success(res.message || 'Size chart duplicated successfully.');
    fetchCharts();
  } catch (err) {
    toast.error(err.message || 'Failed to duplicate chart.');
  }
}

async function confirmDelete(chart) {
  if (!confirm(`Are you sure you want to delete size chart '${chart.name}'?`)) return;

  try {
    const res = await api.delete(`/size-charts/${chart.id}`);
    toast.success(res.message || 'Size chart deleted successfully.');
    fetchCharts();
  } catch (err) {
    toast.error(err.message || 'Failed to delete size chart.');
  }
}
</script>
