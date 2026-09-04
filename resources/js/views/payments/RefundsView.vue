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
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">💸 Customer & Transaction Refunds</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Process transaction-linked refunds for STR-001 (Deducts Cash Drawer or issues Store Credit)
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          @click="openRefundModal"
          class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md shadow-red-600/20 transition-all uppercase tracking-wider flex items-center gap-1.5 cursor-pointer"
        >
          <span>💸</span>
          <span>Process Refund</span>
        </button>
      </div>
    </div>

    <!-- FILTER BAR -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex flex-wrap items-center justify-between gap-3 text-xs">
      <div class="flex flex-wrap items-center gap-3 flex-1">
        <div class="relative max-w-xs w-64">
          <input
            v-model="filters.search"
            @input="debouncedFetch"
            type="text"
            placeholder="Search Refund #, Customer, Invoice, Reason..."
            class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-red-500/20"
          />
          <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
        </div>

        <select v-model="filters.refund_method" @change="fetchRefunds(1)" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-700 focus:outline-none cursor-pointer">
          <option value="">All Refund Methods</option>
          <option value="cash">Cash</option>
          <option value="store_credit">Store Credit</option>
          <option value="upi">UPI</option>
          <option value="card">Card</option>
          <option value="bank_transfer">Bank Transfer</option>
        </select>
      </div>
    </div>

    <!-- REFUNDS TABLE -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="bg-slate-900 text-white font-extrabold uppercase tracking-wider text-[11px]">
              <th class="py-3.5 px-4">Refund # & Date</th>
              <th class="py-3.5 px-4">Customer & Invoice</th>
              <th class="py-3.5 px-4">Refund Reason</th>
              <th class="py-3.5 px-4 text-center">Refund Method</th>
              <th class="py-3.5 px-4 text-right font-mono">Amount Refunded</th>
              <th class="py-3.5 px-4 text-center">Processed By</th>
              <th class="py-3.5 px-4 text-center">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium">
            <tr v-if="loading">
              <td colspan="7" class="text-center py-12 text-slate-400 font-bold">
                Loading refunds...
              </td>
            </tr>
            <tr v-else-if="refundsList.length === 0">
              <td colspan="7" class="text-center py-12 text-slate-500 font-bold">
                No refunds recorded. Click "Process Refund" to issue one.
              </td>
            </tr>
            <tr v-for="ref in refundsList" :key="ref.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="py-3 px-4">
                <div class="font-mono font-black text-red-600 text-xs">{{ ref.refund_number }}</div>
                <div class="text-[10px] text-slate-400 font-medium">{{ formatDate(ref.created_at) }}</div>
              </td>
              <td class="py-3 px-4">
                <div class="font-bold text-slate-900">{{ ref.customer?.name || 'Walk-in Customer' }}</div>
                <div v-if="ref.invoice" class="text-[10px] text-blue-600 font-bold">Inv #{{ ref.invoice.invoice_number }}</div>
              </td>
              <td class="py-3 px-4 font-bold text-slate-700">
                {{ ref.reason }}
              </td>
              <td class="py-3 px-4 text-center">
                <span :class="getRefundBadgeClass(ref.refund_method)">
                  {{ (ref.refund_method || 'CASH').toUpperCase() }}
                </span>
              </td>
              <td class="py-3 px-4 text-right font-mono font-black text-red-600 text-sm">
                ₹{{ formatCurrency(ref.amount) }}
              </td>
              <td class="py-3 px-4 text-center font-bold text-slate-700">
                {{ ref.processor?.name || 'System' }}
              </td>
              <td class="py-3 px-4 text-center">
                <button @click="printReceipt(ref)" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg text-[10px] font-black transition-colors cursor-pointer">
                  🖨️ Voucher
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between text-xs font-bold text-slate-600">
        <div>Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} Refunds)</div>
        <div class="flex items-center gap-2">
          <button @click="fetchRefunds(pagination.current_page - 1)" :disabled="pagination.current_page <= 1" class="px-3 py-1 bg-white border rounded-lg disabled:opacity-40 cursor-pointer">← Prev</button>
          <button @click="fetchRefunds(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page" class="px-3 py-1 bg-white border rounded-lg disabled:opacity-40 cursor-pointer">Next →</button>
        </div>
      </div>
    </div>

    <!-- MODAL: PROCESS TRANSACTION REFUND -->
    <div v-if="showRefundModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto font-sans">
      <div class="bg-white rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 space-y-6 my-8">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
          <h2 class="text-lg font-black text-slate-900">💸 Process Transaction Refund</h2>
          <button @click="showRefundModal = false" class="text-slate-400 hover:text-slate-600 font-bold cursor-pointer">✕</button>
        </div>

        <!-- Modal Inline Error -->
        <div v-if="modalError" class="p-3 bg-red-50 border border-red-200 rounded-xl text-red-900 text-xs font-bold flex items-center gap-2">
          <span>⚠️</span>
          <span>{{ modalError }}</span>
        </div>

        <div class="space-y-4 text-xs">
          <!-- 1. Invoice Select -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Select Sales Invoice to Refund <span class="text-red-600">*</span></label>
            <select v-model="refundForm.invoice_id" @change="onInvoiceSelect" class="w-full bg-slate-50 border rounded-xl px-3.5 py-2.5 font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-red-500/20 cursor-pointer">
              <option value="">-- Choose Invoice --</option>
              <option v-for="inv in eligibleInvoices" :key="inv.id" :value="inv.id">
                Inv #{{ inv.invoice_number }} — Paid: ₹{{ formatCurrency(inv.paid_amount) }} ({{ inv.customer?.name || 'Walk-in' }})
              </option>
            </select>
          </div>

          <!-- Refundable Cap Banner -->
          <div v-if="selectedInvoice" class="p-3 bg-red-50 border border-red-200 rounded-xl space-y-1 font-mono text-xs">
            <div class="flex justify-between">
              <span class="text-slate-600">Original Invoice Total:</span>
              <span class="font-bold">₹{{ formatCurrency(selectedInvoice.grand_total) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-600">Total Paid Amount:</span>
              <span class="font-bold text-emerald-600">₹{{ formatCurrency(selectedInvoice.paid_amount) }}</span>
            </div>
            <div class="flex justify-between border-t border-red-200 pt-1 font-black text-red-600">
              <span>Maximum Refundable:</span>
              <span>₹{{ formatCurrency(maxRefundableAmount) }}</span>
            </div>
          </div>

          <!-- 2. Refund Amount & Refund Method -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Refund Amount (₹) <span class="text-red-600">*</span></label>
              <input type="number" step="0.01" min="0.01" :max="maxRefundableAmount" v-model.number="refundForm.amount" placeholder="0.00" class="w-full bg-slate-50 border rounded-xl px-3.5 py-2 font-mono font-black text-sm text-slate-900 focus:bg-white" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Refund Method <span class="text-red-600">*</span></label>
              <select v-model="refundForm.refund_method" class="w-full bg-slate-50 border rounded-xl px-3.5 py-2 font-bold text-slate-900 focus:bg-white cursor-pointer">
                <option value="cash">💵 Cash (Deducts Cash Drawer)</option>
                <option value="store_credit">💳 Issue Store Credit to Customer</option>
                <option value="upi">📱 UPI Refund</option>
                <option value="card">💳 Card Refund</option>
                <option value="bank_transfer">🏦 Bank Transfer</option>
              </select>
            </div>
          </div>

          <!-- 3. Reason & Ref -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Refund Reason <span class="text-red-600">*</span></label>
              <select v-model="refundForm.reason" class="w-full bg-slate-50 border rounded-xl px-3.5 py-2 font-bold text-slate-900 focus:bg-white cursor-pointer">
                <option value="Damaged Goods">Damaged Goods</option>
                <option value="Customer Return">Customer Return</option>
                <option value="Billing Correction">Billing Correction</option>
                <option value="Overcharge">Overcharge</option>
                <option value="Other">Other</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Transaction Ref / UTR #</label>
              <input type="text" v-model="refundForm.transaction_reference" placeholder="e.g. UTR-REF-99" class="w-full bg-slate-50 border rounded-xl px-3.5 py-2 font-mono font-bold text-slate-900 focus:bg-white" />
            </div>
          </div>
        </div>

        <!-- ACTIONS -->
        <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
          <button @click="showRefundModal = false" type="button" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl font-bold text-xs cursor-pointer">Cancel</button>
          <button @click="submitRefund" :disabled="saving" type="button" class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md shadow-red-600/20 uppercase tracking-wider disabled:opacity-50 cursor-pointer">
            {{ saving ? 'Processing...' : 'Confirm & Process Refund' }}
          </button>
        </div>
      </div>
    </div>

    <!-- PRINT VOUCHER TELEPORT -->
    <Teleport to="body" v-if="selectedPrintVoucher">
      <div id="refund-voucher-print-root" class="hidden print:block p-8 font-sans text-black max-w-2xl mx-auto bg-white">
        <div class="text-center border-b-2 border-black pb-4 mb-4">
          <h1 class="text-xl font-black uppercase">RUPSA PADUKALAYA</h1>
          <p class="text-xs font-bold">Main Outlet — STR-001 | Phone: 9876543210</p>
          <p class="text-xs font-bold uppercase mt-1 text-slate-700">OFFICIAL REFUND VOUCHER</p>
        </div>

        <div class="grid grid-cols-2 gap-4 text-xs font-bold border-b border-black pb-4 mb-4 font-mono">
          <div>
            <div>Voucher #: {{ selectedPrintVoucher.refund_number }}</div>
            <div>Date: {{ formatDate(selectedPrintVoucher.created_at) }}</div>
          </div>
          <div class="text-right">
            <div>Customer: {{ selectedPrintVoucher.customer?.name || 'Walk-in Customer' }}</div>
            <div v-if="selectedPrintVoucher.invoice">Invoice #: {{ selectedPrintVoucher.invoice.invoice_number }}</div>
          </div>
        </div>

        <div class="border border-black p-4 mb-6 text-center">
          <div class="text-xs font-bold uppercase">Amount Refunded</div>
          <div class="text-2xl font-black font-mono text-red-600">₹{{ formatCurrency(selectedPrintVoucher.amount) }}</div>
          <div class="text-xs font-bold mt-1">Refund Method: {{ (selectedPrintVoucher.refund_method || 'CASH').toUpperCase() }}</div>
          <div class="text-xs font-bold mt-0.5">Reason: {{ selectedPrintVoucher.reason }}</div>
        </div>

        <div class="flex justify-between items-end pt-12 text-xs font-bold">
          <div>Customer Signature</div>
          <div>Authorized Signature / RUPSA PADUKALAYA</div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import api from '../../services/api';

const loading = ref(false);
const saving = ref(false);
const modalError = ref('');

const notification = reactive({ show: false, message: '', type: 'success' });

const refundsList = ref([]);
const eligibleInvoices = ref([]);
const showRefundModal = ref(false);
const selectedInvoice = ref(null);
const selectedPrintVoucher = ref(null);

const filters = reactive({ search: '', refund_method: '' });
const pagination = reactive({ current_page: 1, per_page: 15, total: 0, last_page: 1 });

const refundForm = reactive({
  invoice_id: '',
  amount: 0,
  refund_method: 'cash',
  reason: 'Customer Return',
  transaction_reference: '',
  notes: '',
});

const maxRefundableAmount = computed(() => {
  if (!selectedInvoice.value) return 0;
  return Math.max(0, Number(selectedInvoice.value.paid_amount || 0));
});

function showToast(msg, type = 'success') {
  notification.message = msg;
  notification.type = type;
  notification.show = true;
  setTimeout(() => { notification.show = false; }, 4000);
}

let debounceTimer = null;
function debouncedFetch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => fetchRefunds(1), 300);
}

async function fetchRefunds(page = 1) {
  loading.value = true;
  try {
    const res = await api.get('/payments/refunds', {
      params: {
        page,
        per_page: pagination.per_page,
        search: filters.search || undefined,
        refund_method: filters.refund_method || undefined,
      },
    });
    const payload = res.data?.data || res.data;
    refundsList.value = payload.items || [];
    if (payload.pagination) Object.assign(pagination, payload.pagination);
  } catch (err) {
    console.error('Failed to fetch refunds:', err);
  } finally {
    loading.value = false;
  }
}

async function loadEligibleInvoices() {
  try {
    const res = await api.get('/pos/sales', { params: { per_page: 100 } });
    const items = res.data?.data?.items || res.data?.items || res.data?.data || [];
    eligibleInvoices.value = items.filter(inv => Number(inv.paid_amount || 0) > 0 && inv.status !== 'cancelled');
  } catch (e) {
    console.error('Failed to load eligible invoices:', e);
  }
}

function openRefundModal() {
  modalError.value = '';
  refundForm.invoice_id = '';
  refundForm.amount = 0;
  refundForm.refund_method = 'cash';
  refundForm.reason = 'Customer Return';
  refundForm.transaction_reference = '';
  refundForm.notes = '';
  selectedInvoice.value = null;
  loadEligibleInvoices();
  showRefundModal.value = true;
}

function onInvoiceSelect() {
  modalError.value = '';
  selectedInvoice.value = eligibleInvoices.value.find(i => i.id === refundForm.invoice_id) || null;
  if (selectedInvoice.value) {
    refundForm.amount = maxRefundableAmount.value;
  }
}

async function submitRefund() {
  if (saving.value) return; // Prevent double submission
  modalError.value = '';

  if (!refundForm.invoice_id) {
    modalError.value = 'Please select an invoice to refund.';
    return;
  }
  if (!refundForm.amount || Number(refundForm.amount) <= 0) {
    modalError.value = 'Please enter a valid refund amount greater than zero.';
    return;
  }
  if (Number(refundForm.amount) > maxRefundableAmount.value + 0.01) {
    modalError.value = `Refund amount cannot exceed maximum refundable balance ₹${maxRefundableAmount.value}.`;
    return;
  }

  saving.value = true;
  try {
    await api.post('/payments/refunds', {
      invoice_id: refundForm.invoice_id,
      customer_id: selectedInvoice.value?.customer_id || undefined,
      amount: Number(refundForm.amount),
      refund_method: refundForm.refund_method,
      reason: refundForm.reason,
      transaction_reference: refundForm.transaction_reference,
      notes: refundForm.notes,
      store_id: 1,
    });
    showRefundModal.value = false;
    showToast('Refund processed successfully!', 'success');
    fetchRefunds(1);
  } catch (err) {
    modalError.value = err.response?.data?.message || 'Failed to process refund.';
  } finally {
    saving.value = false;
  }
}

function printReceipt(refItem) {
  selectedPrintVoucher.value = refItem;
  setTimeout(() => {
    window.print();
  }, 100);
}

function formatCurrency(val) {
  return Number(val || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(dt) {
  if (!dt) return '—';
  return new Date(dt).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' });
}

function getRefundBadgeClass(mop) {
  const map = {
    cash: 'px-2 py-0.5 rounded-full bg-red-100 text-red-800 text-[10px] font-black',
    store_credit: 'px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-black',
    upi: 'px-2 py-0.5 rounded-full bg-purple-100 text-purple-800 text-[10px] font-black',
    card: 'px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 text-[10px] font-black',
  };
  return map[mop] || 'px-2 py-0.5 rounded-full bg-slate-100 text-slate-800 text-[10px] font-black';
}

onMounted(() => {
  fetchRefunds(1);
});
</script>
