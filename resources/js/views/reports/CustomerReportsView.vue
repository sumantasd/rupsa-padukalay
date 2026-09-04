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
            <span>👥</span>
            <span>Customer Reports & Analytics</span>
          </h2>
          <span class="px-2.5 py-0.5 text-[10px] font-extrabold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-full border border-slate-200 dark:border-slate-700">
            📅 {{ dateRangeBadge }}
          </span>
        </div>
        <p class="text-xs text-slate-500 font-medium mt-0.5">Customer sales history, outstanding balances, payment ledgers & loyalty summary</p>
      </div>

      <div class="flex items-center gap-2">
        <div class="relative flex-1 sm:w-64">
          <span class="absolute left-3 top-2.5 text-xs text-slate-400">🔍</span>
          <input
            type="text"
            v-model="searchQuery"
            @input="debounceSearch"
            placeholder="Search customer name, phone..."
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

    <!-- 4 KPI Summary Cards Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <ReportKpiCard
        title="Total Customers"
        :value="summary.total_customers"
        icon="👥"
        accentColor="indigo"
        subtext="Registered customer base"
      />
      <ReportKpiCard
        title="Total Purchases"
        :value="summary.total_purchase_amount"
        :isCurrency="true"
        icon="🛍️"
        accentColor="emerald"
        subtext="Customer sales volume"
      />
      <ReportKpiCard
        title="Total Outstanding"
        :value="summary.total_outstanding_amount"
        :isCurrency="true"
        icon="⏳"
        accentColor="red"
        subtext="Uncollected customer credit"
      />
      <ReportKpiCard
        title="Loyalty Points Balance"
        :value="summary.total_loyalty_points"
        icon="⭐"
        accentColor="amber"
        subtext="Active rewards points"
      />
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="p-12 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
      <div class="inline-block animate-spin text-2xl text-red-600">⌛</div>
      <p class="text-xs font-extrabold text-slate-500">Loading customer report data...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="customers.length === 0" class="p-12 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
      <div class="text-3xl text-slate-400">👥</div>
      <h3 class="font-black text-sm text-slate-700 dark:text-slate-300">No Customer Records Found</h3>
      <p class="text-xs text-slate-400 font-medium">Try searching by name or mobile number.</p>
    </div>

    <!-- Customer Results List (Mobile Cards & Desktop Table) -->
    <div v-else class="space-y-3">
      <!-- Mobile Cards (<768px) -->
      <div class="space-y-3 md:hidden">
        <ReportListCard
          v-for="cust in customers"
          :key="cust.id"
          :title="cust.name"
          :subtitle="'Phone: ' + (cust.mobile_number || cust.phone || 'N/A')"
          :status="(cust.outstanding_balance || 0) > 0 ? 'DUE' : 'CLEAR'"
          :statusType="(cust.outstanding_balance || 0) > 0 ? 'warning' : 'success'"
          :metric="'₹' + formatCurrency(cust.total_purchase_amount || 0)"
          metricLabel="Total Purchase"
          :details="[
            { label: 'Paid Amount', value: '₹' + formatCurrency(cust.paid_amount || 0) },
            { label: 'Outstanding', value: '₹' + formatCurrency(cust.outstanding_balance || 0) },
            { label: 'Loyalty Points', value: (cust.loyalty_points || cust.points_balance || 0) + ' pts' },
            { label: 'Invoices Count', value: cust.invoices_count || 0 }
          ]"
        >
          <template #details>
            <div class="space-y-2 text-xs pt-1">
              <div class="flex justify-between items-center bg-slate-50 dark:bg-slate-950 p-2 rounded-lg text-[11px]">
                <span class="text-slate-500 font-bold">Email:</span>
                <span class="font-bold text-slate-800 dark:text-slate-200">{{ cust.email || 'N/A' }}</span>
              </div>
              <div class="flex justify-between items-center bg-slate-50 dark:bg-slate-950 p-2 rounded-lg text-[11px]">
                <span class="text-slate-500 font-bold">City / Address:</span>
                <span class="font-bold text-slate-800 dark:text-slate-200">{{ cust.city || cust.address || 'Kolkata' }}</span>
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
              <th class="py-3 px-4">Customer Name</th>
              <th class="py-3 px-4">Mobile Number</th>
              <th class="py-3 px-4">City</th>
              <th class="py-3 px-4 text-center">Loyalty Pts</th>
              <th class="py-3 px-4 text-right">Total Purchase</th>
              <th class="py-3 px-4 text-right">Paid</th>
              <th class="py-3 px-4 text-right">Outstanding</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
            <tr v-for="cust in customers" :key="cust.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
              <td class="py-3 px-4 font-bold text-slate-900 dark:text-white">{{ cust.name }}</td>
              <td class="py-3 px-4 font-mono text-slate-500">{{ cust.mobile_number || cust.phone || 'N/A' }}</td>
              <td class="py-3 px-4 text-slate-500">{{ cust.city || 'Kolkata' }}</td>
              <td class="py-3 px-4 text-center font-bold font-mono text-amber-500">{{ cust.loyalty_points || cust.points_balance || 0 }}</td>
              <td class="py-3 px-4 text-right font-mono font-bold text-slate-900 dark:text-white">₹{{ formatCurrency(cust.total_purchase_amount || 0) }}</td>
              <td class="py-3 px-4 text-right font-mono text-emerald-600 font-bold">₹{{ formatCurrency(cust.paid_amount || 0) }}</td>
              <td class="py-3 px-4 text-right font-mono text-red-600 font-bold">₹{{ formatCurrency(cust.outstanding_balance || 0) }}</td>
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

function handleViewPdf() {
  exportReportPdf('customer_summary', { ...filterState, search: searchQuery.value }, 'inline');
}

function handleDownloadPdf() {
  exportReportPdf('customer_summary', { ...filterState, search: searchQuery.value }, 'attachment');
}

const loading = ref(false);
const errorMsg = ref('');
const showFilterSheet = ref(false);
const searchQuery = ref('');

const summary = ref({
  total_customers: 0,
  total_purchase_amount: 0,
  total_paid_amount: 0,
  total_outstanding_amount: 0,
  total_loyalty_points: 0,
});

const customers = ref([]);
const filterState = reactive({
  date_from: '',
  date_to: '',
});

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
    await Promise.all([fetchCustomerSummary(), fetchCustomersList()]);
  } catch (e) {
    console.error('Failed to load customer reports:', e);
    errorMsg.value = e?.message || 'Failed to load customer reports. Please check network and retry.';
  } finally {
    loading.value = false;
  }
}

async function fetchCustomerSummary() {
  const params = {};
  if (filterState.date_from) params.date_from = filterState.date_from;
  if (filterState.date_to) params.date_to = filterState.date_to;
  if (filterState.store_id) params.store_id = filterState.store_id;

  const res = await api.get('/reports/customers-summary', { params });
  const payload = (res && res.data !== undefined) ? res.data : res;
  if (payload) {
    summary.value = payload;
  }
}

async function fetchCustomersList() {
  const params = {};
  if (filterState.date_from) params.date_from = filterState.date_from;
  if (filterState.date_to) params.date_to = filterState.date_to;
  if (filterState.store_id) params.store_id = filterState.store_id;
  if (searchQuery.value) params.search = searchQuery.value;

  const res = await api.get('/customers', { params });
  if (res.data) {
    const payload = res.data.data || res.data;
    customers.value = Array.isArray(payload) ? payload : (payload.items || payload.data || []);
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
    { title: 'Total Customers', value: summary.value.total_customers },
    { title: 'Total Sales Volume', value: '₹' + formatCurrency(summary.value.total_purchase_amount) },
    { title: 'Total Outstanding Credit', value: '₹' + formatCurrency(summary.value.total_outstanding_amount), isLoss: true },
    { title: 'Loyalty Points Balance', value: summary.value.total_loyalty_points },
  ];

  const cols = [
    { label: 'Customer Name', field: 'name' },
    { label: 'Mobile Number', value: r => r.mobile_number || r.phone || 'N/A' },
    { label: 'City', value: r => r.city || 'Kolkata' },
    { label: 'Loyalty Pts', value: r => r.loyalty_points || r.points_balance || 0, align: 'center' },
    { label: 'Total Purchase', value: r => '₹' + formatCurrency(r.total_purchase_amount || 0), align: 'right' },
    { label: 'Paid Amount', value: r => '₹' + formatCurrency(r.paid_amount || 0), align: 'right' },
    { label: 'Outstanding Balance', value: r => '₹' + formatCurrency(r.outstanding_balance || 0), align: 'right' },
  ];
  exportToPdf('Customer Accounts & Analytics Report', filterDesc, kpiCards, cols, customers.value);
}

// EXPORT CSV HANDLER
function handleExportCsv() {
  const cols = [
    { label: 'Customer Name', field: 'name' },
    { label: 'Mobile Number', value: r => r.mobile_number || r.phone || 'N/A' },
    { label: 'City', value: r => r.city || 'Kolkata' },
    { label: 'Loyalty Points', value: r => r.loyalty_points || r.points_balance || 0 },
    { label: 'Total Purchase Amount', value: r => r.total_purchase_amount || 0 },
    { label: 'Paid Amount', value: r => r.paid_amount || 0 },
    { label: 'Outstanding Balance', value: r => r.outstanding_balance || 0 },
  ];
  exportToCsv('customer_accounts_report', cols, customers.value);
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
