<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-16 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">📥 Goods Receive Notes (GRN)</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Receive vendor stock into STR-001 (Automatically increases Inventory stock & logs Purchase movements)
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          @click="openReceiveModal"
          class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md shadow-red-600/20 transition-all uppercase tracking-wider flex items-center gap-1.5"
        >
          <span>📥</span>
          <span>Receive Goods Against PO</span>
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
          placeholder="Search GRN #, PO #, Supplier..."
          class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-red-500/20"
        />
        <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
      </div>
    </div>

    <!-- GRN TABLE -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="bg-slate-900 text-white font-extrabold uppercase tracking-wider text-[11px]">
              <th class="py-3.5 px-4">GRN # & Date</th>
              <th class="py-3.5 px-4">PO Reference</th>
              <th class="py-3.5 px-4">Supplier</th>
              <th class="py-3.5 px-4">Vendor Invoice #</th>
              <th class="py-3.5 px-4 text-center">Received Qty</th>
              <th class="py-3.5 px-4 text-right font-mono">Total Cost</th>
              <th class="py-3.5 px-4 text-center">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium">
            <tr v-if="loading">
              <td colspan="7" class="text-center py-12 text-slate-400 font-bold">
                Loading goods receive notes...
              </td>
            </tr>
            <tr v-else-if="grnList.length === 0">
              <td colspan="7" class="text-center py-12 text-slate-500 font-bold">
                No goods receive notes found. Click "Receive Goods Against PO" to record stock receiving.
              </td>
            </tr>
            <tr v-for="grn in grnList" :key="grn.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="py-3 px-4">
                <div class="font-mono font-black text-red-600 text-xs">{{ grn.grn_number }}</div>
                <div class="text-[10px] text-slate-400 font-medium">{{ grn.received_date }}</div>
              </td>
              <td class="py-3 px-4 font-mono font-bold text-slate-800">
                {{ grn.po_number }}
              </td>
              <td class="py-3 px-4">
                <div class="font-bold text-slate-900">{{ grn.supplier_name }}</div>
                <div class="text-[10px] text-slate-500 font-mono">{{ grn.supplier_phone }}</div>
              </td>
              <td class="py-3 px-4 font-mono text-slate-700">
                {{ grn.supplier_invoice_number || 'N/A' }}
              </td>
              <td class="py-3 px-4 text-center font-black text-emerald-700 text-sm">
                +{{ grn.total_items_received }} pairs
              </td>
              <td class="py-3 px-4 text-right font-mono font-black text-slate-900">
                ₹{{ formatCurrency(grn.total_cost) }}
              </td>
              <td class="py-3 px-4 text-center">
                <span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-black uppercase tracking-wider">
                  STOCK INCREASED
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between text-xs font-bold text-slate-600">
        <div>Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} GRNs)</div>
        <div class="flex items-center gap-2">
          <button @click="fetchGrns(pagination.current_page - 1)" :disabled="pagination.current_page <= 1" class="px-3 py-1 bg-white border rounded-lg disabled:opacity-40">← Prev</button>
          <button @click="fetchGrns(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page" class="px-3 py-1 bg-white border rounded-lg disabled:opacity-40">Next →</button>
        </div>
      </div>
    </div>

    <!-- GOODS RECEIVE WIZARD MODAL -->
    <Teleport to="body">
      <div v-if="showReceiveModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-[400] flex items-center justify-center p-0 sm:p-4 overflow-hidden font-sans">
        <div class="bg-white rounded-none sm:rounded-3xl max-w-4xl w-full p-0 shadow-2xl border-0 sm:border sm:border-slate-200 flex flex-col h-[100dvh] sm:h-auto sm:max-h-[90dvh] overflow-hidden my-0 sm:my-auto">
          <!-- Header -->
          <div class="flex items-center justify-between border-b border-slate-200 px-4 sm:px-6 py-3.5 sm:py-4 shrink-0 bg-white z-10">
            <h2 class="text-base sm:text-lg font-black text-slate-900">📥 Receive Goods Against Purchase Order</h2>
            <button @click="showReceiveModal = false" class="text-slate-400 hover:text-slate-600 font-bold p-1">✕</button>
          </div>

          <!-- Body -->
          <div class="p-4 sm:p-6 overflow-y-auto flex-1 space-y-5 text-xs text-slate-800">
            <!-- PO SELECTION -->
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3">
              <label class="block font-black text-xs text-slate-800 uppercase tracking-wider">Select Purchase Order to Receive</label>
              <select v-model="selectedPoId" @change="onPoSelect" class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 font-bold text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                <option value="">-- Choose Pending Purchase Order --</option>
                <option v-for="po in pendingPosList" :key="po.id" :value="po.id">
                  {{ po.po_number }} — Supplier: {{ po.supplier_name }} (Date: {{ po.order_date }})
                </option>
              </select>

              <div v-if="activePo" class="p-3 bg-white rounded-xl border border-slate-200 text-xs space-y-1">
                <div v-if="poWarningMessage" class="p-2.5 bg-amber-50 border border-amber-200 text-amber-900 font-bold rounded-lg text-xs mb-2">
                  ⚠️ {{ poWarningMessage }}
                </div>
                <div class="font-black text-slate-900">Supplier: {{ activePo.supplier_name }}</div>
                <div class="text-[11px] text-slate-500">PO Number: {{ activePo.po_number }} | Date: {{ activePo.order_date }}</div>
              </div>
            </div>

            <!-- GRN NOTES & INVOICE REF -->
            <div v-if="activePo" class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
              <div>
                <label class="block font-bold text-slate-700 mb-1">Vendor Invoice / Bill Number</label>
                <input type="text" v-model="grnForm.supplier_invoice_number" placeholder="Vendor Invoice #" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-900" />
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">Receiving Notes</label>
                <input type="text" v-model="grnForm.notes" placeholder="Batch / Carton notes" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-900" />
              </div>
            </div>

            <!-- ITEMS RECEIVING TABLE -->
            <div v-if="activePo" class="space-y-3">
              <h3 class="font-black text-xs text-slate-900">Footwear Line Items to Receive</h3>

              <div class="overflow-x-auto border border-slate-200 rounded-2xl overflow-hidden shadow-xs">
                <table class="w-full text-left text-xs min-w-[550px]">
                  <thead>
                    <tr class="bg-slate-900 text-white font-extrabold uppercase text-[10px]">
                      <th class="py-2.5 px-3">Article & SKU</th>
                      <th class="py-2.5 px-3">Color & Size</th>
                      <th class="py-2.5 px-3 text-center">Ordered</th>
                      <th class="py-2.5 px-3 text-center">Prev Rec</th>
                      <th class="py-2.5 px-3 text-center">Remaining</th>
                      <th class="py-2.5 px-3 text-center">Receive Now</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100 font-medium">
                    <tr v-for="item in grnForm.items" :key="item.purchase_order_item_id" class="hover:bg-slate-50">
                      <td class="py-2.5 px-3">
                        <div class="font-black text-red-600">{{ item.article_number }}</div>
                        <div class="text-[10px] text-slate-500">{{ item.product_name }}</div>
                      </td>
                      <td class="py-2.5 px-3">
                        <div class="font-bold text-slate-800">{{ item.color_name }}</div>
                        <div class="text-[10px] font-black text-slate-900">{{ item.size_display }}</div>
                      </td>
                      <td class="py-2.5 px-3 text-center font-bold text-slate-700">{{ item.quantity_ordered }}</td>
                      <td class="py-2.5 px-3 text-center text-slate-500">{{ item.quantity_received_so_far }}</td>
                      <td class="py-2.5 px-3 text-center font-black text-amber-700">{{ item.remaining_qty }}</td>
                      <td class="py-2.5 px-3 text-center">
                        <input
                          type="number"
                          v-model.number="item.quantity_received_now"
                          min="0"
                          :max="item.remaining_qty"
                          class="w-20 bg-emerald-50 border-2 border-emerald-300 rounded-xl px-2 py-1 text-center font-mono font-black text-slate-900"
                        />
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- ACTIONS FOOTER -->
          <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-4 sm:px-6 py-3 sm:py-4 shrink-0 bg-slate-50 sm:rounded-b-3xl z-10">
            <button @click="showReceiveModal = false" type="button" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 rounded-xl font-bold text-xs">Cancel</button>
            <button
              v-if="activePo"
              @click="submitGoodsReceive"
              :disabled="saving"
              type="button"
              class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-black text-xs shadow-md shadow-emerald-600/20"
            >
              {{ saving ? 'Updating Stock...' : 'Confirm Goods Receive & Update Stock' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '../../services/api';

const route = useRoute();

const loading = ref(false);
const saving = ref(false);
const showReceiveModal = ref(false);

watch(showReceiveModal, (isOpen) => {
  if (isOpen) {
    document.body.style.overflow = 'hidden';
  } else {
    document.body.style.overflow = '';
  }
});

const grnList = ref([]);
const pendingPosList = ref([]);
const selectedPoId = ref('');
const activePo = ref(null);
const poWarningMessage = ref('');

const filters = reactive({
  search: '',
});

const pagination = reactive({
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1,
});

const grnForm = reactive({
  supplier_invoice_number: '',
  notes: '',
  items: [],
});

let debounceTimer = null;
function debouncedFetch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => fetchGrns(1), 300);
}

async function fetchGrns(page = 1) {
  loading.value = true;
  try {
    const res = await api.get('/purchases/grn', {
      params: { page, per_page: pagination.per_page, search: filters.search || undefined },
    });
    const payload = res.data?.data || res.data;
    grnList.value = payload.items || [];
    if (payload.pagination) Object.assign(pagination, payload.pagination);
  } catch (err) {
    console.error('Failed to load GRNs:', err);
  } finally {
    loading.value = false;
  }
}

async function openReceiveModal() {
  selectedPoId.value = '';
  activePo.value = null;
  poWarningMessage.value = '';
  grnForm.supplier_invoice_number = '';
  grnForm.notes = '';
  grnForm.items = [];

  try {
    const res = await api.get('/purchases/orders', { params: { per_page: 100 } });
    const items = res.data?.data?.items || res.data?.items || [];
    pendingPosList.value = items.filter(po => po.status !== 'fully_received' && po.status !== 'received' && po.status !== 'cancelled');
    showReceiveModal.value = true;
  } catch (e) {
    alert('Failed to load pending purchase orders.');
  }
}

async function openReceiveModalForPo(poId) {
  poWarningMessage.value = '';
  try {
    const res = await api.get('/purchases/orders', { params: { per_page: 100 } });
    const items = res.data?.data?.items || res.data?.items || [];
    pendingPosList.value = items;
    selectedPoId.value = Number(poId);
    showReceiveModal.value = true;
    await onPoSelect();
  } catch (e) {
    console.error('Failed to auto-load PO for GRN:', e);
  }
}

async function onPoSelect() {
  poWarningMessage.value = '';
  if (!selectedPoId.value) {
    activePo.value = null;
    grnForm.items = [];
    return;
  }

  try {
    const res = await api.get(`/purchases/orders/${selectedPoId.value}`);
    const po = res.data?.data || res.data;
    activePo.value = po;
    grnForm.supplier_invoice_number = po.supplier_invoice_number || '';

    const st = po.status?.toLowerCase() || '';
    if (st === 'fully_received' || st === 'received' || st === 'cancelled') {
      poWarningMessage.value = `Purchase Order ${po.po_number} is ${st.replace('_', ' ')}. No further stock can be received against it.`;
    }

    grnForm.items = (po.items || []).map(pi => {
      const remaining = Math.max(0, pi.quantity_ordered - (pi.quantity_received || 0));
      return {
        purchase_order_item_id: pi.id,
        article_number: pi.article_number || pi.variantSize?.variant?.product?.article_number || 'N/A',
        product_name: pi.product_name || pi.variantSize?.variant?.product?.name || 'Footwear Item',
        color_name: pi.color_name || pi.variantSize?.variant?.color?.name || 'Std',
        size_display: pi.size_display || pi.variantSize?.size?.size_number || 'N/A',
        quantity_ordered: pi.quantity_ordered,
        quantity_received_so_far: pi.quantity_received || 0,
        remaining_qty: remaining,
        quantity_received_now: remaining,
      };
    });
  } catch (e) {
    alert('Failed to load PO details.');
  }
}

async function submitGoodsReceive() {
  if (poWarningMessage.value) {
    alert(poWarningMessage.value);
    return;
  }

  if (!grnForm.items.some(i => i.quantity_received_now > 0)) {
    alert('Please specify at least 1 unit to receive.');
    return;
  }

  saving.value = true;
  try {
    await api.post('/purchases/grn', {
      purchase_order_id: selectedPoId.value,
      supplier_invoice_number: grnForm.supplier_invoice_number,
      notes: grnForm.notes,
      items: grnForm.items.map(i => ({
        purchase_order_item_id: i.purchase_order_item_id,
        quantity_received_now: i.quantity_received_now,
      })),
    });

    showReceiveModal.value = false;
    alert('Goods received successfully! Inventory stock increased and GRN recorded.');
    fetchGrns(1);
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to confirm Goods Receive.');
  } finally {
    saving.value = false;
  }
}

function formatCurrency(val) {
  return Number(val || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

onMounted(async () => {
  fetchGrns(1);
  const poIdFromQuery = route.query.po_id;
  if (poIdFromQuery) {
    await openReceiveModalForPo(poIdFromQuery);
  }
});
</script>
