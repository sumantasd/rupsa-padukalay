<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-16 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">↩️ Purchase Returns (Return to Vendor)</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Process Return to Vendor (RTV) for STR-001 (Deducts size-wise stock & adjusts Supplier Payable / Credit)
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          @click="openReturnModal"
          class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md shadow-red-600/20 transition-all uppercase tracking-wider flex items-center gap-1.5 cursor-pointer"
        >
          <span>↩️</span>
          <span>New Purchase Return</span>
        </button>
      </div>
    </div>

    <!-- FILTER BAR -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex flex-wrap items-center justify-between gap-3 text-xs">
      <div class="relative max-w-xs w-64">
        <input
          v-model="filters.search"
          @input="debouncedFetch"
          type="text"
          placeholder="Search Return #, Supplier..."
          class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-red-500/20"
        />
        <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
      </div>
    </div>

    <!-- RETURNS TABLE -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="bg-slate-900 text-white font-extrabold uppercase tracking-wider text-[11px]">
              <th class="py-3.5 px-4">Return # & Date</th>
              <th class="py-3.5 px-4">Supplier</th>
              <th class="py-3.5 px-4">Return Reason</th>
              <th class="py-3.5 px-4 text-center">Refund Mode</th>
              <th class="py-3.5 px-4 text-right font-mono">Total Return Amount</th>
              <th class="py-3.5 px-4 text-center">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium">
            <tr v-if="loading">
              <td colspan="6" class="text-center py-12 text-slate-400 font-bold">
                Loading purchase returns...
              </td>
            </tr>
            <tr v-else-if="returnsList.length === 0">
              <td colspan="6" class="text-center py-12 text-slate-500 font-bold">
                No purchase returns recorded. Click "New Purchase Return" to record one.
              </td>
            </tr>
            <tr v-for="ret in returnsList" :key="ret.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="py-3 px-4">
                <div class="font-mono font-black text-red-600 text-xs">{{ ret.return_number }}</div>
                <div class="text-[10px] text-slate-400 font-medium">{{ ret.created_at }}</div>
              </td>
              <td class="py-3 px-4">
                <div class="font-bold text-slate-900">{{ ret.supplier_name }}</div>
                <div class="text-[10px] text-slate-500 font-mono">Phone: {{ ret.supplier_phone }}</div>
              </td>
              <td class="py-3 px-4 font-bold text-slate-700">
                {{ ret.reason || 'Damaged' }}
              </td>
              <td class="py-3 px-4 text-center uppercase font-bold text-slate-800 text-[10px]">
                {{ ret.refund_mode || 'SUPPLIER CREDIT' }}
              </td>
              <td class="py-3 px-4 text-right font-mono font-black text-slate-900">
                ₹{{ formatCurrency(ret.total_return_amount) }}
              </td>
              <td class="py-3 px-4 text-center">
                <span class="px-2.5 py-1 rounded-full bg-red-100 text-red-800 text-[10px] font-black uppercase tracking-wider">
                  STOCK DEDUCTED
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between text-xs font-bold text-slate-600">
        <div>Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} Returns)</div>
        <div class="flex items-center gap-2">
          <button @click="fetchReturns(pagination.current_page - 1)" :disabled="pagination.current_page <= 1" class="px-3 py-1 bg-white border rounded-lg disabled:opacity-40 cursor-pointer">← Prev</button>
          <button @click="fetchReturns(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page" class="px-3 py-1 bg-white border rounded-lg disabled:opacity-40 cursor-pointer">Next →</button>
        </div>
      </div>
    </div>

    <!-- NEW PURCHASE RETURN SIZE-MATRIX WIZARD MODAL -->
    <Teleport to="body">
      <div v-if="showReturnModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-[400] flex items-center justify-center p-0 sm:p-4 overflow-hidden font-sans">
        <div class="bg-white rounded-none sm:rounded-3xl max-w-5xl w-full p-0 shadow-2xl border-0 sm:border sm:border-slate-200 flex flex-col h-[100dvh] sm:h-auto sm:max-h-[90dvh] overflow-hidden my-0 sm:my-auto">
          <!-- Header -->
          <div class="flex items-center justify-between border-b border-slate-200 px-4 sm:px-6 py-3.5 sm:py-4 shrink-0 bg-white z-10">
            <h2 class="text-base sm:text-lg font-black text-slate-900">↩️ New Purchase Return (Return to Vendor)</h2>
            <button @click="showReturnModal = false" class="text-slate-400 hover:text-slate-600 font-bold p-1 cursor-pointer">✕</button>
          </div>

          <!-- Body -->
          <div class="p-4 sm:p-6 overflow-y-auto flex-1 space-y-5 text-xs text-slate-800">
            <!-- 1. MANDATORY SUPPLIER SELECTION CARD -->
            <div class="bg-red-50/50 p-4 rounded-2xl border-2 border-red-200/80 space-y-3">
              <label class="block font-black text-xs text-red-900 uppercase tracking-wider">
                1. Select Supplier <span class="text-red-600">* Mandatory</span>
              </label>

              <select v-model="returnForm.supplier_id" @change="onSupplierSelect" class="w-full bg-white border border-red-300 rounded-xl px-3.5 py-2.5 font-bold text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                <option value="">-- Choose Supplier --</option>
                <option v-for="sup in suppliersList" :key="sup.id" :value="sup.id">
                  {{ sup.name }} ({{ sup.company_name || 'No Company' }}) — Phone: {{ sup.phone }}
                </option>
              </select>
            </div>

            <!-- REASON & REFUND MODE -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
              <div>
                <label class="block font-bold text-slate-700 mb-1">Return Reason <span class="text-red-600">*</span></label>
                <select v-model="returnForm.reason" class="w-full bg-slate-50 border rounded-xl px-3 py-2 font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500/20">
                  <option value="Damaged">Damaged Goods</option>
                  <option value="Defective">Defective / Quality Issue</option>
                  <option value="Wrong Product">Wrong Product Sent</option>
                  <option value="Wrong Size">Wrong Footwear Size</option>
                  <option value="Excess Quantity">Excess Quantity</option>
                  <option value="Supplier Request">Supplier Recall / Request</option>
                  <option value="Other">Other</option>
                </select>
              </div>

              <div>
                <label class="block font-bold text-slate-700 mb-1">Refund / Adjustment Mode</label>
                <select v-model="returnForm.refund_mode" class="w-full bg-slate-50 border rounded-xl px-3 py-2 font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500/20">
                  <option value="supplier_credit">💳 Adjust Against Supplier Payable (Supplier Credit)</option>
                  <option value="cash_refund">💵 Cash Refund Received</option>
                  <option value="bank_transfer">🏦 Bank Transfer Refund</option>
                </select>
              </div>
            </div>

            <!-- 2. SEARCH FOOTWEAR PRODUCT MATRIX -->
            <div class="space-y-3 relative">
              <label class="block font-black text-xs text-slate-900 uppercase tracking-wider">
                2. Search & Select Footwear Product
              </label>

              <div class="relative">
                <input
                  type="text"
                  v-model="productSearchQuery"
                  @input="onProductSearchInput"
                  placeholder="🔍 Type Product Name / Article Number / SKU / Brand (e.g. RP-610, BATA, Sneaker)..."
                  class="w-full bg-slate-50 border-2 border-slate-200 rounded-xl px-4 py-2.5 font-bold text-xs text-slate-900 placeholder-slate-400 focus:bg-white focus:border-red-500 focus:outline-none"
                />
                <span v-if="searchLoading" class="absolute right-3 top-2.5 text-xs text-slate-400 animate-spin">⌛</span>
              </div>

              <!-- Live Search Results Dropdown -->
              <div
                v-if="showSearchDropdown && searchResultProducts.length > 0"
                class="absolute left-0 right-0 top-full mt-1 bg-white border-2 border-slate-300 rounded-2xl shadow-xl z-50 max-h-60 overflow-y-auto divide-y divide-slate-100"
              >
                <div
                  v-for="p in searchResultProducts"
                  :key="p.product_id"
                  @click="addFootwearProduct(p)"
                  class="p-3 hover:bg-slate-50 cursor-pointer flex items-center justify-between transition-colors"
                >
                  <div class="flex items-center gap-3">
                    <span class="px-2 py-0.5 bg-red-600 text-white font-mono font-black rounded text-[10px]">
                      ARTICLE: {{ p.article_number }}
                    </span>
                    <div>
                      <div class="font-black text-xs text-slate-900">{{ p.product_name }}</div>
                      <div class="text-[11px] text-slate-500">Brand: {{ p.brand_name }}</div>
                    </div>
                  </div>
                  <div class="text-right">
                    <span class="px-2.5 py-1 bg-slate-100 text-slate-700 font-bold text-[10px] rounded-lg">
                      + Add Product Size Matrix
                    </span>
                  </div>
                </div>
              </div>

              <div v-else-if="showSearchDropdown && searchResultProducts.length === 0 && !searchLoading" class="p-3 bg-amber-50 border border-amber-200 text-amber-800 text-xs font-bold rounded-xl">
                No matching footwear products found for "{{ productSearchQuery }}".
              </div>
            </div>

            <!-- SELECTED FOOTWEAR PRODUCTS SIZE MATRIX TABLES -->
            <div v-if="selectedFootwearProducts.length > 0" class="space-y-6">
              <div
                v-for="(prod, pIdx) in selectedFootwearProducts"
                :key="prod.product_id"
                class="bg-white border-2 border-slate-200 rounded-2xl overflow-hidden shadow-xs space-y-3"
              >
                <!-- Product Header Bar -->
                <div class="bg-slate-900 text-white p-3 sm:p-4 flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <span class="px-2 py-0.5 bg-red-600 text-white font-mono font-black rounded text-[11px]">
                      ARTICLE: {{ prod.article_number }}
                    </span>
                    <span class="font-black text-sm ml-2">{{ prod.product_name }}</span>
                    <span class="text-xs text-slate-400 ml-2">Brand: {{ prod.brand_name }}</span>
                  </div>

                  <div class="flex items-center gap-2">
                    <button @click="promptBulkFillRate(prod)" type="button" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-amber-400 text-[10px] font-black rounded-lg border border-slate-700 transition-colors cursor-pointer">
                      ⚡ Fill Rate
                    </button>
                    <button @click="removeFootwearProduct(pIdx)" type="button" class="px-2.5 py-1 bg-red-600/80 hover:bg-red-600 text-white text-[11px] font-bold rounded-lg transition-colors cursor-pointer">
                      🗑️ Remove
                    </button>
                  </div>
                </div>

                <!-- Size-Wise Return Matrix Table -->
                <div class="px-3 pb-3 overflow-x-auto">
                  <table class="w-full text-left text-xs border-collapse min-w-[550px]">
                    <thead>
                      <tr class="bg-slate-100 text-slate-700 font-black uppercase text-[10px] border-b border-slate-200">
                        <th class="p-2.5">Footwear Size</th>
                        <th class="p-2.5 text-center font-mono">SKU Code</th>
                        <th class="p-2.5 text-center">Current Stock</th>
                        <th class="p-2.5 text-center">Return Qty</th>
                        <th class="p-2.5 text-center">Return Rate (₹)</th>
                        <th class="p-2.5 text-right font-mono">Line Total</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                      <template v-for="v in prod.variants" :key="v.variant_id">
                        <tr v-for="s in v.sizes" :key="s.product_variant_size_id" class="hover:bg-slate-50/80 transition-colors">
                          <td class="p-2 font-black text-slate-900">
                            <span class="px-2.5 py-1 bg-slate-900 text-white font-mono rounded-lg text-[11px]">
                              {{ s.size_display }}
                            </span>
                            <span class="text-[10px] text-slate-500 ml-1.5 font-normal">({{ v.color_name }})</span>
                          </td>
                          <td class="p-2 text-center font-mono text-[11px] font-bold text-slate-600">
                            {{ s.sku }}
                          </td>
                          <td class="p-2 text-center font-bold text-slate-700">
                            <span :class="s.current_stock <= 0 ? 'text-red-600 font-black' : 'text-slate-800'">
                              {{ s.current_stock }} pcs
                            </span>
                          </td>
                          <td class="p-2 text-center">
                            <input
                              type="number"
                              v-model.number="s.return_qty"
                              min="0"
                              :max="s.current_stock"
                              placeholder="0"
                              class="w-20 bg-slate-50 border border-slate-300 rounded-xl px-2 py-1 text-center font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-red-500/20"
                              :class="s.return_qty > s.current_stock ? 'border-red-500 text-red-600 bg-red-50' : ''"
                            />
                            <div v-if="s.return_qty > s.current_stock" class="text-[9px] font-black text-red-600 mt-0.5">
                              Exceeds Stock!
                            </div>
                          </td>
                          <td class="p-2 text-center">
                            <input
                              type="number"
                              step="0.01"
                              v-model.number="s.cost_price"
                              min="0"
                              placeholder="Rate ₹"
                              class="w-24 bg-slate-50 border border-slate-300 rounded-xl px-2 py-1 text-center font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-red-500/20"
                            />
                          </td>
                          <td class="p-2 text-right font-mono font-black text-slate-900">
                            ₹{{ formatCurrency((s.return_qty || 0) * (s.cost_price || 0)) }}
                          </td>
                        </tr>
                      </template>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- TOTALS & SUMMARY BAR -->
            <div class="p-4 bg-slate-900 text-white rounded-2xl flex flex-wrap items-center justify-between text-xs font-mono font-bold gap-3">
              <div>
                TOTAL RETURN QUANTITY: <span class="text-amber-400 font-black text-sm">{{ totalReturnQuantity }}</span> pairs
              </div>
              <div class="text-right">
                <div class="text-sm text-red-400 font-black">TOTAL RETURN VALUE: ₹{{ formatCurrency(computedTotalReturnAmount) }}</div>
              </div>
            </div>
          </div>

          <!-- ACTIONS FOOTER -->
          <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-4 sm:px-6 py-3 sm:py-4 shrink-0 bg-slate-50 sm:rounded-b-3xl z-10">
            <button @click="showReturnModal = false" type="button" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 rounded-xl font-bold text-xs cursor-pointer">Cancel</button>
            <button
              @click="submitReturn"
              :disabled="saving"
              type="button"
              class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md shadow-red-600/20 uppercase tracking-wider cursor-pointer"
            >
              {{ saving ? 'Processing Return & Deducting Stock...' : 'Confirm Return & Deduct Stock' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue';
import api from '../../services/api';

const loading = ref(false);
const saving = ref(false);
const showReturnModal = ref(false);

watch(showReturnModal, (isOpen) => {
  if (isOpen) {
    document.body.style.overflow = 'hidden';
  } else {
    document.body.style.overflow = '';
  }
});

const returnsList = ref([]);
const suppliersList = ref([]);
const selectedSupplier = ref(null);

const productSearchQuery = ref('');
const searchResultProducts = ref([]);
const searchLoading = ref(false);
const showSearchDropdown = ref(false);
const selectedFootwearProducts = ref([]);

const filters = reactive({ search: '' });
const pagination = reactive({ current_page: 1, per_page: 15, total: 0, last_page: 1 });

const returnForm = reactive({
  supplier_id: '',
  reason: 'Damaged',
  refund_mode: 'supplier_credit',
});

const totalReturnQuantity = computed(() => {
  let count = 0;
  selectedFootwearProducts.value.forEach(p => {
    p.variants?.forEach(v => {
      v.sizes?.forEach(s => {
        if (Number(s.return_qty) > 0) {
          count += Number(s.return_qty);
        }
      });
    });
  });
  return count;
});

const computedTotalReturnAmount = computed(() => {
  let total = 0;
  selectedFootwearProducts.value.forEach(p => {
    p.variants?.forEach(v => {
      v.sizes?.forEach(s => {
        if (Number(s.return_qty) > 0) {
          total += Number(s.return_qty) * Number(s.cost_price || 0);
        }
      });
    });
  });
  return Math.round(total * 100) / 100;
});

let debounceTimer = null;
function debouncedFetch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => fetchReturns(1), 300);
}

async function fetchReturns(page = 1) {
  loading.value = true;
  try {
    const res = await api.get('/purchases/returns', { params: { page, per_page: pagination.per_page, search: filters.search || undefined } });
    const payload = res.data?.data || res.data;
    returnsList.value = payload.items || [];
    if (payload.pagination) Object.assign(pagination, payload.pagination);
  } catch (err) {
    console.error('Failed to load purchase returns:', err);
  } finally {
    loading.value = false;
  }
}

async function loadMasterData() {
  try {
    const res = await api.get('/suppliers', { params: { per_page: 100 } });
    suppliersList.value = res.data?.data?.items || res.data?.data || res.data?.items || [];
  } catch (e) {
    console.error('Failed to load suppliers:', e);
  }
}

function openReturnModal() {
  returnForm.supplier_id = '';
  returnForm.reason = 'Damaged';
  returnForm.refund_mode = 'supplier_credit';
  selectedSupplier.value = null;
  selectedFootwearProducts.value = [];
  productSearchQuery.value = '';
  searchResultProducts.value = [];
  showSearchDropdown.value = false;
  showReturnModal.value = true;
}

function onSupplierSelect() {
  selectedSupplier.value = suppliersList.value.find(s => s.id === returnForm.supplier_id) || null;
}

let searchDebounce = null;
function onProductSearchInput() {
  clearTimeout(searchDebounce);
  const q = productSearchQuery.value.trim();
  if (!q) {
    searchResultProducts.value = [];
    showSearchDropdown.value = false;
    return;
  }

  showSearchDropdown.value = true;
  searchLoading.value = true;

  searchDebounce = setTimeout(async () => {
    try {
      const res = await api.get('/products/size-matrix-search', { params: { q } });
      const items = res.data?.data || res.data || [];
      searchResultProducts.value = items;
    } catch (e) {
      console.error('Failed to search product size matrix:', e);
      searchResultProducts.value = [];
    } finally {
      searchLoading.value = false;
    }
  }, 300);
}

function addFootwearProduct(product) {
  if (selectedFootwearProducts.value.some(p => p.product_id === product.product_id)) {
    alert(`Product ${product.article_number} is already added.`);
    showSearchDropdown.value = false;
    productSearchQuery.value = '';
    return;
  }

  const clonedProd = JSON.parse(JSON.stringify(product));
  clonedProd.variants?.forEach(v => {
    v.sizes?.forEach(s => {
      s.return_qty = 0;
    });
  });

  selectedFootwearProducts.value.push(clonedProd);
  showSearchDropdown.value = false;
  productSearchQuery.value = '';
}

function removeFootwearProduct(idx) {
  selectedFootwearProducts.value.splice(idx, 1);
}

function promptBulkFillRate(prod) {
  const rateInput = prompt(`Enter default return rate (₹) for ALL sizes of Article: ${prod.article_number}:`, '750');
  if (rateInput !== null) {
    const rate = Number(rateInput);
    if (!isNaN(rate) && rate >= 0) {
      prod.variants?.forEach(v => {
        v.sizes?.forEach(s => {
          s.cost_price = rate;
        });
      });
    }
  }
}

async function submitReturn() {
  if (!returnForm.supplier_id) {
    alert('Supplier is required for every purchase return transaction.');
    return;
  }

  if (!returnForm.reason) {
    alert('Please select a valid return reason.');
    return;
  }

  const items = [];
  let overStockError = false;

  selectedFootwearProducts.value.forEach(p => {
    p.variants?.forEach(v => {
      v.sizes?.forEach(s => {
        const qty = Number(s.return_qty || 0);
        if (qty > 0) {
          if (qty > Number(s.current_stock || 0)) {
            overStockError = true;
          }
          items.push({
            product_variant_size_id: s.product_variant_size_id,
            quantity: qty,
            cost_price: Number(s.cost_price || 0),
          });
        }
      });
    });
  });

  if (overStockError) {
    alert('Return quantity cannot exceed current physical stock for any footwear size.');
    return;
  }

  if (items.length === 0) {
    alert('Please specify at least one size-wise return quantity greater than 0.');
    return;
  }

  saving.value = true;
  try {
    await api.post('/purchases/returns', {
      supplier_id: returnForm.supplier_id,
      reason: returnForm.reason,
      refund_mode: returnForm.refund_mode,
      store_id: 1,
      items,
    });

    showReturnModal.value = false;
    alert('Purchase Return confirmed! Size-wise inventory deducted and Supplier Payable adjusted.');
    fetchReturns(1);
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to process purchase return.');
  } finally {
    saving.value = false;
  }
}

function formatCurrency(val) {
  return Number(val || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function handleGlobalClick() {
  showSearchDropdown.value = false;
}

onMounted(() => {
  fetchReturns(1);
  loadMasterData();
  document.addEventListener('click', handleGlobalClick);
});

onUnmounted(() => {
  document.removeEventListener('click', handleGlobalClick);
});
</script>
