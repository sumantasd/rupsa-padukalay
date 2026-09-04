<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-16 antialiased font-sans">
    <!-- Toast Notification Banner -->
    <div v-if="notification.show" :class="['p-4 rounded-2xl border font-bold text-xs flex items-center justify-between shadow-md transition-all', notification.type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-red-50 border-red-200 text-red-900']">
      <div class="flex items-center gap-2">
        <span>{{ notification.type === 'success' ? '✅' : '⚠️' }}</span>
        <span>{{ notification.message }}</span>
      </div>
      <button @click="notification.show = false" class="text-slate-400 hover:text-slate-600 text-sm font-black cursor-pointer">✕</button>
    </div>

    <!-- Header & Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
          <span>🗑️</span>
          <span>Stock Out – Damage</span>
        </h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Permanently deduct damaged, broken, torn, defective or lost footwear inventory from available stock
        </p>
      </div>

      <div class="flex items-center gap-3">
        <div class="flex items-center gap-1.5 bg-white border border-slate-200 rounded-xl px-3 py-1.5 shadow-xs font-bold text-xs text-slate-700">
          <span>🏬 Store:</span>
          <select v-model="selectedStoreId" class="bg-transparent focus:outline-none cursor-pointer">
            <option :value="1">RUPSA PADUKALAYA — Main Outlet (STR-001)</option>
          </select>
        </div>
      </div>
    </div>

    <!-- NEW DAMAGE TRANSACTION PANEL -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-6">
      <h2 class="text-sm font-black uppercase text-slate-900 tracking-wider flex items-center gap-2 border-b border-slate-100 pb-3">
        <span>➕</span>
        <span>Record New Stock Damage Transaction</span>
      </h2>

      <!-- 1. SEARCH PRODUCT BAR -->
      <div class="relative">
        <label class="block font-bold text-xs text-slate-700 mb-1">
          Search Footwear Product (Name, Article #, SKU, Barcode, Brand)
        </label>
        <div class="relative">
          <input
            type="text"
            v-model="searchQuery"
            @input="onSearchInput"
            placeholder="Type Product Name / Article (e.g., RP-610, Urban Flex, Action)..."
            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 font-bold text-xs text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-900/10"
          />
          <div v-if="searching" class="absolute right-3 top-2.5 text-xs text-slate-400 font-bold">
            Searching...
          </div>
        </div>

        <!-- SEARCH RESULTS DROPDOWN -->
        <div v-if="searchHasRun && searchQuery.trim().length >= 2 && !searching" class="absolute z-20 w-full mt-1 bg-white border border-slate-200 rounded-2xl shadow-xl max-h-60 overflow-y-auto divide-y divide-slate-100">
          <div v-if="searchError" class="p-4 text-center text-xs font-bold text-red-600">
            ⚠️ {{ searchError }}
          </div>
          <div v-else-if="searchResults.length === 0" class="p-4 text-center text-xs font-bold text-slate-500">
            🔍 No matching product found for "{{ searchQuery }}"
          </div>
          <div
            v-else
            v-for="prod in searchResults"
            :key="prod.id"
            @click="selectProduct(prod)"
            class="p-3 hover:bg-slate-50 cursor-pointer flex items-center justify-between text-xs transition-colors"
          >
            <div>
              <div class="font-black text-slate-900">{{ prod.name }}</div>
              <div class="text-[10px] text-slate-500 font-mono">Article: {{ prod.article_number || 'N/A' }} | Brand: {{ prod.brand || 'N/A' }} | Category: {{ prod.category || 'N/A' }}</div>
            </div>
            <div class="text-right">
              <span class="px-2 py-1 bg-slate-100 text-slate-800 rounded font-mono font-bold text-[10px]">
                {{ prod.sizes?.length || 0 }} Sizes Available
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- 2. SELECTED PRODUCT & SIZE-WISE MATRIX -->
      <div v-if="selectedProduct" class="space-y-4 bg-slate-50 p-4 rounded-2xl border border-slate-200">
        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
          <div>
            <h3 class="font-black text-sm text-slate-900">{{ selectedProduct.name }}</h3>
            <p class="text-xs text-slate-500 font-mono">
              Article #: <strong>{{ selectedProduct.article_number || 'N/A' }}</strong> | Brand: <strong>{{ selectedProduct.brand || 'N/A' }}</strong>
            </p>
          </div>
          <button @click="clearSelectedProduct" class="text-xs font-bold text-red-600 hover:underline cursor-pointer">
            Change Product
          </button>
        </div>

        <!-- SIZE MATRIX (MOBILE CARDS LIST FOR < SM) -->
        <div class="sm:hidden space-y-2">
          <div
            v-for="item in selectedProduct.sizes"
            :key="'mob-' + item.product_variant_size_id"
            class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between gap-3 text-xs"
          >
            <div>
              <div class="font-black text-slate-900 font-mono text-sm">{{ item.size_name }}</div>
              <div class="text-[10px] text-slate-500 font-mono">{{ item.color_name || 'N/A' }} | SKU: {{ item.sku || 'N/A' }}</div>
              <div class="text-[11px] font-bold mt-1" :class="item.available_stock > 0 ? 'text-emerald-600' : 'text-slate-400'">
                Available: {{ item.available_stock }} pairs
              </div>
            </div>

            <div class="text-right">
              <label class="block text-[9px] font-bold text-slate-400 uppercase mb-0.5">Damage Qty</label>
              <input
                type="number"
                inputmode="numeric"
                min="0"
                :max="item.available_stock"
                v-model.number="item.damage_quantity"
                placeholder="0"
                class="w-20 bg-slate-50 border border-slate-300 text-center font-mono font-black text-sm text-slate-900 rounded-lg p-2 focus:bg-white focus:outline-none focus:ring-2 focus:ring-red-500/40"
              />
            </div>
          </div>
        </div>

        <!-- SIZE MATRIX TABLE (DESKTOP & TABLET >= SM) -->
        <div class="hidden sm:block overflow-x-auto">
          <table class="w-full text-left text-xs bg-white rounded-xl border border-slate-200 overflow-hidden">
            <thead>
              <tr class="bg-slate-900 text-white font-extrabold uppercase text-[10px]">
                <th class="py-2.5 px-4">Size Name</th>
                <th class="py-2.5 px-4">Color / SKU</th>
                <th class="py-2.5 px-4 text-center">Available Stock</th>
                <th class="py-2.5 px-4 text-right">Damage Qty Input</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium">
              <tr v-for="item in selectedProduct.sizes" :key="item.product_variant_size_id" class="hover:bg-slate-50/80">
                <td class="py-2.5 px-4 font-black text-slate-900 font-mono">{{ item.size_name }}</td>
                <td class="py-2.5 px-4 font-mono text-slate-600 text-[10px]">{{ item.color_name || 'N/A' }} ({{ item.sku || 'N/A' }})</td>
                <td class="py-2.5 px-4 text-center font-mono font-black" :class="item.available_stock > 0 ? 'text-emerald-600' : 'text-slate-400'">
                  {{ item.available_stock }} pairs
                </td>
                <td class="py-2.5 px-4 text-right">
                  <input
                    type="number"
                    min="0"
                    :max="item.available_stock"
                    v-model.number="item.damage_quantity"
                    placeholder="0"
                    class="w-24 bg-slate-50 border border-slate-200 text-center font-mono font-black text-xs text-slate-900 rounded-lg p-1.5 focus:bg-white focus:outline-none focus:ring-2 focus:ring-red-500/30"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- REASON & REMARKS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
          <div>
            <label class="block font-bold text-xs text-slate-700 mb-1">
              Damage Reason <span class="text-red-600">*</span>
            </label>
            <select v-model="damageReason" class="w-full bg-white border border-slate-200 rounded-xl p-2.5 font-bold text-xs text-slate-900 focus:outline-none">
              <option value="damaged">Damaged (General)</option>
              <option value="torn">Torn / Ripped</option>
              <option value="broken">Broken / Sole Detached</option>
              <option value="manufacturing_defect">Manufacturing Defect</option>
              <option value="lost">Lost / Missing</option>
              <option value="unusable">Unusable / Expired</option>
              <option value="other">Other (Requires Remarks)</option>
            </select>
          </div>

          <div>
            <label class="block font-bold text-xs text-slate-700 mb-1">
              Remarks / Explanation
              <span v-if="damageReason === 'other'" class="text-red-600 font-bold">* (MANDATORY)</span>
            </label>
            <input
              type="text"
              v-model="damageRemarks"
              placeholder="Provide reason notes..."
              class="w-full bg-white border border-slate-200 rounded-xl p-2.5 font-bold text-xs text-slate-900 focus:outline-none"
            />
          </div>
        </div>

        <!-- TOTAL & CONFIRM ACTION BUTTON -->
        <div class="flex items-center justify-between pt-3 border-t border-slate-200">
          <div class="text-xs font-mono font-black text-slate-900">
            Total Damage Selected: <span class="text-red-600 font-bold text-sm">{{ totalSelectedDamage }} pairs</span>
          </div>

          <button
            @click="openConfirmModal"
            :disabled="totalSelectedDamage <= 0"
            class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white font-black text-xs rounded-xl shadow-md shadow-red-600/20 uppercase tracking-wider disabled:opacity-40 cursor-pointer flex items-center gap-2"
          >
            <span>🗑️</span>
            <span>Confirm Stock Out Damage</span>
          </button>
        </div>
      </div>
    </div>

    <!-- 3. STOCK DAMAGE TRANSACTION HISTORY LEDGER -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden space-y-4 p-4">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-100 pb-3">
        <h2 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
          <span>📜</span>
          <span>Stock Out Damage Transaction History</span>
        </h2>

        <!-- FILTERS -->
        <div class="flex flex-wrap items-center gap-2 text-xs">
          <input
            type="text"
            v-model="historySearch"
            @input="fetchHistory"
            placeholder="Search Damage # / Product..."
            class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 font-bold text-slate-700 focus:outline-none"
          />

          <select v-model="historyReason" @change="fetchHistory" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 font-bold text-slate-700 focus:outline-none">
            <option value="">All Reasons</option>
            <option value="damaged">Damaged</option>
            <option value="torn">Torn</option>
            <option value="broken">Broken</option>
            <option value="manufacturing_defect">Manufacturing Defect</option>
            <option value="lost">Lost</option>
            <option value="unusable">Unusable</option>
            <option value="other">Other</option>
          </select>
        </div>
      </div>

      <!-- HISTORY MOBILE CARDS LIST (< SM) -->
      <div v-if="!loadingHistory && historyList.length > 0" class="sm:hidden space-y-3">
        <MobileListCard
          v-for="tx in historyList"
          :key="'mob-tx-' + tx.id"
          :title="tx.damage_number"
          :subtitle="formatDate(tx.created_at) + ' • By ' + (tx.creator?.name || 'User')"
          :status="tx.reason"
          status-type="danger"
          :metric="'-' + tx.total_quantity + ' pairs'"
        >
          <div v-if="tx.remarks" class="text-slate-600 text-xs font-medium">
            Remarks: {{ tx.remarks }}
          </div>
          <div class="text-[11px] font-bold text-slate-500 font-mono">
            Items count: {{ tx.items?.length || 0 }} items
          </div>

          <template #actions>
            <button @click="openDetailModal(tx)" class="px-3 py-1.5 bg-slate-900 text-white rounded-lg font-bold text-xs cursor-pointer">
              🔍 Details
            </button>
          </template>
        </MobileListCard>
      </div>

      <!-- HISTORY TABLE (>= SM) -->
      <div class="hidden sm:block overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="bg-slate-900 text-white font-extrabold uppercase tracking-wider text-[11px]">
              <th class="py-3 px-4">Damage # & Date</th>
              <th class="py-3 px-4">Reason & Remarks</th>
              <th class="py-3 px-4 text-center">Items Count</th>
              <th class="py-3 px-4 text-center font-mono">Total Damage Qty</th>
              <th class="py-3 px-4 text-center">Created By</th>
              <th class="py-3 px-4 text-center">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium">
            <tr v-if="loadingHistory">
              <td colspan="6" class="text-center py-12 text-slate-400 font-bold">
                Loading damage transaction history...
              </td>
            </tr>
            <tr v-else-if="historyList.length === 0">
              <td colspan="6" class="text-center py-12 text-slate-500 font-bold">
                No stock damage transactions recorded.
              </td>
            </tr>
            <tr v-for="tx in historyList" :key="tx.id" class="hover:bg-slate-50">
              <td class="py-3 px-4">
                <div class="font-mono font-black text-red-600 text-xs">{{ tx.damage_number }}</div>
                <div class="text-[10px] text-slate-500 font-bold">{{ formatDate(tx.created_at) }}</div>
              </td>
              <td class="py-3 px-4">
                <span class="px-2 py-0.5 bg-red-100 text-red-800 font-bold text-[10px] rounded uppercase">
                  {{ tx.reason }}
                </span>
                <p v-if="tx.remarks" class="text-[11px] text-slate-600 font-medium mt-0.5">{{ tx.remarks }}</p>
              </td>
              <td class="py-3 px-4 text-center font-bold text-slate-700">
                {{ tx.items?.length || 0 }} Items
              </td>
              <td class="py-3 px-4 text-center font-mono font-black text-red-600">
                -{{ tx.total_quantity }} pairs
              </td>
              <td class="py-3 px-4 text-center font-bold text-slate-700">
                {{ tx.creator?.name || 'User' }}
              </td>
              <td class="py-3 px-4 text-center">
                <button @click="openDetailModal(tx)" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg text-[10px] font-black cursor-pointer">
                  🔍 View Details
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- DESTRUCTIVE CONFIRMATION MODAL -->
    <div v-if="showConfirmModal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto font-sans">
      <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-5 my-8">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h2 class="text-lg font-black text-slate-900 text-red-600 flex items-center gap-2">
            <span>⚠️</span>
            <span>Confirm Stock Out – Damage</span>
          </h2>
          <button @click="showConfirmModal = false" class="text-slate-400 hover:text-slate-600 font-bold cursor-pointer">✕</button>
        </div>

        <div class="space-y-3 text-xs bg-red-50/50 p-4 rounded-2xl border border-red-200 text-slate-900">
          <div class="font-bold">Product: {{ selectedProduct?.name }}</div>
          <div class="font-mono text-[11px]">Article #: {{ selectedProduct?.article_number || 'N/A' }}</div>

          <div class="border-t border-red-200 pt-2 font-mono space-y-1">
            <div class="font-black text-slate-900 mb-1">Items To Be Damaged:</div>
            <div v-for="item in activeDamageItems" :key="item.product_variant_size_id" class="flex justify-between text-slate-700">
              <span>Size {{ item.size_name }}:</span>
              <span class="font-bold text-red-600">-{{ item.damage_quantity }} pairs (Available: {{ item.available_stock }})</span>
            </div>
          </div>

          <div class="border-t border-red-200 pt-2 flex justify-between font-black text-sm">
            <span>Total Quantity:</span>
            <span class="text-red-600">-{{ totalSelectedDamage }} pairs</span>
          </div>

          <div class="text-[11px] text-slate-600">
            <strong>Reason:</strong> {{ damageReason.toUpperCase() }} {{ damageRemarks ? `(${damageRemarks})` : '' }}
          </div>
        </div>

        <p class="text-[11px] text-slate-500 font-medium">
          Warning: This action will permanently remove the items from available inventory and log an immutable stock movement record.
        </p>

        <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
          <button @click="showConfirmModal = false" type="button" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl font-bold text-xs cursor-pointer">Cancel</button>
          <button @click="submitDamage" :disabled="submitting" type="button" class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md shadow-red-600/20 uppercase tracking-wider disabled:opacity-50 cursor-pointer">
            {{ submitting ? 'Processing Removal...' : 'Confirm Damage & Remove From Stock' }}
          </button>
        </div>
      </div>
    </div>

    <!-- DETAIL MODAL / SLIDE-OVER -->
    <div v-if="showDetailModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto font-sans">
      <div class="bg-white rounded-3xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 space-y-5 my-8">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h2 class="text-lg font-black text-slate-900 font-mono text-red-600">{{ selectedDetailTx?.damage_number }}</h2>
            <p class="text-xs text-slate-500 font-bold">Stock Out Damage Details</p>
          </div>
          <button @click="showDetailModal = false" class="text-slate-400 hover:text-slate-600 font-bold cursor-pointer">✕</button>
        </div>

        <div class="grid grid-cols-2 gap-4 text-xs font-mono bg-slate-50 p-4 rounded-2xl border border-slate-200">
          <div>
            <div>Date: {{ formatDate(selectedDetailTx?.created_at) }}</div>
            <div>Store: {{ selectedDetailTx?.store?.name || 'Main Outlet' }}</div>
            <div>Created By: {{ selectedDetailTx?.creator?.name || 'User' }}</div>
          </div>
          <div class="text-right">
            <div>Reason: <span class="font-bold text-red-600 uppercase">{{ selectedDetailTx?.reason }}</span></div>
            <div>Remarks: {{ selectedDetailTx?.remarks || 'N/A' }}</div>
            <div class="font-black text-slate-900 mt-1">Total Quantity: -{{ selectedDetailTx?.total_quantity }} pairs</div>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border border-slate-200 rounded-xl overflow-hidden">
            <thead>
              <tr class="bg-slate-900 text-white font-extrabold uppercase text-[10px]">
                <th class="py-2.5 px-3">Product / Article</th>
                <th class="py-2.5 px-3 text-center">Size</th>
                <th class="py-2.5 px-3 text-center">Stock Before</th>
                <th class="py-2.5 px-3 text-center">Damage Qty</th>
                <th class="py-2.5 px-3 text-center">Stock After</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium">
              <tr v-for="item in selectedDetailTx?.items" :key="item.id" class="hover:bg-slate-50">
                <td class="py-2 px-3 font-bold text-slate-900">
                  {{ item.product_variant_size?.product_variant?.product?.name || 'Product' }}
                  <span class="text-[10px] text-slate-500 block font-mono">Article: {{ item.product_variant_size?.product_variant?.product?.article_number || 'N/A' }}</span>
                </td>
                <td class="py-2 px-3 text-center font-mono font-bold text-slate-800">
                  {{ item.product_variant_size?.size?.name || 'Size' }}
                </td>
                <td class="py-2 px-3 text-center font-mono text-slate-600">{{ item.available_stock_before }}</td>
                <td class="py-2 px-3 text-center font-mono font-black text-red-600">-{{ item.quantity }}</td>
                <td class="py-2 px-3 text-center font-mono font-bold text-slate-900">{{ item.available_stock_after }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex justify-end pt-2">
          <button @click="showDetailModal = false" class="px-5 py-2 bg-slate-900 text-white font-bold text-xs rounded-xl cursor-pointer">
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
import MobileListCard from '../../components/ui/MobileListCard.vue';

const selectedStoreId = ref(1);
const searchQuery = ref('');
const searching = ref(false);
const searchHasRun = ref(false);
const searchError = ref('');
const searchResults = ref([]);
const selectedProduct = ref(null);

const damageReason = ref('damaged');
const damageRemarks = ref('');

const notification = reactive({ show: false, message: '', type: 'success' });
const submitting = ref(false);
const showConfirmModal = ref(false);

const historyList = ref([]);
const loadingHistory = ref(false);
const historySearch = ref('');
const historyReason = ref('');

const showDetailModal = ref(false);
const selectedDetailTx = ref(null);

let debounceTimer = null;

function showToast(msg, type = 'success') {
  notification.message = msg;
  notification.type = type;
  notification.show = true;
  setTimeout(() => { notification.show = false; }, 4000);
}

function onSearchInput() {
  clearTimeout(debounceTimer);
  searchError.value = '';
  const query = searchQuery.value.trim();
  if (query.length < 2) {
    searchResults.value = [];
    searchHasRun.value = false;
    searching.value = false;
    return;
  }
  searching.value = true;
  debounceTimer = setTimeout(async () => {
    try {
      const res = await api.get('/inventory/damage/product-search', {
        params: { q: query, store_id: selectedStoreId.value },
      });
      const data = res.data?.data || res.data || [];
      searchResults.value = Array.isArray(data) ? data : [];
      searchHasRun.value = true;
    } catch (err) {
      console.error('Product search error:', err);
      searchError.value = err.response?.data?.message || 'Failed to fetch search results.';
      searchResults.value = [];
      searchHasRun.value = true;
    } finally {
      searching.value = false;
    }
  }, 300);
}

function selectProduct(prod) {
  selectedProduct.value = JSON.parse(JSON.stringify(prod));
  searchResults.value = [];
  searchHasRun.value = false;
}

function clearSelectedProduct() {
  selectedProduct.value = null;
  searchQuery.value = '';
  searchHasRun.value = false;
  searchError.value = '';
}

const activeDamageItems = computed(() => {
  if (!selectedProduct.value || !selectedProduct.value.sizes) return [];
  return selectedProduct.value.sizes.filter(s => Number(s.damage_quantity || 0) > 0);
});

const totalSelectedDamage = computed(() => {
  return activeDamageItems.value.reduce((sum, item) => sum + Number(item.damage_quantity || 0), 0);
});

function openConfirmModal() {
  if (totalSelectedDamage.value <= 0) {
    showToast('Please enter a valid damage quantity greater than zero.', 'error');
    return;
  }
  if (damageReason.value === 'other' && (!damageRemarks.value || damageRemarks.value.trim().length < 3)) {
    showToast('Remarks/explanation is mandatory when reason is Other.', 'error');
    return;
  }
  showConfirmModal.value = true;
}

async function submitDamage() {
  if (submitting.value) return;
  submitting.value = true;

  try {
    const payloadItems = activeDamageItems.value.map(item => ({
      product_variant_size_id: item.product_variant_size_id,
      quantity: Number(item.damage_quantity),
    }));

    await api.post('/inventory/damage', {
      store_id: selectedStoreId.value,
      reason: damageReason.value,
      remarks: damageRemarks.value,
      items: payloadItems,
    });

    showConfirmModal.value = false;
    showToast('Stock Out – Damage transaction confirmed successfully!', 'success');
    clearSelectedProduct();
    fetchHistory();
  } catch (err) {
    showToast(err.response?.data?.message || 'Failed to process stock damage.', 'error');
  } finally {
    submitting.value = false;
  }
}

async function fetchHistory() {
  loadingHistory.value = true;
  try {
    const res = await api.get('/inventory/damage', {
      params: {
        store_id: selectedStoreId.value,
        search: historySearch.value,
        reason: historyReason.value,
      },
    });
    const payload = res.data?.data || res.data;
    historyList.value = payload.items || [];
  } catch (err) {
    console.error('Failed to fetch damage history:', err);
  } finally {
    loadingHistory.value = false;
  }
}

function openDetailModal(tx) {
  selectedDetailTx.value = tx;
  showDetailModal.value = true;
}

function formatDate(dt) {
  if (!dt) return '—';
  return new Date(dt).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' });
}

onMounted(() => {
  fetchHistory();
});
</script>
