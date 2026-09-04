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
            <span>📦</span>
            <span>Inventory & Stock Analytics</span>
          </h2>
          <span class="px-2.5 py-0.5 text-[10px] font-extrabold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-full border border-slate-200 dark:border-slate-700">
            📅 {{ dateRangeBadge }}
          </span>
        </div>
        <p class="text-xs text-slate-500 font-medium mt-0.5">Valuation, low stock alerts, stock velocity (Fast/Slow/Dead stock), adjustments & transfers</p>
      </div>

      <div class="flex items-center gap-2">
        <div class="relative flex-1 sm:w-64">
          <span class="absolute left-3 top-2.5 text-xs text-slate-400">🔍</span>
          <input
            type="text"
            v-model="searchQuery"
            @input="debounceSearch"
            placeholder="Search SKU, article, product..."
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

    <!-- 9 Sub-Report Tab Buttons -->
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

    <!-- KPI Summary Cards Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <ReportKpiCard
        title="Total Stock Qty"
        :value="summary.total_stock_quantity"
        icon="📦"
        accentColor="indigo"
        subtext="Available units in store"
      />
      <ReportKpiCard
        title="Inventory Cost Value"
        :value="summary.total_inventory_cost_value"
        :isCurrency="true"
        icon="💰"
        accentColor="emerald"
        subtext="Purchase cost valuation"
      />
      <ReportKpiCard
        title="Total MRP Value"
        :value="summary.total_inventory_mrp_value"
        :isCurrency="true"
        icon="🏷️"
        accentColor="amber"
        subtext="Maximum retail price value"
      />
      <ReportKpiCard
        title="Total Selling Value"
        :value="summary.total_inventory_selling_value"
        :isCurrency="true"
        icon="📈"
        accentColor="slate"
        subtext="Target revenue value"
      />
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="p-12 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
      <div class="inline-block animate-spin text-2xl text-red-600">⌛</div>
      <p class="text-xs font-extrabold text-slate-500">Loading stock report data...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="items.length === 0" class="p-12 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
      <div class="text-3xl text-slate-400">📦</div>
      <h3 class="font-black text-sm text-slate-700 dark:text-slate-300">No Stock Records Found</h3>
      <p class="text-xs text-slate-400 font-medium">Try adjusting search query or date filter.</p>
    </div>

    <!-- Inventory Results List -->
    <div v-else class="space-y-3">
      <!-- Mobile Cards (<768px) -->
      <div class="space-y-3 md:hidden">
        <ReportListCard
          v-for="item in items"
          :key="item.id || item.sku"
          :title="item.product_name || item.sku || item.reference_number"
          :subtitle="'SKU: ' + (item.sku || 'N/A') + ' • Article: ' + (item.article_number || 'N/A')"
          :status="(item.stock_quantity || item.quantity) <= 5 ? 'LOW STOCK' : 'IN STOCK'"
          :statusType="(item.stock_quantity || item.quantity) <= 5 ? 'warning' : 'success'"
          :metric="'₹' + formatCurrency(item.total_cost_value || ((item.stock_quantity || 0) * (item.cost_price || 0)) || 0)"
          metricLabel="Cost Valuation"
          :details="[
            { label: 'Category', value: item.category_name || 'Footwear' },
            { label: 'Color / Size', value: (item.color || '') + ' / ' + (item.size || '') },
            { label: 'Stock Qty', value: (item.stock_quantity ?? item.quantity ?? 0) + ' pairs' },
            { label: 'Cost Price', value: '₹' + formatCurrency(item.cost_price || 0) }
          ]"
        >
          <template #details>
            <div class="space-y-1 text-xs pt-1">
              <div class="flex justify-between items-center bg-slate-50 dark:bg-slate-950 p-2 rounded-lg text-[11px]">
                <span class="text-slate-500 font-bold">MRP:</span>
                <span class="font-black font-mono">₹{{ formatCurrency(item.mrp || 0) }}</span>
              </div>
              <div class="flex justify-between items-center bg-slate-50 dark:bg-slate-950 p-2 rounded-lg text-[11px]">
                <span class="text-slate-500 font-bold">Selling Price:</span>
                <span class="font-black font-mono text-emerald-600">₹{{ formatCurrency(item.selling_price || 0) }}</span>
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
              <th class="py-3 px-4">SKU / Article</th>
              <th class="py-3 px-4">Product Name</th>
              <th class="py-3 px-4">Color / Size</th>
              <th class="py-3 px-4 text-center">Stock Qty</th>
              <th class="py-3 px-4 text-right">Cost Price</th>
              <th class="py-3 px-4 text-right">Selling Price</th>
              <th class="py-3 px-4 text-right">Total Cost Val</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
            <tr v-for="item in items" :key="item.id || item.sku" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
              <td class="py-3 px-4 font-mono font-bold text-slate-900 dark:text-white">
                <div>{{ item.sku }}</div>
                <div class="text-[10px] text-slate-400">Art: {{ item.article_number }}</div>
              </td>
              <td class="py-3 px-4 font-bold text-slate-800 dark:text-slate-200">{{ item.product_name }}</td>
              <td class="py-3 px-4 text-slate-500">{{ item.color }} / {{ item.size }}</td>
              <td class="py-3 px-4 text-center font-bold">
                <span :class="[
                  'px-2 py-0.5 rounded font-mono',
                  (item.stock_quantity || 0) <= 5 ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 'text-slate-900 dark:text-white'
                ]">
                  {{ item.stock_quantity ?? item.quantity ?? 0 }}
                </span>
              </td>
              <td class="py-3 px-4 text-right font-mono text-slate-500">₹{{ formatCurrency(item.cost_price || 0) }}</td>
              <td class="py-3 px-4 text-right font-mono font-bold text-emerald-600">₹{{ formatCurrency(item.selling_price || 0) }}</td>
              <td class="py-3 px-4 text-right font-mono font-bold text-slate-900 dark:text-white">₹{{ formatCurrency(item.total_cost_value || ((item.stock_quantity || 0) * (item.cost_price || 0))) }}</td>
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
    'stock-valuation': 'inventory_valuation',
    'low-stock': 'inventory_low_stock',
    'fast-moving': 'inventory_fast_moving',
    'slow-moving': 'inventory_slow_moving',
    'dead-stock': 'inventory_dead_stock',
    'movements': 'inventory_movements',
    'adjustments': 'inventory_adjustments',
    'transfers': 'inventory_transfers',
    'damage': 'inventory_damage',
  };
  return typeMap[activeTab.value] || 'inventory_valuation';
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
const activeTab = ref('stock-valuation');

const summary = ref({
  total_items_count: 0,
  total_stock_quantity: 0,
  total_inventory_cost_value: 0,
  total_inventory_mrp_value: 0,
  total_inventory_selling_value: 0,
});

const items = ref([]);
const filterState = reactive({
  date_from: '',
  date_to: '',
});

const subTabs = [
  { id: 'stock-valuation', name: 'Stock Valuation', icon: '💰' },
  { id: 'low-stock', name: 'Low Stock', icon: '⚠️' },
  { id: 'fast-moving', name: 'Fast Moving', icon: '🚀' },
  { id: 'slow-moving', name: 'Slow Moving', icon: '🐢' },
  { id: 'dead-stock', name: 'Dead Stock', icon: '📦' },
  { id: 'movements', name: 'Movements Ledger', icon: '📈' },
  { id: 'adjustments', name: 'Adjustments', icon: '⚙️' },
  { id: 'transfers', name: 'Transfers', icon: '🚚' },
  { id: 'damage', name: 'Damage / Out', icon: '🗑️' },
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
  return 'Current Stock';
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
    const params = {};
    if (filterState.date_from) params.date_from = filterState.date_from;
    if (filterState.date_to) params.date_to = filterState.date_to;
    if (searchQuery.value) params.search = searchQuery.value;

    let endpoint = '/reports/stock-valuation';
    if (activeTab.value === 'low-stock') endpoint = '/inventory/low-stock';
    else if (activeTab.value === 'fast-moving') endpoint = '/reports/stock-valuation';
    else if (activeTab.value === 'slow-moving') endpoint = '/reports/stock-valuation';
    else if (activeTab.value === 'dead-stock') endpoint = '/reports/stock-valuation';
    else if (activeTab.value === 'movements') endpoint = '/inventory/movements';
    else if (activeTab.value === 'adjustments') endpoint = '/inventory/adjustments';
    else if (activeTab.value === 'transfers') endpoint = '/inventory/transfers';
    else if (activeTab.value === 'damage') endpoint = '/inventory/damage';

    const res = await api.get(endpoint, { params });
    if (res.data) {
      const payload = res.data.data || res.data;

      if (payload.summary) {
        summary.value = payload.summary;
      }
      let rawItems = [];
      if (payload.items) {
        rawItems = payload.items;
      } else if (Array.isArray(payload)) {
        rawItems = payload;
      } else {
        rawItems = payload.data || [];
      }

      if (activeTab.value === 'fast-moving') {
        items.value = rawItems.filter(i => (i.stock_quantity || i.quantity || 0) > 10);
      } else if (activeTab.value === 'slow-moving') {
        items.value = rawItems.filter(i => (i.stock_quantity || i.quantity || 0) > 0 && (i.stock_quantity || i.quantity || 0) <= 5);
      } else if (activeTab.value === 'dead-stock') {
        items.value = rawItems.filter(i => (i.stock_quantity || i.quantity || 0) === 0);
      } else {
        items.value = rawItems;
      }
    }
  } catch (e) {
    console.error('Failed to load inventory report:', e);
    errorMsg.value = 'Failed to connect to backend service. Please check network and retry.';
  } finally {
    loading.value = false;
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
    { title: 'Total Stock Units', value: summary.value.total_stock_quantity },
    { title: 'Inventory Cost Value', value: '₹' + formatCurrency(summary.value.total_inventory_cost_value) },
    { title: 'Total MRP Value', value: '₹' + formatCurrency(summary.value.total_inventory_mrp_value) },
    { title: 'Selling Revenue Value', value: '₹' + formatCurrency(summary.value.total_inventory_selling_value) },
  ];

  const cols = [
    { label: 'SKU', field: 'sku' },
    { label: 'Article No', field: 'article_number' },
    { label: 'Product Name', field: 'product_name' },
    { label: 'Color / Size', value: r => `${r.color || ''} / ${r.size || ''}` },
    { label: 'Stock Qty', value: r => r.stock_quantity ?? r.quantity ?? 0, align: 'center' },
    { label: 'Cost Price', value: r => '₹' + formatCurrency(r.cost_price || 0), align: 'right' },
    { label: 'Selling Price', value: r => '₹' + formatCurrency(r.selling_price || 0), align: 'right' },
    { label: 'Total Valuation', value: r => '₹' + formatCurrency(r.total_cost_value || ((r.stock_quantity || 0) * (r.cost_price || 0))), align: 'right' },
  ];
  exportToPdf(`Inventory ${activeTab.value.toUpperCase().replace('-', ' ')} Report`, filterDesc, kpiCards, cols, items.value);
}

// EXPORT CSV HANDLER
function handleExportCsv() {
  const cols = [
    { label: 'SKU', field: 'sku' },
    { label: 'Article Number', field: 'article_number' },
    { label: 'Product Name', field: 'product_name' },
    { label: 'Color', field: 'color' },
    { label: 'Size', field: 'size' },
    { label: 'Stock Quantity', value: r => r.stock_quantity ?? r.quantity ?? 0 },
    { label: 'Cost Price', field: 'cost_price' },
    { label: 'Selling Price', field: 'selling_price' },
    { label: 'Cost Valuation', value: r => r.total_cost_value || ((r.stock_quantity || 0) * (r.cost_price || 0)) },
  ];
  exportToCsv(`inventory_${activeTab.value}_report`, cols, items.value);
}

function getFilterDesc() {
  const parts = [];
  if (filterState.date_from || filterState.date_to) {
    parts.push(`Date: ${filterState.date_from || 'Start'} to ${filterState.date_to || 'Today'}`);
  } else {
    parts.push('Date: Current Stock');
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

