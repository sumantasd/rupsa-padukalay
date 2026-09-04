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
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">💳 Payment Collections</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Record & settle customer dues and supplier payments for STR-001 (Updates Ledgers & Cash Drawer)
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          @click="openSupplierPaymentModal"
          class="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold text-xs shadow-xs transition-all uppercase tracking-wider flex items-center gap-1.5 cursor-pointer"
        >
          <span>🏢</span>
          <span>Supplier Payment</span>
        </button>
        <button
          @click="openCustomerPaymentModal"
          class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md shadow-red-600/20 transition-all uppercase tracking-wider flex items-center gap-1.5 cursor-pointer"
        >
          <span>💳</span>
          <span>Collect Customer Payment</span>
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
            placeholder="Search Payment #, Customer, Mobile, Invoice..."
            class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-red-500/20"
          />
          <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
        </div>

        <select v-model="filters.payment_method" @change="fetchCollections(1)" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-700 focus:outline-none cursor-pointer">
          <option value="">All Payment Methods</option>
          <option value="cash">Cash</option>
          <option value="upi">UPI</option>
          <option value="card">Card</option>
          <option value="bank_transfer">Bank Transfer</option>
          <option value="cheque">Cheque</option>
        </select>
      </div>
    </div>

    <!-- COLLECTIONS TABLE -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="bg-slate-900 text-white font-extrabold uppercase tracking-wider text-[11px]">
              <th class="py-3.5 px-4">Payment # & Date</th>
              <th class="py-3.5 px-4">Customer</th>
              <th class="py-3.5 px-4 text-center">Payment Method</th>
              <th class="py-3.5 px-4 text-center">Ref / UTR #</th>
              <th class="py-3.5 px-4 text-right font-mono">Amount Collected</th>
              <th class="py-3.5 px-4 text-center">Collected By</th>
              <th class="py-3.5 px-4 text-center">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium">
            <tr v-if="loading">
              <td colspan="7" class="text-center py-12 text-slate-400 font-bold">
                Loading payment collections...
              </td>
            </tr>
            <tr v-else-if="collectionsList.length === 0">
              <td colspan="7" class="text-center py-12 text-slate-500 font-bold">
                No payment collections found. Click "Collect Customer Payment" to record one.
              </td>
            </tr>
            <tr v-for="pay in collectionsList" :key="pay.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="py-3 px-4">
                <div class="font-mono font-black text-red-600 text-xs">{{ pay.payment_number }}</div>
                <div class="text-[10px] text-slate-400 font-medium">{{ formatDate(pay.payment_date || pay.created_at) }}</div>
              </td>
              <td class="py-3 px-4">
                <div class="font-bold text-slate-900">{{ pay.customer?.name || 'N/A' }}</div>
                <div class="text-[10px] text-slate-500 font-mono">Phone: {{ pay.customer?.mobile_number || 'N/A' }}</div>
                <div v-if="pay.invoice" class="text-[10px] text-blue-600 font-bold">Inv: {{ pay.invoice.invoice_number }}</div>
              </td>
              <td class="py-3 px-4 text-center">
                <span :class="getMopBadgeClass(pay.payment_method)">
                  {{ (pay.payment_method || 'CASH').toUpperCase() }}
                </span>
              </td>
              <td class="py-3 px-4 text-center font-mono font-bold text-slate-600 text-[11px]">
                {{ pay.transaction_reference || '—' }}
              </td>
              <td class="py-3 px-4 text-right font-mono font-black text-slate-900 text-sm">
                ₹{{ formatCurrency(pay.amount) }}
              </td>
              <td class="py-3 px-4 text-center font-bold text-slate-700">
                {{ pay.collector?.name || 'System User' }}
              </td>
              <td class="py-3 px-4 text-center">
                <button @click="printReceipt(pay)" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg text-[10px] font-black transition-colors cursor-pointer">
                  🖨️ Receipt
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between text-xs font-bold text-slate-600">
        <div>Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} Payments)</div>
        <div class="flex items-center gap-2">
          <button @click="fetchCollections(pagination.current_page - 1)" :disabled="pagination.current_page <= 1" class="px-3 py-1 bg-white border rounded-lg disabled:opacity-40 cursor-pointer">← Prev</button>
          <button @click="fetchCollections(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page" class="px-3 py-1 bg-white border rounded-lg disabled:opacity-40 cursor-pointer">Next →</button>
        </div>
      </div>
    </div>

    <!-- MODAL 1: CUSTOMER PAYMENT COLLECTION -->
    <div v-if="showCustomerModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto font-sans">
      <div class="bg-white rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 space-y-6 my-8">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
          <h2 class="text-lg font-black text-slate-900">💳 Record Customer Due Collection</h2>
          <button @click="showCustomerModal = false" class="text-slate-400 hover:text-slate-600 font-bold cursor-pointer">✕</button>
        </div>

        <!-- Modal Inline Error -->
        <div v-if="modalError" class="p-3 bg-red-50 border border-red-200 rounded-xl text-red-900 text-xs font-bold flex items-center gap-2">
          <span>⚠️</span>
          <span>{{ modalError }}</span>
        </div>

        <div class="space-y-4 text-xs">
          <!-- 1. Customer Select -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Select Customer <span class="text-red-600">*</span></label>
            <select v-model="customerForm.customer_id" @change="onCustomerSelect" class="w-full bg-slate-50 border rounded-xl px-3.5 py-2.5 font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-red-500/20 cursor-pointer">
              <option value="">-- Choose Customer --</option>
              <option v-for="c in customersList" :key="c.id" :value="c.id">
                {{ c.name }} (Mobile: {{ c.mobile_number }})
              </option>
            </select>
          </div>

          <!-- Customer Outstanding Card -->
          <div v-if="selectedCustomer" class="p-3 bg-red-50 border border-red-200 rounded-xl flex items-center justify-between font-mono text-xs">
            <div>
              <div class="font-bold text-slate-900">{{ selectedCustomer.name }}</div>
              <div class="text-[10px] text-slate-500">Unpaid Invoices: {{ customerInvoices.length }}</div>
            </div>
            <div class="text-right">
              <div class="text-[10px] text-red-600 font-bold uppercase">Total Outstanding Due</div>
              <div class="font-black text-sm text-red-600">₹{{ formatCurrency(customerTotalDue) }}</div>
            </div>
          </div>

          <!-- 2. Invoice Select (Optional) -->
          <div v-if="selectedCustomer">
            <label class="block font-bold text-slate-700 mb-1">Apply to Specific Invoice (Optional)</label>
            <select v-model="customerForm.invoice_id" @change="onInvoiceSelect" class="w-full bg-slate-50 border rounded-xl px-3.5 py-2.5 font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-red-500/20 cursor-pointer">
              <option value="">Auto-Apply across oldest unpaid invoices</option>
              <option v-for="inv in customerInvoices" :key="inv.id" :value="inv.id">
                Inv #{{ inv.invoice_number }} — Due: ₹{{ formatCurrency(inv.grand_total - inv.paid_amount) }} (Total: ₹{{ formatCurrency(inv.grand_total) }})
              </option>
            </select>
          </div>

          <!-- 3. Amount & Payment Method -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Collection Amount (₹) <span class="text-red-600">*</span></label>
              <input type="number" step="0.01" min="0.01" v-model.number="customerForm.amount" placeholder="0.00" class="w-full bg-slate-50 border rounded-xl px-3.5 py-2 font-mono font-black text-sm text-slate-900 focus:bg-white focus:ring-2 focus:ring-red-500/20" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Payment Method <span class="text-red-600">*</span></label>
              <select v-model="customerForm.payment_method" class="w-full bg-slate-50 border rounded-xl px-3.5 py-2 font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-red-500/20 cursor-pointer">
                <option value="cash">💵 Cash (Updates Cash Drawer)</option>
                <option value="upi">📱 UPI / QR Code</option>
                <option value="card">💳 Credit/Debit Card</option>
                <option value="bank_transfer">🏦 Bank Transfer / NEFT</option>
                <option value="cheque">📜 Cheque</option>
              </select>
            </div>
          </div>

          <!-- 4. Ref & Notes -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Transaction Ref / UTR / Cheque #</label>
              <input type="text" v-model="customerForm.transaction_reference" placeholder="e.g. UTR12345678" class="w-full bg-slate-50 border rounded-xl px-3.5 py-2 font-mono font-bold text-slate-900 focus:bg-white" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Notes</label>
              <input type="text" v-model="customerForm.notes" placeholder="Optional notes..." class="w-full bg-slate-50 border rounded-xl px-3.5 py-2 font-bold text-slate-900 focus:bg-white" />
            </div>
          </div>
        </div>

        <!-- ACTIONS -->
        <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
          <button @click="showCustomerModal = false" type="button" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl font-bold text-xs cursor-pointer">Cancel</button>
          <button @click="submitCustomerPayment" :disabled="saving" type="button" class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md shadow-red-600/20 uppercase tracking-wider disabled:opacity-50 cursor-pointer">
            {{ saving ? 'Recording Payment...' : 'Confirm & Collect Payment' }}
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL 2: SUPPLIER PAYMENT SETTLEMENT -->
    <div v-if="showSupplierModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto font-sans">
      <div class="bg-white rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 space-y-6 my-8">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
          <h2 class="text-lg font-black text-slate-900">🏢 Record Supplier Payment</h2>
          <button @click="showSupplierModal = false" class="text-slate-400 hover:text-slate-600 font-bold cursor-pointer">✕</button>
        </div>

        <!-- Modal Inline Error -->
        <div v-if="modalError" class="p-3 bg-red-50 border border-red-200 rounded-xl text-red-900 text-xs font-bold flex items-center gap-2">
          <span>⚠️</span>
          <span>{{ modalError }}</span>
        </div>

        <div class="space-y-4 text-xs">
          <!-- 1. Supplier Select -->
          <div>
            <label class="block font-bold text-slate-700 mb-1">Select Supplier <span class="text-red-600">*</span></label>
            <select v-model="supplierForm.supplier_id" @change="onSupplierSelect" class="w-full bg-slate-50 border rounded-xl px-3.5 py-2.5 font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-red-500/20 cursor-pointer">
              <option value="">-- Choose Supplier --</option>
              <option v-for="s in suppliersList" :key="s.id" :value="s.id">
                {{ s.name }} ({{ s.company_name || 'No Company' }})
              </option>
            </select>
          </div>

          <!-- 2. Amount & Payment Method -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Payment Amount (₹) <span class="text-red-600">*</span></label>
              <input type="number" step="0.01" min="0.01" v-model.number="supplierForm.amount" placeholder="0.00" class="w-full bg-slate-50 border rounded-xl px-3.5 py-2 font-mono font-black text-sm text-slate-900 focus:bg-white" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Payment Method <span class="text-red-600">*</span></label>
              <select v-model="supplierForm.payment_method" class="w-full bg-slate-50 border rounded-xl px-3.5 py-2 font-bold text-slate-900 focus:bg-white cursor-pointer">
                <option value="bank_transfer">🏦 Bank Transfer / NEFT</option>
                <option value="cheque">📜 Cheque</option>
                <option value="cash">💵 Cash (Deducts Cash Drawer)</option>
                <option value="upi">📱 UPI</option>
              </select>
            </div>
          </div>

          <!-- 3. Ref & Notes -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Transaction Ref / Cheque #</label>
              <input type="text" v-model="supplierForm.transaction_reference" placeholder="e.g. CHQ-9901" class="w-full bg-slate-50 border rounded-xl px-3.5 py-2 font-mono font-bold text-slate-900 focus:bg-white" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Notes</label>
              <input type="text" v-model="supplierForm.notes" placeholder="Optional notes..." class="w-full bg-slate-50 border rounded-xl px-3.5 py-2 font-bold text-slate-900 focus:bg-white" />
            </div>
          </div>
        </div>

        <!-- ACTIONS -->
        <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
          <button @click="showSupplierModal = false" type="button" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl font-bold text-xs cursor-pointer">Cancel</button>
          <button @click="submitSupplierPayment" :disabled="saving" type="button" class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-black text-xs shadow-xs uppercase tracking-wider disabled:opacity-50 cursor-pointer">
            {{ saving ? 'Recording...' : 'Confirm Supplier Payment' }}
          </button>
        </div>
      </div>
    </div>

    <!-- PRINT RECEIPT TELEPORT -->
    <Teleport to="body" v-if="selectedPrintReceipt">
      <div id="payment-receipt-print-root" class="hidden print:block p-8 font-sans text-black max-w-2xl mx-auto bg-white">
        <div class="text-center border-b-2 border-black pb-4 mb-4">
          <h1 class="text-xl font-black uppercase">RUPSA PADUKALAYA</h1>
          <p class="text-xs font-bold">Main Outlet — STR-001 | Phone: 9876543210</p>
          <p class="text-xs font-bold uppercase mt-1 text-slate-700">OFFICIAL PAYMENT RECEIPT</p>
        </div>

        <div class="grid grid-cols-2 gap-4 text-xs font-bold border-b border-black pb-4 mb-4 font-mono">
          <div>
            <div>Receipt #: {{ selectedPrintReceipt.payment_number }}</div>
            <div>Date: {{ formatDate(selectedPrintReceipt.payment_date || selectedPrintReceipt.created_at) }}</div>
          </div>
          <div class="text-right">
            <div>Customer: {{ selectedPrintReceipt.customer?.name || 'N/A' }}</div>
            <div>Mobile: {{ selectedPrintReceipt.customer?.mobile_number || 'N/A' }}</div>
          </div>
        </div>

        <div class="border border-black p-4 mb-6 text-center">
          <div class="text-xs font-bold uppercase">Amount Received</div>
          <div class="text-2xl font-black font-mono">₹{{ formatCurrency(selectedPrintReceipt.amount) }}</div>
          <div class="text-xs font-bold mt-1">Payment Method: {{ (selectedPrintReceipt.payment_method || 'CASH').toUpperCase() }}</div>
          <div v-if="selectedPrintReceipt.transaction_reference" class="text-xs font-mono mt-0.5">Ref #: {{ selectedPrintReceipt.transaction_reference }}</div>
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

const collectionsList = ref([]);
const customersList = ref([]);
const suppliersList = ref([]);
const customerInvoices = ref([]);

const showCustomerModal = ref(false);
const showSupplierModal = ref(false);
const selectedCustomer = ref(null);
const selectedSupplier = ref(null);
const selectedPrintReceipt = ref(null);

const filters = reactive({ search: '', payment_method: '' });
const pagination = reactive({ current_page: 1, per_page: 15, total: 0, last_page: 1 });

const customerForm = reactive({
  customer_id: '',
  invoice_id: '',
  amount: 0,
  payment_method: 'cash',
  transaction_reference: '',
  notes: '',
});

const supplierForm = reactive({
  supplier_id: '',
  amount: 0,
  payment_method: 'bank_transfer',
  transaction_reference: '',
  notes: '',
});

const customerTotalDue = computed(() => {
  return customerInvoices.value.reduce((sum, inv) => sum + Math.max(0, Number(inv.grand_total || 0) - Number(inv.paid_amount || 0)), 0);
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
  debounceTimer = setTimeout(() => fetchCollections(1), 300);
}

async function fetchCollections(page = 1) {
  loading.value = true;
  try {
    const res = await api.get('/payments/collections', {
      params: {
        page,
        per_page: pagination.per_page,
        search: filters.search || undefined,
        payment_method: filters.payment_method || undefined,
      },
    });
    const payload = res.data?.data || res.data;
    collectionsList.value = payload.items || [];
    if (payload.pagination) Object.assign(pagination, payload.pagination);
  } catch (err) {
    console.error('Failed to fetch payment collections:', err);
  } finally {
    loading.value = false;
  }
}

async function loadMasterData() {
  try {
    const [cRes, sRes] = await Promise.all([
      api.get('/customers', { params: { per_page: 100 } }),
      api.get('/suppliers', { params: { per_page: 100 } }),
    ]);
    customersList.value = cRes.data?.data?.items || cRes.data?.data || cRes.data?.items || [];
    suppliersList.value = sRes.data?.data?.items || sRes.data?.data || sRes.data?.items || [];
  } catch (e) {
    console.error('Failed to load master dropdown data:', e);
  }
}

function openCustomerPaymentModal() {
  modalError.value = '';
  customerForm.customer_id = '';
  customerForm.invoice_id = '';
  customerForm.amount = 0;
  customerForm.payment_method = 'cash';
  customerForm.transaction_reference = '';
  customerForm.notes = '';
  selectedCustomer.value = null;
  customerInvoices.value = [];
  showCustomerModal.value = true;
}

function openSupplierPaymentModal() {
  modalError.value = '';
  supplierForm.supplier_id = '';
  supplierForm.amount = 0;
  supplierForm.payment_method = 'bank_transfer';
  supplierForm.transaction_reference = '';
  supplierForm.notes = '';
  selectedSupplier.value = null;
  showSupplierModal.value = true;
}

async function onCustomerSelect() {
  modalError.value = '';
  selectedCustomer.value = customersList.value.find(c => c.id === customerForm.customer_id) || null;
  customerInvoices.value = [];
  customerForm.invoice_id = '';

  if (customerForm.customer_id) {
    try {
      const res = await api.get('/pos/sales', { params: { customer_id: customerForm.customer_id, per_page: 50 } });
      const items = res.data?.data?.items || res.data?.items || res.data?.data || [];
      customerInvoices.value = items.filter(inv => inv.payment_status === 'unpaid' || inv.payment_status === 'partial');
    } catch (e) {
      console.error('Failed to load customer unpaid invoices:', e);
    }
  }
}

function onInvoiceSelect() {
  modalError.value = '';
  if (customerForm.invoice_id) {
    const inv = customerInvoices.value.find(i => i.id === customerForm.invoice_id);
    if (inv) {
      customerForm.amount = Math.max(0, Number(inv.grand_total || 0) - Number(inv.paid_amount || 0));
    }
  } else {
    customerForm.amount = customerTotalDue.value;
  }
}

function onSupplierSelect() {
  modalError.value = '';
  selectedSupplier.value = suppliersList.value.find(s => s.id === supplierForm.supplier_id) || null;
}

async function submitCustomerPayment() {
  if (saving.value) return; // Prevent double submission
  modalError.value = '';

  if (!customerForm.customer_id) {
    modalError.value = 'Please select a customer.';
    return;
  }
  if (!customerForm.amount || Number(customerForm.amount) <= 0) {
    modalError.value = 'Please enter a valid payment amount greater than zero.';
    return;
  }

  saving.value = true;
  try {
    await api.post('/payments/collections', {
      customer_id: customerForm.customer_id,
      invoice_id: customerForm.invoice_id || undefined,
      amount: Number(customerForm.amount),
      payment_method: customerForm.payment_method,
      transaction_reference: customerForm.transaction_reference,
      notes: customerForm.notes,
      store_id: 1,
    });
    showCustomerModal.value = false;
    showToast('Customer payment collection recorded successfully!', 'success');
    fetchCollections(1);
  } catch (err) {
    modalError.value = err.response?.data?.message || 'Failed to record customer payment.';
  } finally {
    saving.value = false;
  }
}

async function submitSupplierPayment() {
  if (saving.value) return; // Prevent double submission
  modalError.value = '';

  if (!supplierForm.supplier_id) {
    modalError.value = 'Please select a supplier.';
    return;
  }
  if (!supplierForm.amount || Number(supplierForm.amount) <= 0) {
    modalError.value = 'Please enter a valid payment amount greater than zero.';
    return;
  }

  saving.value = true;
  try {
    await api.post(`/suppliers/${supplierForm.supplier_id}/payments`, {
      amount: Number(supplierForm.amount),
      payment_method: supplierForm.payment_method,
      transaction_reference: supplierForm.transaction_reference,
      notes: supplierForm.notes,
    });
    showSupplierModal.value = false;
    showToast('Supplier payment recorded successfully!', 'success');
    fetchCollections(1);
  } catch (err) {
    modalError.value = err.response?.data?.message || 'Failed to record supplier payment.';
  } finally {
    saving.value = false;
  }
}

function printReceipt(pay) {
  selectedPrintReceipt.value = pay;
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

function getMopBadgeClass(mop) {
  const map = {
    cash: 'px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-black',
    upi: 'px-2 py-0.5 rounded-full bg-purple-100 text-purple-800 text-[10px] font-black',
    card: 'px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 text-[10px] font-black',
    bank_transfer: 'px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800 text-[10px] font-black',
    cheque: 'px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-black',
  };
  return map[mop] || 'px-2 py-0.5 rounded-full bg-slate-100 text-slate-800 text-[10px] font-black';
}

onMounted(() => {
  fetchCollections(1);
  loadMasterData();
});
</script>
