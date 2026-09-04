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

    <!-- Header & Search Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-xs">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <h2 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
            <span>📄</span>
            <span>Purchase & Procurement Analytics</span>
          </h2>
          <span class="px-2.5 py-0.5 text-[10px] font-extrabold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-full border border-slate-200 dark:border-slate-700">
            📅 {{ dateRangeBadge }}
          </span>
        </div>
        <p class="text-xs text-slate-500 font-medium mt-0.5">Vendor spend, purchase bills, GRN receiving logs, POs & supplier balances</p>
      </div>

      <div class="flex items-center gap-2">
        <div class="relative flex-1 sm:w-64">
          <span class="absolute left-3 top-2.5 text-xs text-slate-400">🔍</span>
          <input
            type="text"
            v-model="searchQuery"
            @input="debounceSearch"
            placeholder="Search bill no, supplier, PO..."
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

    <!-- 5 KPI Summary Cards Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
      <ReportKpiCard
        title="Total Purchases"
        :value="summary.total_purchases"
        :isCurrency="true"
        icon="🛍️"
        accentColor="indigo"
        subtext="Billed supplier spend"
      />
      <ReportKpiCard
        title="Purchase Bills"
        :value="summary.purchase_bills_count"
        icon="📄"
        accentColor="emerald"
        subtext="Total bills received"
      />
      <ReportKpiCard
        title="Pending POs"
        :value="summary.pending_pos_count"
        icon="⏳"
        accentColor="amber"
        subtext="Active purchase orders"
      />
      <ReportKpiCard
        title="Purchase Returns"
        :value="summary.purchase_returns_total"
        :isCurrency="true"
        icon="↩️"
        accentColor="red"
        subtext="Vendor returns"
      />
      <ReportKpiCard
        title="Supplier Outstanding"
        :value="summary.outstanding_supplier_amount"
        :isCurrency="true"
        icon="🏢"
        accentColor="red"
        subtext="Unpaid vendor balance"
      />
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="p-12 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
      <div class="inline-block animate-spin text-2xl text-red-600">⌛</div>
      <p class="text-xs font-extrabold text-slate-500">Loading purchase report data...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="items.length === 0" class="p-12 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
      <div class="text-3xl text-slate-400">📄</div>
      <h3 class="font-black text-sm text-slate-700 dark:text-slate-300">No Purchase Documents Found</h3>
      <p class="text-xs text-slate-400 font-medium">Try adjusting date range or search parameters.</p>
    </div>

    <!-- Purchase Results List -->
    <div v-else class="space-y-3">
      <!-- Mobile Cards (<768px) -->
      <div class="space-y-3 md:hidden">
        <ReportListCard
          v-for="item in items"
          :key="item.id"
          :title="item.bill_number || item.po_number || item.grn_number || item.return_number || item.name"
          :subtitle="item.created_at ? formatDate(item.created_at) : (item.bill_date ? formatDate(item.bill_date) : '')"
          :status="item.status || item.payment_status || 'completed'"
          :statusType="item.payment_status === 'paid' || item.status === 'received' ? 'success' : 'warning'"
          :metric="'₹' + formatCurrency(item.grand_total || item.total_amount || 0)"
          metricLabel="Total Amount"
          :details="[
            { label: 'Supplier', value: item.supplier ? item.supplier.name : (item.company_name || 'N/A') },
            { label: 'Paid Amount', value: '₹' + formatCurrency(item.paid_amount || 0) },
            { label: 'Due Balance', value: '₹' + formatCurrency(item.due_amount || 0) }
          ]"
        >
          <template #details>
            <div class="space-y-1 text-xs pt-1">
              <div v-if="item.items && item.items.length" class="space-y-1">
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Billed Items:</span>
                <div v-for="it in item.items" :key="it.id" class="flex justify-between items-center bg-slate-50 dark:bg-slate-950 p-2 rounded-lg text-[11px]">
                  <div>
                    <span class="font-bold text-slate-900 dark:text-white block">{{ it.product_name || it.sku }}</span>
                    <span class="text-slate-400 text-[10px]">Qty: {{ it.quantity }} × ₹{{ it.unit_price || it.cost_price }}</span>
                  </div>
                  <span class="font-bold font-mono">₹{{ it.subtotal || (it.quantity * it.cost_price) }}</span>
                </div>
              </div>
            </div>
          </template>
        </ReportListCard>
      </div>

      <!-- Desktop Data Table (≥768px) -->
      <div class="hidden md:block bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 overflow-hidden shadow-xs">
        <table class="w-full text-left text-xs font-sans">
          <thead class="bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 text-[10px] uppercase font-black tracking-wider text-slate-500 dark:text-slate-400">
            <tr>
              <th class="py-3 px-4">Document Ref</th>
              <th class="py-3 px-4">Date</th>
              <th class="py-3 px-4">Supplier</th>
              <th class="py-3 px-4 text-center">Status</th>
              <th class="py-3 px-4 text-right">Grand Total</th>
              <th class="py-3 px-4 text-right">Paid</th>
              <th class="py-3 px-4 text-right">Due</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
            <tr v-for="item in items" :key="item.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
              <td class="py-3 px-4 font-mono font-bold text-slate-900 dark:text-white">
                {{ item.bill_number || item.po_number || item.grn_number || item.return_number || item.name }}
              </td>
              <td class="py-3 px-4 text-slate-500">{{ formatDate(item.created_at || item.bill_date) }}</td>
              <td class="py-3 px-4 font-bold text-slate-800 dark:text-slate-200">{{ item.supplier ? item.supplier.name : (item.company_name || 'N/A') }}</td>
              <td class="py-3 px-4 text-center">
                <span :class="[
                  'px-2 py-0.5 text-[9px] font-black rounded-md uppercase',
                  item.payment_status === 'paid' || item.status === 'received' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                ]">
                  {{ item.payment_status || item.status || 'completed' }}
                </span>
              </td>
              <td class="py-3 px-4 text-right font-mono font-bold text-slate-900 dark:text-white">₹{{ formatCurrency(item.grand_total || item.total_amount || 0) }}</td>
              <td class="py-3 px-4 text-right font-mono text-emerald-600 font-bold">₹{{ formatCurrency(item.paid_amount || 0) }}</td>
              <td class="py-3 px-4 text-right font-mono text-red-600 font-bold">₹{{ formatCurrency(item.due_amount || 0) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Reusable Mobile Bottom Sheet Filter -->
    <ReportFilterSheet
      v-model:show="showFilterSheet"
      :filters="filterState"
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
    'bills': 'purchase_bills',
    'orders': 'purchase_orders',
    'grn': 'purchase_grn',
    'returns': 'purchase_returns',
    'suppliers': 'purchase_summary',
  };
  return typeMap[activeTab.value] || 'purchase_summary';
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
const activeTab = ref('bills');

const summary = ref({
  total_purchases: 0,
  purchase_bills_count: 0,
  pending_pos_count: 0,
  purchase_returns_total: 0,
  outstanding_supplier_amount: 0,
});

const items = ref([]);
const filterState = reactive({
  date_from: '',
  date_to: '',
});

const subTabs = [
  { id: 'bills', name: 'Purchase Bills', icon: '📑' },
  { id: 'orders', name: 'Purchase Orders', icon: '📝' },
  { id: 'grn', name: 'Goods Received (GRN)', icon: '📥' },
  { id: 'returns', name: 'Purchase Returns', icon: '↩️' },
  { id: 'suppliers', name: 'Supplier Purchases', icon: '🏢' },
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
    await Promise.all([fetchPurchaseSummary(), fetchPurchaseItems()]);
  } catch (e) {
    console.error('Failed to load purchase reports:', e);
    errorMsg.value = e?.message || 'Failed to load purchase reports. Please check network and retry.';
  } finally {
    loading.value = false;
  }
}

async function fetchPurchaseSummary() {
  const params = {};
  if (filterState.date_from) params.date_from = filterState.date_from;
  if (filterState.date_to) params.date_to = filterState.date_to;

  const res = await api.get('/reports/purchases-summary', { params });
  const payload = (res && res.data !== undefined) ? res.data : res;
  if (payload) {
    summary.value = payload;
  }
}

async function fetchPurchaseItems() {
  const params = {};
  if (filterState.date_from) params.date_from = filterState.date_from;
  if (filterState.date_to) params.date_to = filterState.date_to;
  if (searchQuery.value) params.search = searchQuery.value;

  let endpoint = '/purchases/bills';
  if (activeTab.value === 'orders') endpoint = '/purchases/orders';
  else if (activeTab.value === 'grn') endpoint = '/purchases/grn';
  else if (activeTab.value === 'returns') endpoint = '/purchases/returns';
  else if (activeTab.value === 'suppliers') endpoint = '/suppliers';

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
  loadAllData();
}

// EXPORT PDF HANDLER
function handleExportPdf() {
  const filterDesc = getFilterDesc();
  const kpiCards = [
    { title: 'Total Spend', value: '₹' + formatCurrency(summary.value.total_purchases) },
    { title: 'Purchase Bills Count', value: summary.value.purchase_bills_count },
    { title: 'Pending POs', value: summary.value.pending_pos_count },
    { title: 'Supplier Outstanding', value: '₹' + formatCurrency(summary.value.outstanding_supplier_amount), isLoss: true },
  ];

  const cols = [
    { label: 'Ref No', value: r => r.bill_number || r.po_number || r.grn_number || r.return_number || r.name },
    { label: 'Date', value: r => formatDate(r.created_at || r.bill_date) },
    { label: 'Supplier', value: r => r.supplier ? r.supplier.name : (r.company_name || 'N/A') },
    { label: 'Status', value: r => r.payment_status || r.status || 'completed' },
    { label: 'Grand Total', value: r => '₹' + formatCurrency(r.grand_total || r.total_amount || 0), align: 'right' },
    { label: 'Paid Amount', value: r => '₹' + formatCurrency(r.paid_amount || 0), align: 'right' },
    { label: 'Due Balance', value: r => '₹' + formatCurrency(r.due_amount || 0), align: 'right' },
  ];
  exportToPdf(`Purchase ${activeTab.value.toUpperCase()} Report`, filterDesc, kpiCards, cols, items.value);
}

// EXPORT CSV HANDLER
function handleExportCsv() {
  const cols = [
    { label: 'Document Ref', value: r => r.bill_number || r.po_number || r.grn_number || r.return_number || r.name },
    { label: 'Date', value: r => formatDate(r.created_at || r.bill_date) },
    { label: 'Supplier', value: r => r.supplier ? r.supplier.name : (r.company_name || 'N/A') },
    { label: 'Status', value: r => r.payment_status || r.status || 'completed' },
    { label: 'Grand Total', value: r => r.grand_total || r.total_amount || 0 },
    { label: 'Paid Amount', value: r => r.paid_amount || 0 },
    { label: 'Due Amount', value: r => r.due_amount || 0 },
  ];
  exportToCsv(`purchase_${activeTab.value}_report`, cols, items.value);
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

