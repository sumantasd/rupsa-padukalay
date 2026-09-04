<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-16 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Stock Movement Audit Ledger</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Immutable chronological audit log of all stock ins, outs, sales, purchases, returns & adjustments for STR-001
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          @click="fetchMovements(1)"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs flex items-center gap-1.5 transition-all"
        >
          <span>🔄 Refresh Ledger</span>
        </button>
      </div>
    </div>

    <!-- FILTER BAR -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs space-y-3">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 text-xs">
        <!-- Search Input -->
        <div class="lg:col-span-2 relative">
          <input
            v-model="filters.search"
            @input="debouncedFetch"
            type="text"
            placeholder="Search Movement #, SKU, Article #, Notes..."
            class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-red-500/20"
          />
          <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
        </div>

        <!-- Movement Type Filter -->
        <select v-model="filters.movement_type" @change="fetchMovements(1)" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-800 focus:outline-none">
          <option value="">All Movement Types</option>
          <option value="purchase_receive">PURCHASE</option>
          <option value="sale_pos">POS SALE</option>
          <option value="sale_return">SALES RETURN</option>
          <option value="exchange">EXCHANGE</option>
          <option value="adjustment_add">ADJUSTMENT (ADD)</option>
          <option value="adjustment_deduct">ADJUSTMENT (REDUCE)</option>
          <option value="opening_stock">OPENING STOCK</option>
          <option value="damage">DAMAGE / LOSS</option>
          <option value="transfer_in">TRANSFER IN</option>
          <option value="transfer_out">TRANSFER OUT</option>
        </select>

        <!-- Date From -->
        <div>
          <input
            type="date"
            v-model="filters.date_from"
            @change="fetchMovements(1)"
            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-800 focus:outline-none"
          />
        </div>

        <!-- Date To -->
        <div>
          <input
            type="date"
            v-model="filters.date_to"
            @change="fetchMovements(1)"
            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-800 focus:outline-none"
          />
        </div>
      </div>
    </div>

    <!-- MOVEMENT LEDGER TABLE -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="bg-slate-900 text-white font-extrabold uppercase tracking-wider text-[11px]">
              <th class="py-3.5 px-4">Date & Time</th>
              <th class="py-3.5 px-4 font-mono">Movement #</th>
              <th class="py-3.5 px-4">Article # & Item</th>
              <th class="py-3.5 px-4">Color & IND Size</th>
              <th class="py-3.5 px-4 font-mono">SKU</th>
              <th class="py-3.5 px-4 text-center">Movement Type</th>
              <th class="py-3.5 px-4 text-center">Change Qty</th>
              <th class="py-3.5 px-4 text-center">Before → After</th>
              <th class="py-3.5 px-4">Staff / User</th>
              <th class="py-3.5 px-4 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium">
            <tr v-if="loading">
              <td colspan="10" class="text-center py-12 text-slate-400 font-bold">
                <div class="flex flex-col items-center justify-center gap-2">
                  <div class="w-6 h-6 border-2 border-red-600 border-t-transparent rounded-full animate-spin"></div>
                  <span>Loading stock movement audit ledger...</span>
                </div>
              </td>
            </tr>

            <tr v-else-if="movementsList.length === 0">
              <td colspan="10" class="text-center py-12 text-slate-400 font-bold">
                No stock movements match your filters.
              </td>
            </tr>

            <tr v-for="m in movementsList" :key="m.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="py-3 px-4 font-bold text-slate-700 whitespace-nowrap">
                {{ formatDateTime(m.created_at) }}
              </td>
              <td class="py-3 px-4 font-mono font-black text-blue-700 text-xs whitespace-nowrap">
                {{ m.movement_number }}
              </td>
              <td class="py-3 px-4">
                <div class="font-mono font-black text-red-600">{{ m.article_number }}</div>
                <div class="font-bold text-slate-900 text-xs">{{ m.product_name }}</div>
              </td>
              <td class="py-3 px-4">
                <div class="font-bold text-slate-800">{{ m.color_name }}</div>
                <div class="font-black text-slate-900 text-xs bg-slate-100 px-1.5 py-0.5 rounded inline-block mt-0.5">
                  {{ m.size_display }}
                </div>
              </td>
              <td class="py-3 px-4 font-mono font-bold text-slate-600 text-[11px]">
                {{ m.sku }}
              </td>
              <td class="py-3 px-4 text-center">
                <span
                  :class="[
                    'px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider',
                    m.quantity_change > 0 ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-red-100 text-red-800 border border-red-300'
                  ]"
                >
                  {{ m.movement_label || m.movement_type }}
                </span>
              </td>
              <td class="py-3 px-4 text-center font-mono font-black text-sm">
                <span :class="m.quantity_change > 0 ? 'text-emerald-600' : 'text-red-600'">
                  {{ m.quantity_change > 0 ? '+' + m.quantity_change : m.quantity_change }}
                </span>
              </td>
              <td class="py-3 px-4 text-center font-mono text-xs text-slate-700 font-bold whitespace-nowrap">
                {{ m.stock_before }} → <span class="font-black text-slate-900">{{ m.stock_after }}</span>
              </td>
              <td class="py-3 px-4 font-bold text-slate-800">
                {{ m.creator_name || 'Staff' }}
              </td>
              <td class="py-3 px-4 text-right">
                <button
                  @click="openMovementDetail(m)"
                  class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-bold text-[10px] transition-all"
                >
                  👁️ Details
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="p-4 border-t border-slate-200 bg-slate-50 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs font-bold text-slate-600">
        <div>
          Showing page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} total movements)
        </div>
        <div class="flex items-center gap-2">
          <button
            @click="fetchMovements(pagination.current_page - 1)"
            :disabled="pagination.current_page <= 1"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-xl font-bold disabled:opacity-40"
          >
            ← Previous
          </button>
          <button
            @click="fetchMovements(pagination.current_page + 1)"
            :disabled="pagination.current_page >= pagination.last_page"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-xl font-bold disabled:opacity-40"
          >
            Next →
          </button>
        </div>
      </div>
    </div>

    <!-- MOVEMENT DETAIL MODAL -->
    <div v-if="selectedMovement" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-lg w-full p-6 space-y-5 text-xs">
        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
          <div>
            <h3 class="font-black text-sm text-slate-900">Stock Movement Detail: {{ selectedMovement.movement_number }}</h3>
            <p class="text-[11px] text-slate-500 font-medium">Recorded on {{ formatDateTime(selectedMovement.created_at) }}</p>
          </div>
          <button @click="selectedMovement = null" class="text-slate-400 hover:text-slate-800 font-black text-base">✕</button>
        </div>

        <div class="grid grid-cols-2 gap-3 bg-slate-50 p-4 rounded-2xl border border-slate-200">
          <div>
            <div class="font-extrabold text-slate-400 uppercase text-[9px]">Movement Type</div>
            <div class="font-black text-slate-900 text-xs uppercase">{{ selectedMovement.movement_label || selectedMovement.movement_type }}</div>
          </div>
          <div>
            <div class="font-extrabold text-slate-400 uppercase text-[9px]">Reference Transaction</div>
            <div class="font-mono font-black text-blue-700 text-xs">{{ selectedMovement.reference_number || 'N/A' }}</div>
          </div>
          <div>
            <div class="font-extrabold text-slate-400 uppercase text-[9px]">Article & SKU</div>
            <div class="font-mono font-bold text-slate-800">{{ selectedMovement.article_number }} | {{ selectedMovement.sku }}</div>
          </div>
          <div>
            <div class="font-extrabold text-slate-400 uppercase text-[9px]">Color & Size</div>
            <div class="font-bold text-slate-800">{{ selectedMovement.color_name }} | {{ selectedMovement.size_display }}</div>
          </div>
        </div>

        <div class="space-y-2 bg-slate-50 p-4 rounded-2xl border border-slate-200">
          <div class="flex justify-between font-bold text-slate-700">
            <span>STOCK BEFORE:</span>
            <span>{{ selectedMovement.stock_before }} pairs</span>
          </div>
          <div class="flex justify-between font-black text-sm text-emerald-700">
            <span>QUANTITY CHANGE:</span>
            <span>{{ selectedMovement.quantity_change > 0 ? '+' + selectedMovement.quantity_change : selectedMovement.quantity_change }} pairs</span>
          </div>
          <div class="flex justify-between font-black text-sm text-slate-900 pt-2 border-t border-slate-200">
            <span>STOCK AFTER:</span>
            <span>{{ selectedMovement.stock_after }} pairs</span>
          </div>
        </div>

        <div class="space-y-1">
          <span class="font-bold text-slate-500 block uppercase text-[10px]">Reason & Audit Notes</span>
          <p class="p-3 bg-slate-100 rounded-xl font-medium text-slate-800 leading-relaxed">
            {{ selectedMovement.notes || 'Automated stock movement recorded.' }}
          </p>
        </div>

        <div class="flex justify-end pt-2 border-t border-slate-200">
          <button @click="selectedMovement = null" class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '../../services/api';

const loading = ref(false);
const movementsList = ref([]);
const selectedMovement = ref(null);

const filters = reactive({
  search: '',
  movement_type: '',
  date_from: '',
  date_to: '',
});

const pagination = reactive({
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1,
});

let debounceTimer = null;
function debouncedFetch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => fetchMovements(1), 300);
}

async function fetchMovements(page = 1) {
  loading.value = true;
  try {
    const params = {
      page,
      per_page: pagination.per_page,
      search: filters.search || undefined,
      movement_type: filters.movement_type || undefined,
      date_from: filters.date_from || undefined,
      date_to: filters.date_to || undefined,
    };

    const res = await api.get('/inventory/movements', { params });
    const payload = res.data?.data || res.data || res;

    movementsList.value = payload.items || [];
    if (payload.pagination) {
      Object.assign(pagination, payload.pagination);
    }
  } catch (err) {
    console.error('Failed to load stock movements:', err);
  } finally {
    loading.value = false;
  }
}

function openMovementDetail(m) {
  selectedMovement.value = m;
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
  fetchMovements(1);
});
</script>
