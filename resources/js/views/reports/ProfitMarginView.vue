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

    <!-- Header Description & Sub-tabs Switcher -->
    <div class="space-y-3 bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-xs">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <div class="flex items-center gap-2 flex-wrap">
            <h2 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
              <span>📈</span>
              <span>Profit & Loss / Margin Statement</span>
            </h2>
            <span class="px-2.5 py-0.5 text-[10px] font-extrabold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-full border border-slate-200 dark:border-slate-700">
              📅 {{ dateRangeBadge }}
            </span>
            <span :class="['px-2.5 py-0.5 text-[10px] font-black rounded-full uppercase tracking-wider', profitability.net_profit >= 0 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800' : 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300 border border-red-300 dark:border-red-800']">
              {{ profitability.net_profit >= 0 ? 'PROFIT' : 'LOSS' }} ₹{{ formatCurrency(Math.abs(profitability.net_profit || 0)) }}
            </span>
          </div>
          <p class="text-xs text-slate-500 font-medium mt-0.5">Authoritative financial sales reconciliation, COGS calculations, and net margin analysis</p>
        </div>

        <button
          @click="loadAllData"
          class="flex items-center gap-1.5 px-3 py-2 bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs rounded-xl shadow-md transition-all uppercase tracking-wider cursor-pointer self-start sm:self-auto"
        >
          <span>🔄</span>
          <span>Recalculate Statement</span>
        </button>
      </div>

      <!-- Sub-tabs Switcher -->
      <div class="flex items-center gap-1.5 p-1 bg-slate-100 dark:bg-slate-950 rounded-xl overflow-x-auto">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          type="button"
          @click="activeTab = tab.id; loadAllData();"
          :class="[
            'py-2 px-3.5 rounded-lg font-extrabold text-xs transition-all whitespace-nowrap flex items-center gap-1.5 cursor-pointer',
            activeTab === tab.id
              ? 'bg-red-600 text-white shadow-xs shadow-red-600/30'
              : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
          ]"
        >
          <span>{{ tab.icon }}</span>
          <span>{{ tab.label }}</span>
        </button>
      </div>
    </div>

    <!-- Error Retry Banner -->
    <div v-if="errorMsg" class="p-6 text-center bg-red-50 dark:bg-red-950/20 rounded-2xl border border-red-200 dark:border-red-900/40 space-y-3">
      <div class="text-2xl text-red-600">⚠️</div>
      <h3 class="font-black text-sm text-red-800 dark:text-red-300">Unable to Load Profit & Loss Statement</h3>
      <p class="text-xs text-red-600 dark:text-red-400 font-medium">{{ errorMsg }}</p>
      <button
        @click="loadAllData"
        class="px-4 py-2 bg-red-600 hover:bg-red-700 active:scale-95 text-white font-extrabold text-xs rounded-xl shadow-md transition-all uppercase cursor-pointer"
      >
        🔄 Retry Report Loading
      </button>
    </div>

    <!-- Loading State -->
    <div v-else-if="loading" class="p-12 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
      <div class="inline-block animate-spin text-2xl text-red-600">⌛</div>
      <p class="text-xs font-extrabold text-slate-500">Calculating authoritative P&L statement...</p>
    </div>

    <div v-else class="space-y-4">
      <!-- 8 Key Financial KPI Summary Cards Grid -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <ReportKpiCard
          title="Gross Sales Revenue"
          :value="revenue.gross_sales"
          :isCurrency="true"
          icon="💰"
          accentColor="indigo"
          subtext="Billed invoice subtotal"
        />
        <ReportKpiCard
          title="Cost of Goods Sold"
          :value="cogs.net_cogs"
          :isCurrency="true"
          icon="📦"
          accentColor="slate"
          subtext="Valued inventory cost"
        />
        <ReportKpiCard
          title="Gross Operating Profit"
          :value="profitability.gross_profit"
          :isCurrency="true"
          icon="📈"
          :accentColor="profitability.gross_profit >= 0 ? 'emerald' : 'red'"
          :subtext="profitability.gross_margin_percentage + '% Margin (' + (profitability.gross_profit >= 0 ? 'PROFIT' : 'LOSS') + ')'"
          :subtextType="profitability.gross_profit >= 0 ? 'success' : 'danger'"
        />
        <ReportKpiCard
          title="Net Profit / Loss"
          :value="profitability.net_profit"
          :isCurrency="true"
          icon="🎯"
          :accentColor="profitability.net_profit >= 0 ? 'emerald' : 'red'"
          :subtext="profitability.net_profit >= 0 ? 'PROFIT' : 'LOSS'"
          :subtextType="profitability.net_profit >= 0 ? 'success' : 'danger'"
        />
        <ReportKpiCard
          title="Sales Discounts"
          :value="revenue.discounts"
          :isCurrency="true"
          icon="🏷️"
          accentColor="amber"
          subtext="Total discount given"
        />
        <ReportKpiCard
          title="Returns & Refunds"
          :value="revenue.returns_refunds"
          :isCurrency="true"
          icon="↩️"
          accentColor="red"
          subtext="Refunded sales"
        />
        <ReportKpiCard
          title="GST Billed"
          :value="revenue.gst_collected"
          :isCurrency="true"
          icon="🧾"
          accentColor="indigo"
          subtext="Tax collected"
        />
        <ReportKpiCard
          title="Net Sales Revenue"
          :value="revenue.net_sales || revenue.net_billed_revenue || (revenue.gross_sales - revenue.discounts - revenue.returns_refunds)"
          :isCurrency="true"
          icon="💵"
          accentColor="emerald"
          subtext="Gross Sales minus discounts & returns"
        />
      </div>

    <!-- TAB 1: SUMMARY & CALCULATION BREAKDOWN -->
    <div v-if="activeTab === 'summary'" class="space-y-4">
      <!-- Clear Financial Calculation Breakdown Box -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-3">
        <h3 class="font-black text-sm text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
          <span>📐</span>
          <span>Financial Calculation Breakdown</span>
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
          <!-- Step 1: Net Sales -->
          <div class="p-3 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 space-y-1.5">
            <div class="font-black text-slate-500 uppercase text-[10px]">1. Net Sales (Tax-Inclusive Billed)</div>
            <div class="flex justify-between font-mono text-[11px]">
              <span class="text-slate-500">Gross Sales:</span>
              <span class="font-bold">₹{{ formatCurrency(revenue.gross_sales) }}</span>
            </div>
            <div class="flex justify-between font-mono text-[11px] text-amber-600">
              <span>- Discounts:</span>
              <span>₹{{ formatCurrency(revenue.discounts) }}</span>
            </div>
            <div class="flex justify-between font-mono text-[11px] text-red-600">
              <span>- Returns:</span>
              <span>₹{{ formatCurrency(revenue.returns_refunds) }}</span>
            </div>
            <div class="flex justify-between font-mono text-xs font-black border-t border-slate-200 dark:border-slate-800 pt-1 text-slate-900 dark:text-white">
              <span>= Net Sales:</span>
              <span class="text-emerald-600">₹{{ formatCurrency(revenue.net_sales || revenue.net_billed_revenue || (revenue.gross_sales - revenue.discounts - revenue.returns_refunds)) }}</span>
            </div>
          </div>

          <!-- Step 2: Gross Profit -->
          <div class="p-3 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 space-y-1.5">
            <div class="font-black text-slate-500 uppercase text-[10px]">2. Gross Profit</div>
            <div class="flex justify-between font-mono text-[11px]">
              <span class="text-slate-500">Net Sales:</span>
              <span class="font-bold">₹{{ formatCurrency(revenue.net_sales || revenue.net_billed_revenue || (revenue.gross_sales - revenue.discounts - revenue.returns_refunds)) }}</span>
            </div>
            <div class="flex justify-between font-mono text-[11px] text-slate-500">
              <span>- COGS:</span>
              <span>₹{{ formatCurrency(cogs.net_cogs || cogs.cogs || 0) }}</span>
            </div>
            <div class="flex justify-between font-mono text-xs font-black border-t border-slate-200 dark:border-slate-800 pt-1">
              <span>= Gross Profit:</span>
              <span :class="profitability.gross_profit >= 0 ? 'text-emerald-600' : 'text-red-600'">
                ₹{{ formatCurrency(profitability.gross_profit) }}
              </span>
            </div>
          </div>

          <!-- Step 3: Net Profit / Loss -->
          <div class="p-3 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 space-y-1.5">
            <div class="font-black text-slate-500 uppercase text-[10px]">3. Net Profit / Loss</div>
            <div class="flex justify-between font-mono text-[11px]">
              <span class="text-slate-500">Gross Profit:</span>
              <span class="font-bold">₹{{ formatCurrency(profitability.gross_profit) }}</span>
            </div>
            <div class="flex justify-between font-mono text-[11px] text-red-600">
              <span>- Expenses:</span>
              <span>₹{{ formatCurrency(profitability.operating_expenses || 0) }}</span>
            </div>
            <div class="flex justify-between font-mono text-xs font-black border-t border-slate-200 dark:border-slate-800 pt-1">
              <span>= Net Result:</span>
              <span :class="['px-2 py-0.5 rounded text-[11px]', profitability.net_profit >= 0 ? 'bg-emerald-500/20 text-emerald-600' : 'bg-red-500/20 text-red-600']">
                {{ profitability.net_profit >= 0 ? 'PROFIT' : 'LOSS' }} ₹{{ formatCurrency(Math.abs(profitability.net_profit || 0)) }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Operating Expense Categories -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-3">
          <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
            <h3 class="font-black text-sm text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
              <span>💸</span>
              <span>Operating Expenses</span>
            </h3>
            <span class="font-mono font-bold text-red-600">₹{{ formatCurrency(profitability.operating_expenses || 0) }}</span>
          </div>

          <div v-if="expenseBreakdown.length === 0" class="p-4 text-center text-xs text-slate-400 font-medium">
            No operating expenses recorded for this period.
          </div>
          <div v-else class="space-y-2">
            <div
              v-for="exp in expenseBreakdown"
              :key="exp.category_id"
              class="flex justify-between items-center bg-slate-50 dark:bg-slate-950 p-2.5 rounded-xl text-xs"
            >
              <span class="font-bold text-slate-800 dark:text-slate-200">{{ exp.category_name }}</span>
              <span class="font-mono font-bold text-slate-900 dark:text-white">₹{{ formatCurrency(exp.amount) }}</span>
            </div>
          </div>
        </div>

        <!-- Period Growth Comparison -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-3">
          <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
            <h3 class="font-black text-sm text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
              <span>📊</span>
              <span>Period Growth Comparison</span>
            </h3>
            <span class="text-[10px] font-bold text-slate-400 uppercase">vs Prior Period</span>
          </div>

          <div class="space-y-2 text-xs">
            <div class="flex justify-between items-center bg-slate-50 dark:bg-slate-950 p-2.5 rounded-xl">
              <span class="text-slate-500 font-bold">Revenue Growth:</span>
              <span :class="['font-mono font-black', growth.net_revenue_growth_pct >= 0 ? 'text-emerald-600' : 'text-red-600']">
                {{ growth.net_revenue_growth_pct >= 0 ? '▲ +' : '▼ ' }}{{ growth.net_revenue_growth_pct }}%
              </span>
            </div>
            <div class="flex justify-between items-center bg-slate-50 dark:bg-slate-950 p-2.5 rounded-xl">
              <span class="text-slate-500 font-bold">Gross Profit Growth:</span>
              <span :class="['font-mono font-black', growth.gross_profit_growth_pct >= 0 ? 'text-emerald-600' : 'text-red-600']">
                {{ growth.gross_profit_growth_pct >= 0 ? '▲ +' : '▼ ' }}{{ growth.gross_profit_growth_pct }}%
              </span>
            </div>
            <div class="flex justify-between items-center bg-slate-50 dark:bg-slate-950 p-2.5 rounded-xl">
              <span class="text-slate-500 font-bold">Net Profit Growth:</span>
              <span :class="['font-mono font-black', growth.net_profit_growth_pct >= 0 ? 'text-emerald-600' : 'text-red-600']">
                {{ growth.net_profit_growth_pct >= 0 ? '▲ +' : '▼ ' }}{{ growth.net_profit_growth_pct }}%
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 2: DATE-WISE P&L -->
    <div v-else-if="activeTab === 'date_wise'" class="space-y-3">
      <div v-if="dateWisePL.length === 0" class="p-12 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
        <div class="text-3xl text-slate-400">📅</div>
        <h3 class="font-black text-sm text-slate-700 dark:text-slate-300">No Date-Wise P&L Data</h3>
        <p class="text-xs text-slate-400 font-medium">Try adjusting date range or group-by filter.</p>
      </div>

      <div v-else class="space-y-3">
        <!-- Mobile Cards (<768px) -->
        <div class="space-y-3 md:hidden">
          <ReportListCard
            v-for="d in dateWisePL"
            :key="d.date_period"
            :title="d.date_period"
            subtitle="Financial Period Performance"
            :status="d.is_profit ? 'PROFIT' : 'LOSS'"
            :statusType="d.is_profit ? 'success' : 'danger'"
            :metric="'₹' + formatCurrency(d.net_profit)"
            metricLabel="Net Profit/Loss"
            :details="[
              { label: 'Net Sales', value: '₹' + formatCurrency(d.net_sales) },
              { label: 'COGS', value: '₹' + formatCurrency(d.cogs) },
              { label: 'Gross Profit', value: '₹' + formatCurrency(d.gross_profit) },
              { label: 'Expenses', value: '₹' + formatCurrency(d.expenses) },
              { label: 'Margin %', value: d.margin_percentage + '%' }
            ]"
          />
        </div>

        <!-- Desktop Table (≥768px) -->
        <div class="hidden md:block bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 overflow-x-auto shadow-xs">
          <table class="w-full text-left text-xs font-sans whitespace-nowrap min-w-[750px]">
            <thead class="bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 text-[10px] uppercase font-black tracking-wider text-slate-500 dark:text-slate-400">
              <tr>
                <th class="py-3 px-4">Period / Date</th>
                <th class="py-3 px-4 text-right">Net Sales</th>
                <th class="py-3 px-4 text-right">COGS</th>
                <th class="py-3 px-4 text-right">Gross Profit</th>
                <th class="py-3 px-4 text-right">Expenses</th>
                <th class="py-3 px-4 text-right">Net Profit/Loss</th>
                <th class="py-3 px-4 text-center">Status</th>
                <th class="py-3 px-4 text-right">Margin %</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
              <tr v-for="d in dateWisePL" :key="d.date_period" :class="['hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors', d.is_profit ? '' : 'bg-red-50/30 dark:bg-red-950/10']">
                <td class="py-3 px-4 font-extrabold font-mono text-slate-900 dark:text-white">{{ d.date_period }}</td>
                <td class="py-3 px-4 text-right font-mono font-bold text-slate-900 dark:text-white">₹{{ formatCurrency(d.net_sales) }}</td>
                <td class="py-3 px-4 text-right font-mono text-slate-500">₹{{ formatCurrency(d.cogs) }}</td>
                <td :class="['py-3 px-4 text-right font-mono font-bold', d.gross_profit >= 0 ? 'text-emerald-600' : 'text-red-600']">
                  ₹{{ formatCurrency(d.gross_profit) }}
                </td>
                <td class="py-3 px-4 text-right font-mono text-red-600">₹{{ formatCurrency(d.expenses) }}</td>
                <td :class="['py-3 px-4 text-right font-mono font-black', d.net_profit >= 0 ? 'text-emerald-600' : 'text-red-600']">
                  ₹{{ formatCurrency(d.net_profit) }}
                </td>
                <td class="py-3 px-4 text-center">
                  <span :class="['px-2.5 py-1 text-[9px] font-black rounded-lg uppercase tracking-wider', d.is_profit ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300']">
                    {{ d.is_profit ? 'PROFIT' : 'LOSS' }}
                  </span>
                </td>
                <td class="py-3 px-4 text-right font-mono">
                  <span :class="['px-2 py-0.5 text-[9px] font-black rounded-md', d.margin_percentage >= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800']">
                    {{ d.margin_percentage }}%
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

    <!-- Reusable Mobile Bottom Sheet Filter -->
    <ReportFilterSheet
      v-model:show="showFilterSheet"
      :filters="filterState"
      :showGroupBy="activeTab === 'date_wise'"
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
  exportReportPdf('profit_loss', { ...filterState }, 'inline');
}

function handleDownloadPdf() {
  exportReportPdf('profit_loss', { ...filterState }, 'attachment');
}

const loading = ref(false);
const errorMsg = ref('');
const showFilterSheet = ref(false);
const activeTab = ref('summary');

const tabs = [
  { id: 'summary', label: 'P&L Statement', icon: '📋' },
  { id: 'date_wise', label: 'Date-Wise P&L', icon: '📅' },
];

const revenue = ref({ gross_sales: 0, discounts: 0, returns_refunds: 0, net_taxable_revenue: 0, gst_collected: 0 });
const cogs = ref({ net_cogs: 0 });
const profitability = ref({ gross_profit: 0, gross_margin_percentage: 0, operating_expenses: 0, net_profit: 0 });
const expenseBreakdown = ref([]);
const growth = ref({ net_revenue_growth_pct: 0, gross_profit_growth_pct: 0, net_profit_growth_pct: 0 });
const dateWisePL = ref([]);

const filterState = reactive({
  date_from: '',
  date_to: '',
  group_by: 'day',
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

async function loadAllData() {
  loading.value = true;
  errorMsg.value = '';
  try {
    const params = {};
    if (filterState.date_from) params.date_from = filterState.date_from;
    if (filterState.date_to) params.date_to = filterState.date_to;

    const plRes = await api.get('/financial-reports/profit-loss', { params });
    const pData = (plRes && plRes.data !== undefined) ? plRes.data : plRes;

    if (pData) {
      if (pData.revenue) revenue.value = pData.revenue;
      if (pData.cost_of_goods_sold) cogs.value = pData.cost_of_goods_sold;
      if (pData.profitability) profitability.value = pData.profitability;
      if (pData.expense_categories_breakdown) expenseBreakdown.value = pData.expense_categories_breakdown;
      if (pData.period_comparison) growth.value = pData.period_comparison;
    }

    if (activeTab.value === 'date_wise') {
      await fetchDateWisePL();
    }
  } catch (e) {
    console.error('Failed to load profit and loss report:', e);
    errorMsg.value = e?.message || 'Failed to connect to backend service. Please check network and retry.';
  } finally {
    loading.value = false;
  }
}

async function fetchDateWisePL() {
  const params = {
    group_by: filterState.group_by || 'day',
  };
  if (filterState.date_from) params.date_from = filterState.date_from;
  if (filterState.date_to) params.date_to = filterState.date_to;

  const res = await api.get('/reports/date-wise-profit-loss', { params });
  const payload = (res && res.data !== undefined) ? res.data : res;
  if (payload) {
    dateWisePL.value = payload.items || (Array.isArray(payload) ? payload : []);
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
    { title: 'Gross Revenue', value: '₹' + formatCurrency(revenue.value.gross_sales) },
    { title: 'Cost of Goods (COGS)', value: '₹' + formatCurrency(cogs.value.net_cogs) },
    { title: 'Gross Profit', value: '₹' + formatCurrency(profitability.value.gross_profit), isLoss: profitability.value.gross_profit < 0 },
    { title: 'Net Profit', value: '₹' + formatCurrency(profitability.value.net_profit), isLoss: profitability.value.net_profit < 0 },
  ];

  if (activeTab.value === 'date_wise') {
    const cols = [
      { label: 'Period / Date', field: 'date_period' },
      { label: 'Net Sales', value: r => '₹' + formatCurrency(r.net_sales), align: 'right' },
      { label: 'COGS', value: r => '₹' + formatCurrency(r.cogs), align: 'right' },
      { label: 'Gross Profit', value: r => '₹' + formatCurrency(r.gross_profit), align: 'right' },
      { label: 'Expenses', value: r => '₹' + formatCurrency(r.expenses), align: 'right' },
      { label: 'Net Profit', value: r => '₹' + formatCurrency(r.net_profit), align: 'right' },
      { label: 'Status', field: 'is_profit', isStatus: true },
      { label: 'Margin %', value: r => r.margin_percentage + '%', align: 'right' },
    ];
    exportToPdf('Date-Wise Profit & Loss Statement', filterDesc, kpiCards, cols, dateWisePL.value);
  } else {
    const cols = [
      { label: 'Financial Metric', field: 'metric' },
      { label: 'Amount', field: 'amount', align: 'right' },
    ];
    const summaryRows = [
      { metric: 'Gross Sales Revenue', amount: '₹' + formatCurrency(revenue.value.gross_sales) },
      { metric: 'Discounts', amount: '₹' + formatCurrency(revenue.value.discounts) },
      { metric: 'Returns & Refunds', amount: '₹' + formatCurrency(revenue.value.returns_refunds) },
      { metric: 'Net Sales Revenue', amount: '₹' + formatCurrency(revenue.value.net_taxable_revenue) },
      { metric: 'Cost of Goods Sold (COGS)', amount: '₹' + formatCurrency(cogs.value.net_cogs) },
      { metric: 'Gross Operating Profit', amount: '₹' + formatCurrency(profitability.value.gross_profit) },
      { metric: 'Operating Expenses', amount: '₹' + formatCurrency(profitability.value.operating_expenses) },
      { metric: 'Net Profit / Loss', amount: '₹' + formatCurrency(profitability.value.net_profit) },
    ];
    exportToPdf('Profit & Loss Summary Statement', filterDesc, kpiCards, cols, summaryRows);
  }
}

// EXPORT CSV HANDLER
function handleExportCsv() {
  if (activeTab.value === 'date_wise') {
    const cols = [
      { label: 'Period / Date', field: 'date_period' },
      { label: 'Net Sales', field: 'net_sales' },
      { label: 'COGS', field: 'cogs' },
      { label: 'Gross Profit', field: 'gross_profit' },
      { label: 'Operating Expenses', field: 'expenses' },
      { label: 'Net Profit', field: 'net_profit' },
      { label: 'Is Profitable', value: r => r.is_profit ? 'PROFIT' : 'LOSS' },
      { label: 'Margin %', field: 'margin_percentage' },
    ];
    exportToCsv('date_wise_profit_loss_report', cols, dateWisePL.value);
  } else {
    const cols = [
      { label: 'Financial Metric', field: 'metric' },
      { label: 'Amount', field: 'amount' },
    ];
    const summaryRows = [
      { metric: 'Gross Sales Revenue', amount: revenue.value.gross_sales },
      { metric: 'Discounts', amount: revenue.value.discounts },
      { metric: 'Returns & Refunds', amount: revenue.value.returns_refunds },
      { metric: 'Net Sales Revenue', amount: revenue.value.net_taxable_revenue },
      { metric: 'Cost of Goods Sold (COGS)', amount: cogs.value.net_cogs },
      { metric: 'Gross Operating Profit', amount: profitability.value.gross_profit },
      { metric: 'Operating Expenses', amount: profitability.value.operating_expenses },
      { metric: 'Net Profit / Loss', amount: profitability.value.net_profit },
    ];
    exportToCsv('profit_loss_summary_report', cols, summaryRows);
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
