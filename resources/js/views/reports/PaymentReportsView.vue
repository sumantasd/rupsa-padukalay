<template>
  <div class="space-y-4 antialiased font-sans pb-10">
    <!-- Top Report Header Navigation -->
    <ReportNavHeader
      :activeFilterCount="activeFilterCount"
      :showExportBtn="true"
      @open-filter="showFilterSheet = true"
      @refresh="loadAllData"
      @view-pdf="handleViewPdf"
      @download-pdf="handleDownloadPdf"
    />

    <!-- Header Description & Active Date Badge -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-xs">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <h2 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
            <span>💳</span>
            <span>Payment & Collections Analytics</span>
          </h2>
          <span class="px-2.5 py-0.5 text-[10px] font-extrabold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-full border border-slate-200 dark:border-slate-700">
            📅 {{ dateRangeBadge }}
          </span>
        </div>
        <p class="text-xs text-slate-500 font-medium mt-0.5">Payment mode breakdowns (Cash, Card, UPI, Bank), date-wise collections & refunds</p>
      </div>

      <div class="flex items-center gap-2">
        <div class="relative flex-1 sm:w-64">
          <span class="absolute left-3 top-2.5 text-xs text-slate-400">🔍</span>
          <input
            type="text"
            v-model="searchQuery"
            @input="debounceSearch"
            placeholder="Search payment ref, customer..."
            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl pl-9 pr-3 py-2 text-xs font-bold text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-red-500"
          />
        </div>
      </div>
    </div>

    <!-- Error Retry Banner -->
    <div v-if="errorMsg" class="p-4 bg-red-500/10 border border-red-500/30 rounded-2xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-red-500">
      <div class="flex items-center gap-2 text-xs font-bold">
        <span>⚠️</span>
        <span>{{ errorMsg }}</span>
      </div>
      <button
        @click="loadAllData"
        class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white font-extrabold text-xs rounded-xl shadow-md transition-all cursor-pointer whitespace-nowrap self-end sm:self-auto"
      >
        🔄 Retry
      </button>
    </div>

    <!-- 5 Sub-Report Tab Buttons -->
    <div class="flex items-center gap-1.5 p-1.5 bg-slate-950/90 rounded-2xl border border-slate-800 overflow-x-auto text-xs shrink-0 no-scrollbar">
      <button
        v-for="tab in subTabs"
        :key="tab.id"
        @click="activeTab = tab.id; loadAllData();"
        :class="[
          'px-3.5 py-2 rounded-xl font-bold whitespace-nowrap transition-all cursor-pointer min-h-[38px]',
          activeTab === tab.id
            ? 'bg-red-600 text-white shadow-md shadow-red-600/30'
            : 'text-slate-400 hover:text-white hover:bg-slate-900'
        ]"
      >
        <span>{{ tab.icon }}</span>
        <span class="ml-1.5">{{ tab.name }}</span>
      </button>
    </div>

    <!-- 5 Summary KPI Cards Grid (MOP Breakdown) -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
      <ReportKpiCard
        title="Cash Collections"
        :value="summary.cash"
        :isCurrency="true"
        icon="💵"
        accentColor="emerald"
        subtext="Cash in drawer"
      />
      <ReportKpiCard
        title="Card Payments"
        :value="summary.card"
        :isCurrency="true"
        icon="💳"
        accentColor="indigo"
        subtext="POS swipe terminal"
      />
      <ReportKpiCard
        title="UPI / QR Payments"
        :value="summary.upi"
        :isCurrency="true"
        icon="📱"
        accentColor="indigo"
        subtext="GPay / PhonePe / Paytm"
      />
      <ReportKpiCard
        title="Bank / Transfer"
        :value="summary.bank"
        :isCurrency="true"
        icon="🏦"
        accentColor="amber"
        subtext="Direct bank transfer"
      />
      <ReportKpiCard
        title="Total Refunds"
        :value="summary.total_refunds"
        :isCurrency="true"
        icon="↩️"
        accentColor="red"
        subtext="Returned payment refunds"
      />
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="p-12 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
      <div class="inline-block animate-spin text-2xl text-red-600">⌛</div>
      <p class="text-xs font-extrabold text-slate-500">Loading payment report data...</p>
    </div>

    <!-- TAB: DATE-WISE COLLECTIONS -->
    <div v-else-if="activeTab === 'date-wise'" class="space-y-3">
      <div v-if="dateWisePayments.length === 0" class="p-12 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
        <div class="text-3xl text-slate-400">📅</div>
        <h3 class="font-black text-sm text-slate-700 dark:text-slate-300">No Date-Wise Collection Data</h3>
        <p class="text-xs text-slate-400 font-medium">Try adjusting date range or group-by filter.</p>
      </div>

      <div v-else class="space-y-3">
        <!-- Mobile Cards (<768px) -->
        <div class="space-y-3 md:hidden">
          <ReportListCard
            v-for="d in dateWisePayments"
            :key="d.date_period"
            :title="d.date_period"
            subtitle="MOP Breakdown & Total Collections"
            status="Collection"
            statusType="success"
            :metric="'₹' + formatCurrency(d.net_collections)"
            metricLabel="Net Collection"
            :details="[
              { label: 'Cash', value: '₹' + formatCurrency(d.cash_amount) },
              { label: 'Card', value: '₹' + formatCurrency(d.card_amount) },
              { label: 'UPI / QR', value: '₹' + formatCurrency(d.upi_amount) },
              { label: 'Bank', value: '₹' + formatCurrency(d.bank_amount) },
              { label: 'Refunds', value: '₹' + formatCurrency(d.refund_amount) }
            ]"
          />
        </div>

        <!-- Desktop Table (≥768px) -->
        <div class="hidden md:block bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 overflow-x-auto shadow-xs">
          <table class="w-full text-left text-xs font-sans whitespace-nowrap min-w-[750px]">
            <thead class="bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 text-[10px] uppercase font-black tracking-wider text-slate-500 dark:text-slate-400">
              <tr>
                <th class="py-3 px-4">Period / Date</th>
                <th class="py-3 px-4 text-right">Cash</th>
                <th class="py-3 px-4 text-right">Card</th>
                <th class="py-3 px-4 text-right">UPI</th>
                <th class="py-3 px-4 text-right">Bank</th>
                <th class="py-3 px-4 text-right">Other</th>
                <th class="py-3 px-4 text-right">Total Billed</th>
                <th class="py-3 px-4 text-right">Refunds</th>
                <th class="py-3 px-4 text-right">Net Collection</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
              <tr v-for="d in dateWisePayments" :key="d.date_period" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                <td class="py-3 px-4 font-extrabold font-mono text-slate-900 dark:text-white">{{ d.date_period }}</td>
                <td class="py-3 px-4 text-right font-mono text-slate-700 dark:text-slate-300">₹{{ formatCurrency(d.cash_amount) }}</td>
                <td class="py-3 px-4 text-right font-mono text-indigo-600">₹{{ formatCurrency(d.card_amount) }}</td>
                <td class="py-3 px-4 text-right font-mono text-indigo-600">₹{{ formatCurrency(d.upi_amount) }}</td>
                <td class="py-3 px-4 text-right font-mono text-amber-600">₹{{ formatCurrency(d.bank_amount) }}</td>
                <td class="py-3 px-4 text-right font-mono text-slate-500">₹{{ formatCurrency(d.other_amount) }}</td>
                <td class="py-3 px-4 text-right font-mono text-slate-900 dark:text-white font-bold">₹{{ formatCurrency(d.total_collections) }}</td>
                <td class="py-3 px-4 text-right font-mono text-red-600 font-bold">₹{{ formatCurrency(d.refund_amount) }}</td>
                <td class="py-3 px-4 text-right font-mono font-black text-emerald-600">₹{{ formatCurrency(d.net_collections) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TABS: COLLECTIONS / REFUNDS / CASH DRAWER / DAY CLOSING -->
    <div v-else class="space-y-3">
      <div v-if="items.length === 0" class="p-12 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
        <div class="text-3xl text-slate-400">💳</div>
        <h3 class="font-black text-sm text-slate-700 dark:text-slate-300">No Payment Records Found</h3>
        <p class="text-xs text-slate-400 font-medium">Try adjusting date range or mode filters.</p>
      </div>

      <div v-else class="space-y-3">
        <!-- Mobile Cards (<768px) -->
        <div class="space-y-3 md:hidden">
          <ReportListCard
            v-for="item in items"
            :key="item.id"
            :title="item.payment_number || item.refund_number || item.reference_number || ('PAY-' + item.id)"
            :subtitle="formatDate(item.payment_date || item.created_at)"
            :status="item.payment_method || item.refund_method || 'completed'"
            statusType="info"
            :metric="'₹' + formatCurrency(item.amount || item.refund_amount || 0)"
            metricLabel="Payment Amount"
            :details="[
              { label: 'Customer', value: item.customer ? item.customer.name : 'Walk-in' },
              { label: 'Payment Method', value: (item.payment_method || item.refund_method || 'Cash').toUpperCase() },
              { label: 'Ref / Note', value: item.transaction_reference || item.notes || 'N/A' }
            ]"
          />
        </div>

        <!-- Desktop Data Table (≥768px) -->
        <div class="hidden md:block bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 overflow-hidden shadow-xs">
          <table class="w-full text-left text-xs font-sans">
            <thead class="bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 text-[10px] uppercase font-black tracking-wider text-slate-500 dark:text-slate-400">
              <tr>
                <th class="py-3 px-4">Payment Ref</th>
                <th class="py-3 px-4">Date</th>
                <th class="py-3 px-4">Customer</th>
                <th class="py-3 px-4">Method</th>
                <th class="py-3 px-4">Transaction Ref</th>
                <th class="py-3 px-4 text-right">Amount</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
              <tr v-for="item in items" :key="item.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                <td class="py-3 px-4 font-mono font-bold text-slate-900 dark:text-white">
                  {{ item.payment_number || item.refund_number || item.reference_number || ('PAY-' + item.id) }}
                </td>
                <td class="py-3 px-4 text-slate-500">{{ formatDate(item.payment_date || item.created_at) }}</td>
                <td class="py-3 px-4 font-bold text-slate-800 dark:text-slate-200">{{ item.customer ? item.customer.name : 'Walk-in' }}</td>
                <td class="py-3 px-4 uppercase font-bold text-indigo-600">{{ item.payment_method || item.refund_method || 'Cash' }}</td>
                <td class="py-3 px-4 font-mono text-slate-500">{{ item.transaction_reference || 'N/A' }}</td>
                <td class="py-3 px-4 text-right font-mono font-bold text-emerald-600">₹{{ formatCurrency(item.amount || item.refund_amount || 0) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Reusable Mobile Bottom Sheet Filter -->
    <ReportFilterSheet
      v-model:show="showFilterSheet"
      :filters="filterState"
      :showGroupBy="activeTab === 'date-wise'"
      @apply="handleApplyFilters"
      @reset="handleResetFilters"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import ReportNavHeader from '../../components/ui/ReportNavHeader.vue';
import ReportKpiCard from '../../components/ui/ReportKpiCard.vue';
import ReportListCard from '../../components/ui/ReportListCard.vue';
import ReportFilterSheet from '../../components/ui/ReportFilterSheet.vue';
import api from '../../services/api';
import { exportToCsv } from '../../utils/reportExporter';
import { useReportPdf } from '../../composables/useReportPdf';

const { exportReportPdf } = useReportPdf();

function getReportTypeKey() {
  const typeMap = {
    'collections': 'payment_collections',
    'date-wise': 'payment_datewise',
    'refunds': 'payment_refunds',
    'cash-drawer': 'payment_summary',
    'day-closing': 'payment_summary',
  };
  return typeMap[activeTab.value] || 'payment_summary';
}

function handleViewPdf() {
  exportReportPdf(getReportTypeKey(), { ...filterState, search: searchQuery.value }, 'inline');
}

function handleDownloadPdf() {
  exportReportPdf(getReportTypeKey(), { ...filterState, search: searchQuery.value }, 'attachment');
}

const loading = ref(false);
const errorMsg = ref('');
const showFilterSheet = ref(false);
const searchQuery = ref('');
const activeTab = ref('collections');

const summary = ref({
  cash: 0,
  card: 0,
  upi: 0,
  bank: 0,
  other: 0,
  total_collections: 0,
  total_refunds: 0,
});

const items = ref([]);
const dateWisePayments = ref([]);

const filterState = reactive({
  date_from: '',
  date_to: '',
  group_by: 'day',
});

const subTabs = [
  { id: 'collections', name: 'Collections', icon: '💳' },
  { id: 'date-wise', name: 'Date-Wise Collections', icon: '📅' },
  { id: 'refunds', name: 'Refunds', icon: '💸' },
  { id: 'cash-drawer', name: 'Cash Drawer', icon: '💵' },
  { id: 'day-closing', name: 'Day Closing', icon: '🔒' },
];

const activeFilterCount = computed(() => {
  let count = 0;
  if (filterState.date_from || filterState.date_to) count++;
  return count;
});

const dateRangeBadge = computed(() => {
  if (filterState.date_from && filterState.date_to) {
    if (filterState.date_from === filterState.date_to) return filterState.date_from;
    return `${filterState.date_from} to ${filterState.date_to}`;
  }
  if (filterState.date_from) return `From ${filterState.date_from}`;
  if (filterState.date_to) return `Until ${filterState.date_to}`;
  return 'All Time';
});

let searchTimeout = null;
function debounceSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loadAllData();
  }, 350);
}

async function loadAllData() {
  loading.value = true;
  errorMsg.value = '';
  try {
    await fetchPaymentSummary();
    if (activeTab.value === 'date-wise') {
      await fetchDateWisePayments();
    } else {
      await fetchPaymentList();
    }
  } catch (e) {
    console.error('Failed to load payment reports:', e);
    errorMsg.value = e?.message || 'Failed to load payment reports. Please check network and retry.';
  } finally {
    loading.value = false;
  }
}

async function fetchPaymentSummary() {
  const params = {};
  if (filterState.date_from) params.date_from = filterState.date_from;
  if (filterState.date_to) params.date_to = filterState.date_to;

  const res = await api.get('/reports/payments-summary', { params });
  const payload = (res && res.data !== undefined) ? res.data : res;
  if (payload) {
    summary.value = payload;
  }
}

async function fetchDateWisePayments() {
  const params = {
    group_by: filterState.group_by || 'day',
  };
  if (filterState.date_from) params.date_from = filterState.date_from;
  if (filterState.date_to) params.date_to = filterState.date_to;

  const res = await api.get('/reports/date-wise-payments', { params });
  const payload = (res && res.data !== undefined) ? res.data : res;
  if (payload) {
    dateWisePayments.value = payload.items || (Array.isArray(payload) ? payload : []);
  }
}

async function fetchPaymentList() {
  const params = {};
  if (filterState.date_from) params.date_from = filterState.date_from;
  if (filterState.date_to) params.date_to = filterState.date_to;
  if (searchQuery.value) params.search = searchQuery.value;

  let endpoint = '/payments/collections';
  if (activeTab.value === 'refunds') endpoint = '/payments/refunds';
  else if (activeTab.value === 'cash-drawer') endpoint = '/payments/cash-drawer/movements';
  else if (activeTab.value === 'day-closing') endpoint = '/payments/day-closing';

  const res = await api.get(endpoint, { params });
  if (res.data) {
    const payload = res.data.data || res.data;
    items.value = Array.isArray(payload) ? payload : (payload.items || payload.data || []);
  }
}

function handleApplyFilters(newFilters) {
  Object.assign(filterState, newFilters);
  loadAllData();
}

function handleResetFilters() {
  filterState.date_from = '';
  filterState.date_to = '';
  filterState.group_by = 'day';
  loadAllData();
}

// EXPORT PDF HANDLER
function handleExportPdf() {
  const filterDesc = getFilterDesc();
  const kpiCards = [
    { title: 'Cash Collections', value: '₹' + formatCurrency(summary.value.cash) },
    { title: 'Card Payments', value: '₹' + formatCurrency(summary.value.card) },
    { title: 'UPI Payments', value: '₹' + formatCurrency(summary.value.upi) },
    { title: 'Total Refunds', value: '₹' + formatCurrency(summary.value.total_refunds), isLoss: true },
  ];

  if (activeTab.value === 'date-wise') {
    const cols = [
      { label: 'Period / Date', field: 'date_period' },
      { label: 'Cash', value: r => '₹' + formatCurrency(r.cash_amount), align: 'right' },
      { label: 'Card', value: r => '₹' + formatCurrency(r.card_amount), align: 'right' },
      { label: 'UPI', value: r => '₹' + formatCurrency(r.upi_amount), align: 'right' },
      { label: 'Bank', value: r => '₹' + formatCurrency(r.bank_amount), align: 'right' },
      { label: 'Total Collections', value: r => '₹' + formatCurrency(r.total_collections), align: 'right' },
      { label: 'Refunds', value: r => '₹' + formatCurrency(r.refund_amount), align: 'right' },
      { label: 'Net Collections', value: r => '₹' + formatCurrency(r.net_collections), align: 'right' },
    ];
    exportToPdf('Date-Wise Payment Collections Report', filterDesc, kpiCards, cols, dateWisePayments.value);
  } else {
    const cols = [
      { label: 'Payment Ref', value: r => r.payment_number || r.refund_number || r.reference_number || ('PAY-' + r.id) },
      { label: 'Date', value: r => formatDate(r.payment_date || r.created_at) },
      { label: 'Customer', value: r => r.customer ? r.customer.name : 'Walk-in' },
      { label: 'Method', value: r => (r.payment_method || r.refund_method || 'Cash').toUpperCase() },
      { label: 'Transaction Ref', field: 'transaction_reference' },
      { label: 'Amount', value: r => '₹' + formatCurrency(r.amount || r.refund_amount || 0), align: 'right' },
    ];
    exportToPdf(`Payment ${activeTab.value.toUpperCase()} Report`, filterDesc, kpiCards, cols, items.value);
  }
}

// EXPORT CSV HANDLER
function handleExportCsv() {
  if (activeTab.value === 'date-wise') {
    const cols = [
      { label: 'Period / Date', field: 'date_period' },
      { label: 'Cash Amount', field: 'cash_amount' },
      { label: 'Card Amount', field: 'card_amount' },
      { label: 'UPI Amount', field: 'upi_amount' },
      { label: 'Bank Amount', field: 'bank_amount' },
      { label: 'Other Amount', field: 'other_amount' },
      { label: 'Total Collections', field: 'total_collections' },
      { label: 'Refund Amount', field: 'refund_amount' },
      { label: 'Net Collections', field: 'net_collections' },
    ];
    exportToCsv('date_wise_payments_report', cols, dateWisePayments.value);
  } else {
    const cols = [
      { label: 'Reference Number', value: r => r.payment_number || r.refund_number || r.reference_number || ('PAY-' + r.id) },
      { label: 'Date', value: r => formatDate(r.payment_date || r.created_at) },
      { label: 'Customer', value: r => r.customer ? r.customer.name : 'Walk-in' },
      { label: 'Payment Method', value: r => (r.payment_method || r.refund_method || 'Cash').toUpperCase() },
      { label: 'Transaction Ref', field: 'transaction_reference' },
      { label: 'Amount', value: r => r.amount || r.refund_amount || 0 },
    ];
    exportToCsv(`payment_${activeTab.value}_report`, cols, items.value);
  }
}

function getFilterDesc() {
  const parts = [];
  if (filterState.date_from || filterState.date_to) {
    parts.push(`Date: ${filterState.date_from || 'Start'} to ${filterState.date_to || 'Today'}`);
  } else {
    parts.push('Date: All Time');
  }
  return parts.join(' | ');
}

function formatCurrency(val) {
  const num = Number(val) || 0;
  return num.toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

onMounted(() => {
  loadAllData();
});
</script>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>

