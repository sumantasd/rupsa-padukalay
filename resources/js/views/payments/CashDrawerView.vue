<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-16 antialiased font-sans">
    <!-- UI Notification Toast / Banner -->
    <div v-if="notification.show" :class="['p-4 rounded-2xl border font-bold text-xs flex items-center justify-between shadow-md transition-all', notification.type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-red-50 border-red-200 text-red-900']">
      <div class="flex items-center gap-2">
        <span>{{ notification.type === 'success' ? '✅' : '⚠️' }}</span>
        <span>{{ notification.message }}</span>
      </div>
      <button @click="notification.show = false" class="text-slate-400 hover:text-slate-600 text-sm font-black cursor-pointer">✕</button>
    </div>

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">💵 Cash Drawer Management</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Real-time POS cash floats, drops, sales, collections, expenses & cash movements for STR-001
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          @click="openMovementModal('cash_in')"
          class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs shadow-xs transition-all uppercase tracking-wider flex items-center gap-1.5 cursor-pointer"
        >
          <span>➕</span>
          <span>Manual Cash In</span>
        </button>
        <button
          @click="openMovementModal('cash_out')"
          class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-xs transition-all uppercase tracking-wider flex items-center gap-1.5 cursor-pointer"
        >
          <span>➖</span>
          <span>Manual Cash Out / Drop</span>
        </button>
      </div>
    </div>

    <!-- LIVE CASH DRAWER METRICS GRID -->
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-3">
      <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
        <div class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Opening Float</div>
        <div class="text-lg font-black font-mono text-slate-900 mt-1">₹{{ formatCurrency(drawerData.opening_cash) }}</div>
      </div>

      <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
        <div class="text-[10px] font-black text-emerald-600 uppercase tracking-wider">+ Cash Sales</div>
        <div class="text-lg font-black font-mono text-emerald-600 mt-1">₹{{ formatCurrency(drawerData.cash_sales) }}</div>
      </div>

      <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
        <div class="text-[10px] font-black text-emerald-600 uppercase tracking-wider">+ Cash Collections</div>
        <div class="text-lg font-black font-mono text-emerald-600 mt-1">₹{{ formatCurrency(drawerData.cash_collections) }}</div>
      </div>

      <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
        <div class="text-[10px] font-black text-red-600 uppercase tracking-wider">- Cash Refunds & Exp</div>
        <div class="text-lg font-black font-mono text-red-600 mt-1">
          ₹{{ formatCurrency((drawerData.cash_refunds || 0) + (drawerData.cash_expenses || 0)) }}
        </div>
      </div>

      <div class="bg-slate-900 p-4 rounded-2xl border border-slate-900 shadow-md text-white col-span-2 sm:col-span-1">
        <div class="text-[10px] font-black text-amber-400 uppercase tracking-wider">Expected Cash</div>
        <div class="text-xl font-black font-mono text-amber-400 mt-1">₹{{ formatCurrency(drawerData.expected_cash) }}</div>
      </div>
    </div>

    <!-- CASH MOVEMENTS HISTORY TABLE -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
      <div class="p-4 border-b border-slate-100 flex items-center justify-between">
        <h3 class="font-black text-xs text-slate-900">📋 Cash Movement Audit Ledger</h3>
        <button @click="fetchDrawerStatus" class="text-xs text-blue-600 font-bold hover:underline cursor-pointer">🔄 Refresh Status</button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="bg-slate-900 text-white font-extrabold uppercase tracking-wider text-[11px]">
              <th class="py-3.5 px-4">Timestamp</th>
              <th class="py-3.5 px-4 text-center">Movement Type</th>
              <th class="py-3.5 px-4">Reason & Description</th>
              <th class="py-3.5 px-4 text-center font-mono">Ref #</th>
              <th class="py-3.5 px-4 text-right font-mono">Amount</th>
              <th class="py-3.5 px-4 text-center">User</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium">
            <tr v-if="loading">
              <td colspan="6" class="text-center py-12 text-slate-400 font-bold">
                Loading cash movements...
              </td>
            </tr>
            <tr v-else-if="movementsList.length === 0">
              <td colspan="6" class="text-center py-12 text-slate-500 font-bold">
                No manual cash movements recorded for active register session.
              </td>
            </tr>
            <tr v-for="m in movementsList" :key="m.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="py-3 px-4 font-mono text-slate-500 text-[11px]">
                {{ formatDate(m.created_at) }}
              </td>
              <td class="py-3 px-4 text-center">
                <span :class="m.movement_type === 'cash_in' ? 'px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-black' : 'px-2 py-0.5 rounded-full bg-red-100 text-red-800 text-[10px] font-black'">
                  {{ (m.movement_type || 'CASH').toUpperCase() }}
                </span>
              </td>
              <td class="py-3 px-4 font-bold text-slate-800">
                {{ m.reason }}
              </td>
              <td class="py-3 px-4 text-center font-mono font-bold text-slate-600">
                {{ m.reference_number || '—' }}
              </td>
              <td class="py-3 px-4 text-right font-mono font-black text-slate-900 text-sm" :class="m.movement_type === 'cash_in' ? 'text-emerald-600' : 'text-red-600'">
                {{ m.movement_type === 'cash_in' ? '+' : '-' }}₹{{ formatCurrency(m.amount) }}
              </td>
              <td class="py-3 px-4 text-center font-bold text-slate-700">
                {{ m.user?.name || 'Cashier' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- MODAL: MANUAL CASH IN / CASH OUT -->
    <div v-if="showMovementModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto font-sans">
      <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-6 my-8">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
          <h2 class="text-lg font-black text-slate-900">
            {{ movementForm.movement_type === 'cash_in' ? '➕ Record Manual Cash In' : '➖ Record Manual Cash Out / Drop' }}
          </h2>
          <button @click="showMovementModal = false" class="text-slate-400 hover:text-slate-600 font-bold cursor-pointer">✕</button>
        </div>

        <!-- Modal Inline Error -->
        <div v-if="modalError" class="p-3 bg-red-50 border border-red-200 rounded-xl text-red-900 text-xs font-bold flex items-center gap-2">
          <span>⚠️</span>
          <span>{{ modalError }}</span>
        </div>

        <div class="space-y-4 text-xs">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Amount (₹) <span class="text-red-600">*</span></label>
            <input type="number" step="0.01" min="0.01" v-model.number="movementForm.amount" placeholder="0.00" class="w-full bg-slate-50 border rounded-xl px-3.5 py-2 font-mono font-black text-sm text-slate-900 focus:bg-white" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Reason / Description <span class="text-red-600">*</span></label>
            <input type="text" v-model="movementForm.reason" placeholder="e.g. Petty Cash Float, Bank Drop, Tea Expense" class="w-full bg-slate-50 border rounded-xl px-3.5 py-2 font-bold text-slate-900 focus:bg-white" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Reference Number (Optional)</label>
            <input type="text" v-model="movementForm.reference_number" placeholder="e.g. VOUCHER-001" class="w-full bg-slate-50 border rounded-xl px-3.5 py-2 font-mono font-bold text-slate-900 focus:bg-white" />
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
          <button @click="showMovementModal = false" type="button" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl font-bold text-xs cursor-pointer">Cancel</button>
          <button @click="submitMovement" :disabled="saving" type="button" class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-black text-xs shadow-xs uppercase tracking-wider disabled:opacity-50 cursor-pointer">
            {{ saving ? 'Saving...' : 'Confirm Cash Movement' }}
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
const saving = ref(false);
const showMovementModal = ref(false);
const modalError = ref('');

const notification = reactive({ show: false, message: '', type: 'success' });

const drawerData = reactive({
  has_active_session: false,
  opening_cash: 0,
  cash_sales: 0,
  cash_collections: 0,
  cash_supplier_payments: 0,
  cash_refunds: 0,
  cash_expenses: 0,
  manual_cash_in: 0,
  manual_cash_out: 0,
  expected_cash: 0,
});

const movementsList = ref([]);

const movementForm = reactive({
  movement_type: 'cash_in',
  amount: 0,
  reason: '',
  reference_number: '',
});

function showToast(msg, type = 'success') {
  notification.message = msg;
  notification.type = type;
  notification.show = true;
  setTimeout(() => { notification.show = false; }, 4000);
}

async function fetchDrawerStatus() {
  loading.value = true;
  try {
    const [statusRes, movementsRes] = await Promise.all([
      api.get('/payments/cash-drawer/status'),
      api.get('/payments/cash-drawer/movements'),
    ]);

    const sData = statusRes.data?.data || statusRes.data;
    Object.assign(drawerData, sData);

    const mData = movementsRes.data?.data || movementsRes.data;
    movementsList.value = mData.items || [];
  } catch (err) {
    console.error('Failed to fetch cash drawer status:', err);
  } finally {
    loading.value = false;
  }
}

function openMovementModal(type) {
  modalError.value = '';
  movementForm.movement_type = type;
  movementForm.amount = 0;
  movementForm.reason = type === 'cash_in' ? 'Petty cash added to drawer' : 'Bank cash drop / payout';
  movementForm.reference_number = '';
  showMovementModal.value = true;
}

async function submitMovement() {
  if (saving.value) return; // Prevent double submission
  modalError.value = '';

  if (!movementForm.amount || Number(movementForm.amount) <= 0) {
    modalError.value = 'Please enter a valid cash amount greater than zero.';
    return;
  }
  if (!movementForm.reason.trim()) {
    modalError.value = 'Please enter a valid reason for this cash movement.';
    return;
  }

  saving.value = true;
  try {
    await api.post('/payments/cash-drawer/movement', {
      movement_type: movementForm.movement_type,
      amount: Number(movementForm.amount),
      reason: movementForm.reason,
      reference_number: movementForm.reference_number,
      store_id: 1,
    });
    showMovementModal.value = false;
    showToast('Cash drawer movement recorded successfully!', 'success');
    fetchDrawerStatus();
  } catch (err) {
    modalError.value = err.response?.data?.message || 'Failed to record cash movement.';
  } finally {
    saving.value = false;
  }
}

function formatCurrency(val) {
  return Number(val || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(dt) {
  if (!dt) return '—';
  return new Date(dt).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' });
}

onMounted(() => {
  fetchDrawerStatus();
});
</script>
