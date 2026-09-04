<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-16 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Low Stock & Out of Stock Alert Dashboard</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Real-time, database-driven stock reorder alerts evaluated against global & per-SKU thresholds for STR-001
        </p>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <ReportPdfButtons
          reportType="inventory_low_stock"
          :filters="{ search: filters.search, brand_id: filters.brand_id, category_id: filters.category_id, color_id: filters.color_id }"
        />
        <router-link
          to="/admin/settings/stock"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs flex items-center gap-1.5 transition-all"
        >
          <span>⚙️ Thresholds</span>
        </router-link>
        <router-link
          to="/admin/purchase-orders"
          class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md shadow-red-600/20 transition-all uppercase tracking-wider flex items-center gap-1.5"
        >
          <span>📝 Purchase Orders</span>
        </router-link>
      </div>
    </div>

    <!-- SUMMARY KPI CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-white p-4.5 rounded-2xl border border-slate-200/90 shadow-xs">
        <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Critical Out of Stock</div>
        <div class="text-xl font-black text-red-600 mt-1">{{ formatNumber(criticalCount) }} SKUs</div>
        <div class="text-[10px] text-slate-500 font-medium mt-0.5">0 Available Pairs (Immediate Action)</div>
      </div>

      <div class="bg-white p-4.5 rounded-2xl border border-slate-200/90 shadow-xs">
        <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Low Stock Warnings</div>
        <div class="text-xl font-black text-amber-600 mt-1">{{ formatNumber(lowStockCount) }} SKUs</div>
        <div class="text-[10px] text-slate-500 font-medium mt-0.5">&le; Configured Threshold</div>
      </div>

      <div class="bg-white p-4.5 rounded-2xl border border-slate-200/90 shadow-xs">
        <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Suggested Reorder Units</div>
        <div class="text-xl font-black text-blue-600 mt-1">{{ formatNumber(totalSuggestedUnits) }} pairs</div>
        <div class="text-[10px] text-slate-500 font-medium mt-0.5">Recommended Stock Replenishment</div>
      </div>

      <div class="bg-white p-4.5 rounded-2xl border border-slate-200/90 shadow-xs">
        <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Est. Reorder Cost</div>
        <div class="text-xl font-black text-purple-700 mt-1">₹{{ formatCurrency(estimatedReorderValue) }}</div>
        <div class="text-[10px] text-slate-500 font-medium mt-0.5">Estimated Wholesale Investment</div>
      </div>
    </div>

    <!-- FILTER BAR -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs space-y-3">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 text-xs">
        <!-- Search Input -->
        <div class="lg:col-span-2 relative">
          <input
            v-model="filters.search"
            @input="debouncedFetch"
            type="text"
            placeholder="Search Product, Article #, SKU..."
            class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-red-500/20"
          />
          <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
        </div>

        <!-- Stock Status Filter -->
        <select v-model="filters.status_tab" @change="fetchLowStock(1)" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-800 focus:outline-none">
          <option value="all">All Alerts (Low & Out of Stock)</option>
          <option value="critical">🚨 OUT OF STOCK Only (0 Pairs)</option>
          <option value="warning">⚠️ LOW STOCK Only (&le; Threshold)</option>
        </select>

        <!-- Brand Filter -->
        <select v-model="filters.brand_id" @change="fetchLowStock(1)" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-800 focus:outline-none">
          <option value="">All Brands</option>
          <option v-for="b in brandsList" :key="b.id" :value="b.id">{{ b.name }}</option>
        </select>

        <!-- Category Filter -->
        <select v-model="filters.category_id" @change="fetchLowStock(1)" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-800 focus:outline-none">
          <option value="">All Categories</option>
          <option v-for="c in categoriesList" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>

        <!-- Color Filter -->
        <select v-model="filters.color_id" @change="fetchLowStock(1)" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-800 focus:outline-none">
          <option value="">All Colors</option>
          <option v-for="clr in colorsList" :key="clr.id" :value="clr.id">{{ clr.name }}</option>
        </select>
      </div>
    </div>

    <!-- LOW STOCK ALERT TABLE -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="bg-slate-900 text-white font-extrabold uppercase tracking-wider text-[11px]">
              <th class="py-3.5 px-4">Article # & Item</th>
              <th class="py-3.5 px-4">Product Name</th>
              <th class="py-3.5 px-4">Brand & Category</th>
              <th class="py-3.5 px-4">Color & IND Size</th>
              <th class="py-3.5 px-4 font-mono">SKU</th>
              <th class="py-3.5 px-4 text-center">Current Stock</th>
              <th class="py-3.5 px-4 text-center">Threshold</th>
              <th class="py-3.5 px-4 text-center">Reorder Qty</th>
              <th class="py-3.5 px-4">Preferred Supplier</th>
              <th class="py-3.5 px-4 text-right font-mono">Unit Cost</th>
              <th class="py-3.5 px-4 text-center">Status</th>
              <th class="py-3.5 px-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium">
            <tr v-if="loading">
              <td colspan="12" class="text-center py-12 text-slate-400 font-bold">
                <div class="flex flex-col items-center justify-center gap-2">
                  <div class="w-6 h-6 border-2 border-red-600 border-t-transparent rounded-full animate-spin"></div>
                  <span>Loading low stock reorder alerts...</span>
                </div>
              </td>
            </tr>

            <tr v-else-if="filteredList.length === 0">
              <td colspan="12" class="text-center py-12 text-emerald-600 font-bold">
                🎉 Excellent! All footwear SKUs are currently above minimum stock thresholds.
              </td>
            </tr>

            <tr v-for="st in filteredList" :key="st.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="py-3 px-4">
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 bg-slate-100 rounded-lg border border-slate-200 flex items-center justify-center overflow-hidden shrink-0">
                    <img v-if="st.primary_image_url" :src="st.primary_image_url" class="w-full h-full object-cover" />
                    <span v-else class="text-xs">👞</span>
                  </div>
                  <div class="font-mono font-black text-red-600 text-xs">{{ st.article_number }}</div>
                </div>
              </td>
              <td class="py-3 px-4 font-black text-slate-900 text-xs">
                {{ st.product_name }}
              </td>
              <td class="py-3 px-4">
                <div class="font-bold text-slate-800">{{ st.brand_name }}</div>
                <div class="text-[10px] text-slate-500 font-medium">{{ st.category_name }}</div>
              </td>
              <td class="py-3 px-4">
                <div class="font-bold text-slate-800">{{ st.color_name }}</div>
                <div class="font-black text-slate-900 text-xs bg-slate-100 px-1.5 py-0.5 rounded inline-block mt-0.5">
                  {{ st.size_display }}
                </div>
              </td>
              <td class="py-3 px-4 font-mono font-bold text-slate-600 text-[11px]">
                {{ st.sku }}
              </td>
              <td class="py-3 px-4 text-center">
                <div :class="['font-black text-sm', st.current_stock <= 0 ? 'text-red-600' : 'text-amber-600']">
                  {{ formatNumber(st.current_stock) }} pairs
                </div>
              </td>
              <td class="py-3 px-4 text-center font-bold text-slate-700">
                {{ st.low_stock_threshold || st.reorder_level || 5 }} pairs
              </td>
              <td class="py-3 px-4 text-center font-black text-blue-700 text-sm">
                +{{ st.suggested_reorder_qty || st.reorder_quantity || 10 }} pairs
              </td>
              <td class="py-3 px-4">
                <div class="font-bold text-slate-900">{{ st.supplier_name }}</div>
                <div class="text-[10px] text-slate-500 font-mono">{{ st.supplier_phone }}</div>
              </td>
              <td class="py-3 px-4 text-right font-bold text-purple-700">
                ₹{{ formatCurrency(st.cost_price) }}
              </td>
              <td class="py-3 px-4 text-center">
                <span
                  :class="[
                    'px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider',
                    st.current_stock <= 0 ? 'bg-red-100 text-red-800 border border-red-300' : 'bg-amber-100 text-amber-800 border border-amber-300'
                  ]"
                >
                  {{ st.current_stock <= 0 ? 'OUT OF STOCK' : 'LOW STOCK' }}
                </span>
              </td>
              <td class="py-3 px-4 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <router-link
                    to="/admin/suppliers"
                    class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-bold text-[10px] transition-all"
                  >
                    🏢 Supplier
                  </router-link>
                  <router-link
                    to="/admin/purchase-orders"
                    class="px-2 py-1 bg-red-600 hover:bg-red-700 text-white rounded-lg font-bold text-[10px] transition-all"
                  >
                    📝 Order
                  </router-link>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="p-4 border-t border-slate-200 bg-slate-50 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs font-bold text-slate-600">
        <div>
          Showing page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} low stock SKUs)
        </div>
        <div class="flex items-center gap-2">
          <button
            @click="fetchLowStock(pagination.current_page - 1)"
            :disabled="pagination.current_page <= 1"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-xl font-bold disabled:opacity-40"
          >
            ← Previous
          </button>
          <button
            @click="fetchLowStock(pagination.current_page + 1)"
            :disabled="pagination.current_page >= pagination.last_page"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-xl font-bold disabled:opacity-40"
          >
            Next →
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import ReportPdfButtons from '../../components/ui/ReportPdfButtons.vue';
import api from '../../services/api';

const loading = ref(false);
const lowStockList = ref([]);
const brandsList = ref([]);
const categoriesList = ref([]);
const colorsList = ref([]);

const filters = reactive({
  search: '',
  status_tab: 'all',
  brand_id: '',
  category_id: '',
  color_id: '',
});

const pagination = reactive({
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1,
});

const criticalCount = computed(() => lowStockList.value.filter(i => i.current_stock <= 0).length);
const lowStockCount = computed(() => lowStockList.value.filter(i => i.current_stock > 0).length);
const totalSuggestedUnits = computed(() => lowStockList.value.reduce((sum, i) => sum + (i.suggested_reorder_qty || 10), 0));
const estimatedReorderValue = computed(() => lowStockList.value.reduce((sum, i) => sum + ((i.suggested_reorder_qty || 10) * (i.cost_price || 0)), 0));

const filteredList = computed(() => {
  if (filters.status_tab === 'critical') {
    return lowStockList.value.filter(i => i.current_stock <= 0);
  }
  if (filters.status_tab === 'warning') {
    return lowStockList.value.filter(i => i.current_stock > 0);
  }
  return lowStockList.value;
});

let debounceTimer = null;
function debouncedFetch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => fetchLowStock(1), 300);
}

async function fetchLowStock(page = 1) {
  loading.value = true;
  try {
    const params = {
      page,
      per_page: pagination.per_page,
      search: filters.search || undefined,
      critical_only: filters.status_tab === 'critical' ? true : undefined,
      brand_id: filters.brand_id || undefined,
      category_id: filters.category_id || undefined,
      color_id: filters.color_id || undefined,
    };

    const res = await api.get('/inventory/low-stock', { params });
    const payload = res.data?.data || res.data || res;

    lowStockList.value = payload.items || [];
    if (payload.pagination) {
      Object.assign(pagination, payload.pagination);
    }
  } catch (err) {
    console.error('Failed to load low stock alerts:', err);
  } finally {
    loading.value = false;
  }
}

async function loadMasterFilterData() {
  try {
    const [bRes, cRes, clrRes] = await Promise.all([
      api.get('/brands'),
      api.get('/categories'),
      api.get('/colors'),
    ]);
    brandsList.value = bRes.data?.data || bRes.data?.items || bRes.data || [];
    categoriesList.value = cRes.data?.data || cRes.data?.items || cRes.data || [];
    colorsList.value = clrRes.data?.data || clrRes.data?.items || clrRes.data || [];
  } catch (e) {
    console.error('Failed to load filter options:', e);
  }
}

function formatCurrency(val) {
  return Number(val || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatNumber(val) {
  return Number(val || 0).toLocaleString('en-IN');
}

onMounted(() => {
  fetchLowStock(1);
  loadMasterFilterData();
});
</script>
