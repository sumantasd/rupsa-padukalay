<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-16 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Stock Overview & Real-Time Balances</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Authoritative real-time footwear inventory, reserved quantities & stock valuation for Main Outlet (STR-001)
        </p>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <ReportPdfButtons
          reportType="inventory_stock"
          :filters="{ search: filters.search, brand_id: filters.brand_id, category_id: filters.category_id, color_id: filters.color_id, status: filters.status }"
        />
        <button
          @click="fetchOverview(1)"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer"
        >
          <span>🔄 Refresh Balances</span>
        </button>
      </div>
    </div>

    <!-- SUMMARY KPI CARDS -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4">
      <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs">
        <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total Products</div>
        <div class="text-lg sm:text-xl font-black text-slate-900 mt-1">{{ formatNumber(kpis.total_products) }}</div>
        <div class="text-[10px] text-slate-500 font-medium mt-0.5">Active Catalog</div>
      </div>

      <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs">
        <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total SKUs</div>
        <div class="text-lg sm:text-xl font-black text-blue-600 mt-1">{{ formatNumber(kpis.total_skus) }}</div>
        <div class="text-[10px] text-slate-500 font-medium mt-0.5">Variant-Size Pairs</div>
      </div>

      <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs">
        <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total Units</div>
        <div class="text-lg sm:text-xl font-black text-emerald-600 mt-1">{{ formatNumber(kpis.total_units) }}</div>
        <div class="text-[10px] text-slate-500 font-medium mt-0.5">Sellable Quantity</div>
      </div>

      <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs">
        <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Stock Value (Cost)</div>
        <div class="text-lg sm:text-xl font-black text-purple-700 mt-1">₹{{ formatCurrency(kpis.total_stock_value) }}</div>
        <div class="text-[10px] text-slate-500 font-medium mt-0.5">Cost Valuation</div>
      </div>

      <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs">
        <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Low Stock Alerts</div>
        <div class="text-lg sm:text-xl font-black text-amber-600 mt-1">{{ formatNumber(kpis.low_stock_items) }}</div>
        <div class="text-[10px] text-slate-500 font-medium mt-0.5">≤ Reorder Threshold</div>
      </div>

      <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs">
        <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Out of Stock</div>
        <div class="text-lg sm:text-xl font-black text-red-600 mt-1">{{ formatNumber(kpis.out_of_stock_items) }}</div>
        <div class="text-[10px] text-slate-500 font-medium mt-0.5">0 Available Units</div>
      </div>
    </div>

    <!-- FILTER BAR -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs space-y-3">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
        <!-- Search Input -->
        <div class="lg:col-span-2 relative">
          <input
            v-model="filters.search"
            @input="debouncedFetch"
            type="text"
            placeholder="Search Product, Article #, SKU..."
            class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-red-500/20"
          />
          <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
        </div>

        <!-- Brand Filter -->
        <select v-model="filters.brand_id" @change="fetchOverview(1)" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 focus:outline-none">
          <option value="">All Brands</option>
          <option v-for="b in brandsList" :key="b.id" :value="b.id">{{ b.name }}</option>
        </select>

        <!-- Category Filter -->
        <select v-model="filters.category_id" @change="fetchOverview(1)" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 focus:outline-none">
          <option value="">All Categories</option>
          <option v-for="c in categoriesList" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>

        <!-- Color Filter -->
        <select v-model="filters.color_id" @change="fetchOverview(1)" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 focus:outline-none">
          <option value="">All Colors</option>
          <option v-for="clr in colorsList" :key="clr.id" :value="clr.id">{{ clr.name }}</option>
        </select>

        <!-- Stock Status Filter -->
        <select v-model="filters.status" @change="fetchOverview(1)" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 focus:outline-none">
          <option value="">All Stock Statuses</option>
          <option value="in_stock">In Stock (> Reorder)</option>
          <option value="low_stock">Low Stock (≤ Reorder)</option>
          <option value="out_of_stock">Out of Stock (0 Qty)</option>
        </select>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-100 text-xs">
        <div class="flex items-center gap-2">
          <span class="font-bold text-slate-500">Sort By:</span>
          <select v-model="filters.sort_by" @change="fetchOverview(1)" class="bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1 font-bold text-slate-800">
            <option value="id">Latest Added</option>
            <option value="stock_quantity">Current Stock Quantity</option>
            <option value="updated_at">Last Updated Date</option>
          </select>
          <button @click="toggleSortOrder" class="p-1 bg-slate-100 hover:bg-slate-200 rounded-lg font-bold text-slate-700">
            {{ filters.sort_order === 'asc' ? '⬆️ Asc' : '⬇️ Desc' }}
          </button>
        </div>

        <div class="flex items-center gap-2">
          <span class="font-bold text-slate-500">Per Page:</span>
          <select v-model="pagination.per_page" @change="fetchOverview(1)" class="bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1 font-bold text-slate-800">
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
          </select>
        </div>
      </div>
    </div>

    <!-- INVENTORY STOCK TABLE -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="bg-slate-900 text-white font-extrabold uppercase tracking-wider text-[11px]">
              <th class="py-3.5 px-4">Item & Article #</th>
              <th class="py-3.5 px-4">Product Name</th>
              <th class="py-3.5 px-4">Brand & Category</th>
              <th class="py-3.5 px-4">Color & IND Size</th>
              <th class="py-3.5 px-4 font-mono">SKU</th>
              <th class="py-3.5 px-4 text-right">MRP / Selling</th>
              <th class="py-3.5 px-4 text-right">Cost Price</th>
              <th class="py-3.5 px-4 text-center">Available Stock</th>
              <th class="py-3.5 px-4 text-right">Stock Value</th>
              <th class="py-3.5 px-4 text-center">Status</th>
              <th class="py-3.5 px-4 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium">
            <tr v-if="loading">
              <td colspan="11" class="text-center py-12 text-slate-400 font-bold">
                <div class="flex flex-col items-center justify-center gap-2">
                  <div class="w-6 h-6 border-2 border-red-600 border-t-transparent rounded-full animate-spin"></div>
                  <span>Loading real-time inventory balances...</span>
                </div>
              </td>
            </tr>

            <tr v-else-if="stocksList.length === 0">
              <td colspan="11" class="text-center py-12 text-slate-400 font-bold">
                No inventory records match your criteria.
              </td>
            </tr>

            <tr v-for="st in stocksList" :key="st.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="py-3 px-4">
                <div class="flex items-center gap-2.5">
                  <div class="w-9 h-9 bg-slate-100 rounded-lg border border-slate-200 flex items-center justify-center overflow-hidden shrink-0">
                    <img v-if="st.primary_image_url" :src="st.primary_image_url" class="w-full h-full object-cover" />
                    <span v-else class="text-xs">👞</span>
                  </div>
                  <div>
                    <div class="font-mono font-black text-red-600 text-xs">{{ st.article_number }}</div>
                    <div class="text-[10px] text-slate-400 uppercase font-semibold">STR-001</div>
                  </div>
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
                <div class="font-black text-slate-900 text-xs tracking-tight bg-slate-100 px-1.5 py-0.5 rounded inline-block mt-0.5">
                  {{ st.size_display }}
                </div>
              </td>
              <td class="py-3 px-4 font-mono font-bold text-slate-600 text-[11px]">
                {{ st.sku }}
              </td>
              <td class="py-3 px-4 text-right">
                <div class="font-black text-slate-900">₹{{ formatCurrency(st.selling_price) }}</div>
                <div class="text-[10px] text-slate-400 line-through">MRP ₹{{ formatCurrency(st.mrp) }}</div>
              </td>
              <td class="py-3 px-4 text-right font-bold text-purple-700">
                ₹{{ formatCurrency(st.cost_price) }}
              </td>
              <td class="py-3 px-4 text-center">
                <div :class="['font-black text-sm', st.current_stock > 0 ? 'text-emerald-600' : 'text-red-600']">
                  {{ formatNumber(st.current_stock) }} pairs
                </div>
              </td>
              <td class="py-3 px-4 text-right font-black text-slate-900">
                ₹{{ formatCurrency(st.stock_value) }}
              </td>
              <td class="py-3 px-4 text-center">
                <span
                  :class="[
                    'px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider',
                    st.stock_status === 'in_stock' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' :
                    st.stock_status === 'low_stock' ? 'bg-amber-100 text-amber-800 border border-amber-300' :
                    'bg-red-100 text-red-800 border border-red-300'
                  ]"
                >
                  {{ st.stock_status === 'in_stock' ? 'In Stock' : st.stock_status === 'low_stock' ? 'Low Stock' : 'Out of Stock' }}
                </span>
              </td>
              <td class="py-3 px-4 text-right">
                <button
                  @click="openReconciliationModal(st)"
                  class="px-2.5 py-1 bg-slate-900 hover:bg-slate-800 text-white rounded-lg font-bold text-[10px] transition-all"
                >
                  ⚖️ Reconcile
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="p-4 border-t border-slate-200 bg-slate-50 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs font-bold text-slate-600">
        <div>
          Showing page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} total SKUs)
        </div>
        <div class="flex items-center gap-2">
          <button
            @click="fetchOverview(pagination.current_page - 1)"
            :disabled="pagination.current_page <= 1"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-xl font-bold disabled:opacity-40"
          >
            ← Previous
          </button>
          <button
            @click="fetchOverview(pagination.current_page + 1)"
            :disabled="pagination.current_page >= pagination.last_page"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-xl font-bold disabled:opacity-40"
          >
            Next →
          </button>
        </div>
      </div>
    </div>

    <!-- STOCK RECONCILIATION MODAL -->
    <div v-if="selectedReconcileItem" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-md w-full p-6 space-y-5 text-xs">
        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
          <div>
            <h3 class="font-black text-sm text-slate-900">Stock Reconciliation</h3>
            <p class="text-[11px] text-slate-500 font-medium">Compare physical stock count vs system stock</p>
          </div>
          <button @click="selectedReconcileItem = null" class="text-slate-400 hover:text-slate-800 font-black text-base">✕</button>
        </div>

        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2">
          <div class="flex justify-between">
            <span class="text-slate-500 font-bold">ARTICLE #:</span>
            <span class="font-mono font-black text-red-600">{{ selectedReconcileItem.article_number }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500 font-bold">PRODUCT:</span>
            <span class="font-black text-slate-900">{{ selectedReconcileItem.product_name }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500 font-bold">COLOR & IND SIZE:</span>
            <span class="font-bold text-slate-800">{{ selectedReconcileItem.color_name }} | {{ selectedReconcileItem.size_display }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500 font-bold">SKU:</span>
            <span class="font-mono font-bold text-slate-700">{{ selectedReconcileItem.sku }}</span>
          </div>
          <div class="flex justify-between pt-2 border-t border-slate-200 font-black text-sm">
            <span>CURRENT SYSTEM STOCK:</span>
            <span class="text-blue-600">{{ selectedReconcileItem.current_stock }} pairs</span>
          </div>
        </div>

        <div class="space-y-3">
          <div>
            <label class="block font-black text-slate-900 mb-1">Physical Count Observed (Actual Pairs)</label>
            <input
              type="number"
              v-model.number="reconcilePhysicalCount"
              min="0"
              class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-mono font-black text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500/20"
            />
          </div>

          <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-900 font-bold flex justify-between">
            <span>RECONCILIATION DELTA:</span>
            <span :class="reconcileDelta < 0 ? 'text-red-600' : reconcileDelta > 0 ? 'text-emerald-700' : 'text-slate-700'">
              {{ reconcileDelta > 0 ? '+' + reconcileDelta : reconcileDelta }} pairs
            </span>
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Reconciliation Notes / Reason</label>
            <textarea
              v-model="reconcileNotes"
              rows="2"
              placeholder="e.g. Physical audit count correction"
              class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl font-medium text-slate-800 focus:outline-none"
            ></textarea>
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-slate-200">
          <button @click="selectedReconcileItem = null" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl font-bold">
            Cancel
          </button>
          <button
            @click="submitReconciliation"
            :disabled="submittingReconcile"
            class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black shadow-md shadow-red-600/20 uppercase tracking-wider"
          >
            {{ submittingReconcile ? 'Saving...' : 'Confirm Stock Adjustment' }}
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
import { useReportPdf } from '../../composables/useReportPdf';

const { exportReportPdf } = useReportPdf();

function handleViewPdf() {
  exportReportPdf('inventory_stock', {
    search: filters.search,
    brand_id: filters.brand_id,
    category_id: filters.category_id,
    color_id: filters.color_id,
    status: filters.status,
  }, 'inline');
}

function handleDownloadPdf() {
  exportReportPdf('inventory_stock', {
    search: filters.search,
    brand_id: filters.brand_id,
    category_id: filters.category_id,
    color_id: filters.color_id,
    status: filters.status,
  }, 'attachment');
}

const loading = ref(false);
const stocksList = ref([]);
const kpis = reactive({
  total_products: 0,
  total_skus: 0,
  total_units: 0,
  total_stock_value: 0,
  total_retail_value: 0,
  potential_gross_margin: 0,
  low_stock_items: 0,
  out_of_stock_items: 0,
});

const brandsList = ref([]);
const categoriesList = ref([]);
const colorsList = ref([]);

const filters = reactive({
  search: '',
  brand_id: '',
  category_id: '',
  color_id: '',
  status: '',
  sort_by: 'id',
  sort_order: 'desc',
});

const pagination = reactive({
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1,
});

const selectedReconcileItem = ref(null);
const reconcilePhysicalCount = ref(0);
const reconcileNotes = ref('');
const submittingReconcile = ref(false);

const reconcileDelta = computed(() => {
  if (!selectedReconcileItem.value) return 0;
  return Number(reconcilePhysicalCount.value || 0) - Number(selectedReconcileItem.value.current_stock || 0);
});

let debounceTimer = null;
function debouncedFetch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => fetchOverview(1), 300);
}

function toggleSortOrder() {
  filters.sort_order = filters.sort_order === 'asc' ? 'desc' : 'asc';
  fetchOverview(1);
}

async function fetchOverview(page = 1) {
  loading.value = true;
  try {
    const params = {
      page,
      per_page: pagination.per_page,
      search: filters.search || undefined,
      brand_id: filters.brand_id || undefined,
      category_id: filters.category_id || undefined,
      color_id: filters.color_id || undefined,
      status: filters.status || undefined,
      sort_by: filters.sort_by,
      sort_order: filters.sort_order,
    };

    const res = await api.get('/inventory/overview', { params });
    const payload = res.data?.data || res.data || res;

    stocksList.value = payload.items || [];
    if (payload.kpis) {
      Object.assign(kpis, payload.kpis);
    }
    if (payload.pagination) {
      Object.assign(pagination, payload.pagination);
    }
  } catch (err) {
    console.error('Failed to load inventory overview:', err);
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

function openReconciliationModal(item) {
  selectedReconcileItem.value = item;
  reconcilePhysicalCount.value = item.current_stock;
  reconcileNotes.value = `Physical stock audit reconciliation for SKU ${item.sku}`;
}

async function submitReconciliation() {
  if (!selectedReconcileItem.value) return;

  if (reconcileDelta.value === 0) {
    alert('Physical count matches system stock. No adjustment needed.');
    selectedReconcileItem.value = null;
    return;
  }

  submittingReconcile.value = true;
  try {
    const payload = {
      store_id: 1, // STR-001
      reason: 'Physical Stock Count',
      notes: reconcileNotes.value,
      items: [
        {
          product_variant_size_id: selectedReconcileItem.value.product_variant_size_id,
          sku: selectedReconcileItem.value.sku,
          new_quantity: reconcilePhysicalCount.value,
        },
      ],
    };

    await api.post('/inventory/adjustments', payload);
    alert('Stock reconciliation adjustment applied successfully!');
    selectedReconcileItem.value = null;
    fetchOverview(pagination.current_page);
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to apply stock reconciliation.');
  } finally {
    submittingReconcile.value = false;
  }
}

function formatCurrency(val) {
  return Number(val || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatNumber(val) {
  return Number(val || 0).toLocaleString('en-IN');
}

onMounted(() => {
  fetchOverview(1);
  loadMasterFilterData();
});
</script>
