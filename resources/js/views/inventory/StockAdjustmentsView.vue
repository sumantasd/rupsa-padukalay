<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-16 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Stock Adjustments Management</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Record manual inventory additions, reductions, physical stock count corrections & damage entries for STR-001
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          @click="openNewAdjustmentModal"
          class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md shadow-red-600/20 transition-all uppercase tracking-wider flex items-center gap-1.5"
        >
          <span>➕</span>
          <span>New Stock Adjustment</span>
        </button>
      </div>
    </div>

    <!-- FILTER BAR -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs space-y-3">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
        <!-- Search Input -->
        <div class="lg:col-span-2 relative">
          <input
            v-model="filters.search"
            @input="debouncedFetch"
            type="text"
            placeholder="Search Adjustment #, SKU, Notes..."
            class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-red-500/20"
          />
          <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
        </div>

        <!-- Reason Filter -->
        <select v-model="filters.reason" @change="fetchAdjustments(1)" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-800 focus:outline-none">
          <option value="">All Reasons</option>
          <option value="Physical Stock Count">Physical Stock Count</option>
          <option value="Damaged">Damaged</option>
          <option value="Lost">Lost</option>
          <option value="Found">Found</option>
          <option value="Correction">Stock Correction</option>
          <option value="Opening Stock">Opening Stock</option>
          <option value="Other">Other</option>
        </select>

        <!-- Refresh -->
        <button
          @click="fetchAdjustments(1)"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs flex items-center justify-center gap-1.5"
        >
          <span>🔄 Refresh List</span>
        </button>
      </div>
    </div>

    <!-- ADJUSTMENTS HISTORY TABLE -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="bg-slate-900 text-white font-extrabold uppercase tracking-wider text-[11px]">
              <th class="py-3.5 px-4">Date & Time</th>
              <th class="py-3.5 px-4 font-mono">Adjustment #</th>
              <th class="py-3.5 px-4">Reason</th>
              <th class="py-3.5 px-4 text-center">Items Count</th>
              <th class="py-3.5 px-4">Audit Notes</th>
              <th class="py-3.5 px-4">Processed By</th>
              <th class="py-3.5 px-4 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium">
            <tr v-if="loading">
              <td colspan="7" class="text-center py-12 text-slate-400 font-bold">
                <div class="flex flex-col items-center justify-center gap-2">
                  <div class="w-6 h-6 border-2 border-red-600 border-t-transparent rounded-full animate-spin"></div>
                  <span>Loading stock adjustments history...</span>
                </div>
              </td>
            </tr>

            <tr v-else-if="adjustmentsList.length === 0">
              <td colspan="7" class="text-center py-12 text-slate-400 font-bold">
                No stock adjustment records found.
              </td>
            </tr>

            <tr v-for="adj in adjustmentsList" :key="adj.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="py-3 px-4 font-bold text-slate-700 whitespace-nowrap">
                {{ formatDateTime(adj.created_at) }}
              </td>
              <td class="py-3 px-4 font-mono font-black text-red-600 text-xs whitespace-nowrap">
                {{ adj.adjustment_number }}
              </td>
              <td class="py-3 px-4">
                <span class="px-2.5 py-1 bg-slate-100 rounded-full font-black text-slate-800 uppercase text-[10px]">
                  {{ adj.reason }}
                </span>
              </td>
              <td class="py-3 px-4 text-center font-black text-slate-900">
                {{ adj.items ? adj.items.length : 1 }} SKUs
              </td>
              <td class="py-3 px-4 text-slate-600 max-w-xs truncate">
                {{ adj.notes || 'Manual inventory adjustment' }}
              </td>
              <td class="py-3 px-4 font-bold text-slate-800">
                {{ adj.creator_name || 'Staff' }}
              </td>
              <td class="py-3 px-4 text-right">
                <button
                  @click="openAdjustmentDetail(adj)"
                  class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-bold text-[10px] transition-all"
                >
                  👁️ View Details
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="p-4 border-t border-slate-200 bg-slate-50 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs font-bold text-slate-600">
        <div>
          Showing page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} total records)
        </div>
        <div class="flex items-center gap-2">
          <button
            @click="fetchAdjustments(pagination.current_page - 1)"
            :disabled="pagination.current_page <= 1"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-xl font-bold disabled:opacity-40"
          >
            ← Previous
          </button>
          <button
            @click="fetchAdjustments(pagination.current_page + 1)"
            :disabled="pagination.current_page >= pagination.last_page"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-xl font-bold disabled:opacity-40"
          >
            Next →
          </button>
        </div>
      </div>
    </div>

    <!-- NEW STOCK ADJUSTMENT MODAL -->
    <div v-if="showNewModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-xl w-full p-6 space-y-5 text-xs">
        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
          <div>
            <h3 class="font-black text-sm text-slate-900">➕ New Stock Adjustment</h3>
            <p class="text-[11px] text-slate-500 font-medium">Create atomic inventory adjustment for Main Outlet (STR-001)</p>
          </div>
          <button @click="showNewModal = false" class="text-slate-400 hover:text-slate-800 font-black text-base">✕</button>
        </div>

        <!-- SKU SEARCH STEP -->
        <div class="space-y-3">
          <label class="block font-black text-slate-900">Search Product / SKU / Article Number</label>
          <div class="relative">
            <input
              type="text"
              v-model="skuSearchQuery"
              @input="searchProductsForAdjustment"
              placeholder="Type Article # (e.g. RP-008), Product Name, or SKU..."
              class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500/20"
            />
            <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
          </div>

          <!-- Product Search Results List -->
          <div v-if="foundSkus.length > 0" class="max-h-40 overflow-y-auto border border-slate-200 rounded-xl divide-y divide-slate-100 bg-white shadow-xs">
            <div
              v-for="item in foundSkus"
              :key="item.id"
              @click="selectSkuForAdjustment(item)"
              class="p-2.5 hover:bg-slate-50 cursor-pointer flex items-center justify-between transition-colors"
            >
              <div>
                <span class="font-mono font-black text-red-600 mr-2">{{ item.article_number }}</span>
                <span class="font-bold text-slate-900">{{ item.product_name }}</span>
                <span class="text-slate-500 text-[10px] ml-2">({{ item.color_name }} | {{ item.size_display }})</span>
              </div>
              <div class="font-mono font-bold text-slate-700">Stock: {{ item.current_stock }}</div>
            </div>
          </div>
        </div>

        <!-- SELECTED SKU DETAILS -->
        <div v-if="selectedSku" class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3">
          <div class="flex justify-between items-center border-b border-slate-200 pb-2">
            <div>
              <span class="font-mono font-black text-red-600 text-sm mr-2">{{ selectedSku.article_number }}</span>
              <span class="font-black text-slate-900">{{ selectedSku.product_name }}</span>
              <div class="text-[10px] text-slate-500 font-bold mt-0.5">
                {{ selectedSku.brand_name }} | {{ selectedSku.color_name }} | {{ selectedSku.size_display }} | SKU: {{ selectedSku.sku }}
              </div>
            </div>
            <button @click="selectedSku = null" class="text-slate-400 hover:text-slate-700 font-bold">Change</button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
            <!-- Adjustment Type -->
            <div>
              <label class="block font-bold text-slate-700 mb-1">Adjustment Action</label>
              <select v-model="adjForm.type" class="w-full bg-white border border-slate-300 rounded-xl px-2.5 py-1.5 font-bold text-slate-900">
                <option value="add">Add Stock (+)</option>
                <option value="deduct">Reduce Stock (-)</option>
                <option value="set_exact">Set Exact Stock (=)</option>
              </select>
            </div>

            <!-- Quantity -->
            <div>
              <label class="block font-bold text-slate-700 mb-1">Quantity / Count</label>
              <input
                type="number"
                v-model.number="adjForm.quantity"
                min="0"
                class="w-full bg-white border border-slate-300 rounded-xl px-2.5 py-1.5 font-mono font-black text-slate-900"
              />
            </div>

            <!-- Reason -->
            <div>
              <label class="block font-bold text-slate-700 mb-1">Reason</label>
              <select v-model="adjForm.reason" class="w-full bg-white border border-slate-300 rounded-xl px-2.5 py-1.5 font-bold text-slate-900">
                <option value="Physical Stock Count">Physical Stock Count</option>
                <option value="Damaged">Damaged</option>
                <option value="Lost">Lost</option>
                <option value="Found">Found</option>
                <option value="Correction">Stock Correction</option>
                <option value="Opening Stock">Opening Stock</option>
                <option value="Other">Other</option>
              </select>
            </div>
          </div>

          <!-- BEFORE / AFTER PREVIEW CARD -->
          <div class="p-3 bg-white border border-slate-300 rounded-xl flex items-center justify-between font-mono font-black text-xs">
            <div>CURRENT: <span class="text-blue-600">{{ selectedSku.current_stock }}</span></div>
            <div>ACTION: <span :class="calcDelta < 0 ? 'text-red-600' : 'text-emerald-600'">{{ calcDelta > 0 ? '+' + calcDelta : calcDelta }}</span></div>
            <div>NEW STOCK: <span :class="calcNewStock < 0 ? 'text-red-600' : 'text-slate-900'">{{ calcNewStock }}</span></div>
          </div>

          <div v-if="calcNewStock < 0" class="p-2 bg-red-50 border border-red-200 text-red-700 rounded-xl font-bold text-[11px]">
            ⚠️ Negative stock rejected: Quantity cannot reduce stock below zero.
          </div>

          <!-- Notes -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Audit Notes / Explanation</label>
            <input
              type="text"
              v-model="adjForm.notes"
              placeholder="e.g. Annual stock audit correction"
              class="w-full bg-white border border-slate-300 rounded-xl px-3 py-1.5 font-medium text-slate-800"
            />
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-slate-200">
          <button @click="showNewModal = false" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl font-bold">
            Cancel
          </button>
          <button
            @click="submitAdjustment"
            :disabled="submitting || !selectedSku || calcNewStock < 0"
            class="px-5 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-40 text-white rounded-xl font-black shadow-md uppercase tracking-wider"
          >
            {{ submitting ? 'Processing...' : 'Confirm & Apply Adjustment' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ADJUSTMENT DETAIL MODAL -->
    <div v-if="selectedAdjustment" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-lg w-full p-6 space-y-4 text-xs">
        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
          <div>
            <h3 class="font-black text-sm text-slate-900">Stock Adjustment: {{ selectedAdjustment.adjustment_number }}</h3>
            <p class="text-[11px] text-slate-500 font-medium">Processed on {{ formatDateTime(selectedAdjustment.created_at) }}</p>
          </div>
          <button @click="selectedAdjustment = null" class="text-slate-400 hover:text-slate-800 font-black text-base">✕</button>
        </div>

        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-1.5">
          <div class="flex justify-between">
            <span class="text-slate-500 font-bold">REASON:</span>
            <span class="font-black uppercase text-slate-900">{{ selectedAdjustment.reason }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500 font-bold">PROCESSED BY:</span>
            <span class="font-bold text-slate-800">{{ selectedAdjustment.creator_name || 'Staff' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500 font-bold">STORE OUTLET:</span>
            <span class="font-bold text-slate-800">Main Outlet (STR-001)</span>
          </div>
        </div>

        <div class="space-y-2">
          <span class="font-black text-slate-900 block text-xs uppercase">Adjusted SKU Items</span>
          <div class="space-y-1.5 max-h-48 overflow-y-auto">
            <div v-for="item in selectedAdjustment.items" :key="item.id" class="p-3 bg-slate-50 rounded-xl border border-slate-200 flex justify-between items-center">
              <div>
                <span class="font-mono font-black text-red-600 mr-2">{{ item.article_number }}</span>
                <span class="font-bold text-slate-900">{{ item.product_name }}</span>
                <div class="text-[10px] text-slate-500">{{ item.color_name }} | {{ item.size_display }} | SKU: {{ item.sku }}</div>
              </div>
              <div class="text-right font-mono">
                <div class="font-black text-slate-900">{{ item.old_quantity }} → {{ item.new_quantity }}</div>
                <div :class="['text-[11px] font-bold', item.quantity_adjusted > 0 ? 'text-emerald-600' : 'text-red-600']">
                  {{ item.quantity_adjusted > 0 ? '+' + item.quantity_adjusted : item.quantity_adjusted }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-end pt-2 border-t border-slate-200">
          <button @click="selectedAdjustment = null" class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import api from '../../services/api';

const loading = ref(false);
const adjustmentsList = ref([]);
const selectedAdjustment = ref(null);
const showNewModal = ref(false);

const filters = reactive({
  search: '',
  reason: '',
});

const pagination = reactive({
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1,
});

const skuSearchQuery = ref('');
const foundSkus = ref([]);
const selectedSku = ref(null);

const adjForm = reactive({
  type: 'add',
  quantity: 1,
  reason: 'Physical Stock Count',
  notes: '',
});

const submitting = ref(false);

const calcDelta = computed(() => {
  if (!selectedSku.value) return 0;
  const q = Number(adjForm.quantity || 0);
  if (adjForm.type === 'add') return q;
  if (adjForm.type === 'deduct') return -q;
  if (adjForm.type === 'set_exact') return q - Number(selectedSku.value.current_stock || 0);
  return 0;
});

const calcNewStock = computed(() => {
  if (!selectedSku.value) return 0;
  return Number(selectedSku.value.current_stock || 0) + calcDelta.value;
});

let debounceTimer = null;
function debouncedFetch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => fetchAdjustments(1), 300);
}

async function fetchAdjustments(page = 1) {
  loading.value = true;
  try {
    const params = {
      page,
      per_page: pagination.per_page,
      search: filters.search || undefined,
      reason: filters.reason || undefined,
    };

    const res = await api.get('/inventory/adjustments', { params });
    const payload = res.data?.data || res.data || res;

    adjustmentsList.value = payload.items || [];
    if (payload.pagination) {
      Object.assign(pagination, payload.pagination);
    }
  } catch (err) {
    console.error('Failed to load stock adjustments:', err);
  } finally {
    loading.value = false;
  }
}

function openNewAdjustmentModal() {
  showNewModal.value = true;
  skuSearchQuery.value = '';
  foundSkus.value = [];
  selectedSku.value = null;
  adjForm.type = 'add';
  adjForm.quantity = 1;
  adjForm.reason = 'Physical Stock Count';
  adjForm.notes = '';
}

async function searchProductsForAdjustment() {
  if (!skuSearchQuery.value || skuSearchQuery.value.trim().length < 2) {
    foundSkus.value = [];
    return;
  }

  try {
    const res = await api.get('/inventory/stocks', { params: { search: skuSearchQuery.value.trim(), per_page: 10 } });
    const payload = res.data?.data || res.data || res;
    foundSkus.value = payload.items || [];
  } catch (err) {
    console.error('Failed to search SKUs for adjustment:', err);
  }
}

function selectSkuForAdjustment(item) {
  selectedSku.value = item;
  foundSkus.value = [];
  skuSearchQuery.value = `${item.article_number} - ${item.product_name}`;
}

async function submitAdjustment() {
  if (!selectedSku.value) {
    alert('Please select a product SKU first.');
    return;
  }

  if (calcNewStock.value < 0) {
    alert('Adjustment would result in negative stock. Action rejected.');
    return;
  }

  submitting.value = true;
  try {
    const payload = {
      store_id: 1, // STR-001
      reason: adjForm.reason,
      notes: adjForm.notes || `Manual stock adjustment (${adjForm.reason})`,
      items: [
        {
          product_variant_size_id: selectedSku.value.product_variant_size_id,
          sku: selectedSku.value.sku,
          type: adjForm.type,
          quantity: adjForm.quantity,
          new_quantity: calcNewStock.value,
        },
      ],
    };

    await api.post('/inventory/adjustments', payload);
    alert('Stock adjustment processed successfully!');
    showNewModal.value = false;
    fetchAdjustments(1);
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to process stock adjustment.');
  } finally {
    submitting.value = false;
  }
}

function openAdjustmentDetail(adj) {
  selectedAdjustment.value = adj;
}

function formatDateTime(dtStr) {
  if (!dtStr) return 'N/A';
  try {
    const d = new Date(dtStr);
    return d.toLocaleString('en-IN', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch (e) {
    return dtStr;
  }
}

onMounted(() => {
  fetchAdjustments(1);
});
</script>
