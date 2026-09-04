<template>
  <div class="space-y-6 pb-12 antialiased font-sans max-w-full">
    <!-- 1. Header Row with Filter Toolbar -->
    <DashboardHeader
      :user-name="authStore.user?.name || 'Admin User'"
      :refreshing="loading"
      @refresh="fetchDashboardData"
      @filter-change="handleFilterChange"
    />

    <!-- 2. Top Row: 6 KPI Cards (Fluid grid) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6 gap-4">
      <!-- Card 1: Net Revenue -->
      <KpiCard
        title="Net Sales Revenue"
        :value="'₹' + formatAmount(kpis.net_billed_sales_revenue ?? kpis.gross_billed_sales_revenue ?? 0)"
        icon="🛍️"
        icon-bg-class="bg-red-50 text-red-600 border border-red-100"
        :trend-text="formatGrowthText(kpis.period_comparison?.net_revenue_growth_pct)"
        :trend-color-class="getGrowthColorClass(kpis.period_comparison?.net_revenue_growth_pct)"
        sparkline-color="#dc2626"
        :sparkline-points="salesTrendPoints"
      />

      <!-- Card 2: Completed Orders -->
      <KpiCard
        title="Completed Orders"
        :value="kpis.completed_sales_count ?? 0"
        icon="🛒"
        icon-bg-class="bg-blue-50 text-blue-600 border border-blue-100"
        :trend-text="formatGrowthText(kpis.period_comparison?.sales_count_growth_pct)"
        :trend-color-class="getGrowthColorClass(kpis.period_comparison?.sales_count_growth_pct)"
        sparkline-color="#2563eb"
        :sparkline-points="salesOrderTrendPoints"
      />

      <!-- Card 3: Gross Profit -->
      <KpiCard
        title="Gross Profit"
        :value="'₹' + formatAmount(kpis.gross_profit ?? 0)"
        icon="💹"
        icon-bg-class="bg-emerald-50 text-emerald-600 border border-emerald-100"
        :supporting-text="(kpis.gross_margin_percentage ?? 0) + '% Margin'"
        :trend-text="formatGrowthText(kpis.period_comparison?.gross_profit_growth_pct)"
        :trend-color-class="getGrowthColorClass(kpis.period_comparison?.gross_profit_growth_pct)"
        sparkline-color="#16a34a"
        :sparkline-points="profitTrendPoints"
      />

      <!-- Card 4: Active Customers -->
      <KpiCard
        title="Active Customers"
        :value="customerKpi.active_purchasing_customers ?? kpis.active_customers_count ?? 0"
        icon="👥"
        icon-bg-class="bg-purple-50 text-purple-600 border border-purple-100"
        :supporting-text="'+' + (customerKpi.new_customers_count ?? 0) + ' new'"
        trend-text="Customer Base"
        trend-color-class="text-purple-600"
        sparkline-color="#9333ea"
        :sparkline-points="customerTrendPoints"
      />

      <!-- Card 5: Inventory Valuation -->
      <KpiCard
        title="Inventory Valuation"
        :value="'₹' + formatAmount(inventoryKpi.inventory_valuation_cost ?? 0)"
        icon="📦"
        icon-bg-class="bg-amber-50 text-amber-600 border border-amber-100"
        :supporting-text="'Selling: ₹' + formatAmount(inventoryKpi.inventory_valuation_selling ?? 0)"
        trend-text="Stock Asset"
        trend-color-class="text-emerald-600"
        sparkline-color="#ca8a04"
        :sparkline-points="inventoryTrendPoints"
      />

      <!-- Card 6: Low Stock Items -->
      <KpiCard
        title="Low Stock Items"
        :value="inventoryKpi.low_stock_count ?? 0"
        icon="⚠️"
        icon-bg-class="bg-red-100 text-red-700 border border-red-200"
        :supporting-text="(inventoryKpi.out_of_stock_count ?? 0) + ' Out of Stock'"
        trend-text="Stock Alert"
        trend-color-class="text-red-600"
        sparkline-color="#dc2626"
        :sparkline-points="lowStockTrendPoints"
      />
    </div>

    <!-- 3. Middle Row: Sales Overview Chart & Store Performance Table -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <SalesOverview
        class="lg:col-span-7"
        :sales-trend="salesTrendList"
        @range-change="handleRangeChange"
      />
      <StorePerformance
        class="lg:col-span-5"
        :stores="storeRankings"
      />
    </div>

    <!-- 4. Third Row: Low Stock Alerts, Recent Sales, Top Products -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-6">
      <LowStockAlerts class="lg:col-span-4" :items="lowStockItems" />
      <RecentSales class="lg:col-span-5" :sales="recentSalesList" />
      <TopProducts class="md:col-span-2 lg:col-span-3" :products="topProductsList" />
    </div>

    <!-- 5. Fourth Row: Recent Activities Log -->
    <RecentActivities :activities="recentActivitiesList" />

    <!-- 6. Bottom Summary & Quick Actions -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <FinancialSummary :net-revenue="formatAmount(kpis.net_billed_sales_revenue ?? kpis.gross_billed_sales_revenue ?? 0)" />
      <InventorySummary :total-skus="formatAmount(inventoryKpi.total_inventory_units ?? 0)" />
      <PosStatus :open-registers="posKpi.open_registers ?? posKpi.active_pos_sessions ?? 0" />
      <QuickActions />
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useAuthStore } from '../../stores/authStore';
import api from '../../services/api';

import DashboardHeader from '../../components/dashboard/DashboardHeader.vue';
import KpiCard from '../../components/dashboard/KpiCard.vue';
import SalesOverview from '../../components/dashboard/SalesOverview.vue';
import StorePerformance from '../../components/dashboard/StorePerformance.vue';
import LowStockAlerts from '../../components/dashboard/LowStockAlerts.vue';
import RecentSales from '../../components/dashboard/RecentSales.vue';
import TopProducts from '../../components/dashboard/TopProducts.vue';
import RecentActivities from '../../components/dashboard/RecentActivities.vue';
import FinancialSummary from '../../components/dashboard/FinancialSummary.vue';
import InventorySummary from '../../components/dashboard/InventorySummary.vue';
import PosStatus from '../../components/dashboard/PosStatus.vue';
import QuickActions from '../../components/dashboard/QuickActions.vue';

const authStore = useAuthStore();
const loading = ref(false);

const kpis = ref({});
const inventoryKpi = ref({});
const posKpi = ref({});
const customerKpi = ref({});
const storeRankings = ref([]);
const salesTrendList = ref([]);
const topProductsList = ref([]);
const lowStockItems = ref([]);
const recentSalesList = ref([]);
const recentActivitiesList = ref([]);

const filterState = reactive({
  store_id: null,
  period: 'current_month',
  start_date: '',
  end_date: '',
});

function formatAmount(val) {
  const num = Number(val) || 0;
  return num.toLocaleString('en-IN');
}

function formatGrowthText(pct) {
  if (pct === undefined || pct === null) return '0%';
  const num = Number(pct) || 0;
  return num >= 0 ? `▲ ${num.toFixed(1)}%` : `▼ ${Math.abs(num).toFixed(1)}%`;
}

function getGrowthColorClass(pct) {
  if (pct === undefined || pct === null) return 'text-slate-400';
  const num = Number(pct) || 0;
  return num >= 0 ? 'text-emerald-600' : 'text-red-600';
}

const salesTrendPoints = computed(() => {
  if (!salesTrendList.value || salesTrendList.value.length === 0) return [0, 0, 0, 0];
  return salesTrendList.value.map(item => Number(item.net_revenue || item.gross_revenue || 0));
});

const salesOrderTrendPoints = computed(() => {
  if (!salesTrendList.value || salesTrendList.value.length === 0) return [0, 0, 0, 0];
  return salesTrendList.value.map(item => Number(item.completed_orders || 0));
});

const profitTrendPoints = computed(() => {
  const val = Number(kpis.value.gross_profit || 0);
  return [val * 0.7, val * 0.8, val * 0.9, val];
});

const customerTrendPoints = computed(() => {
  const val = Number(customerKpi.value.active_purchasing_customers || 0);
  return [val, val, val, val];
});

const inventoryTrendPoints = computed(() => {
  const val = Number(inventoryKpi.value.inventory_valuation_cost || 0);
  return [val, val, val, val];
});

const lowStockTrendPoints = computed(() => {
  const val = Number(inventoryKpi.value.low_stock_count || 0);
  return [val, val, val, val];
});

function getParams() {
  const params = {};
  if (filterState.store_id) params.store_id = filterState.store_id;
  if (filterState.period) params.period = filterState.period;
  if (filterState.period === 'custom') {
    if (filterState.start_date) params.start_date = filterState.start_date;
    if (filterState.end_date) params.end_date = filterState.end_date;
  }
  return params;
}

function handleFilterChange(newFilters) {
  Object.assign(filterState, newFilters);
  fetchDashboardData();
}

function handleRangeChange(preset) {
  filterState.period = preset;
  fetchDashboardData();
}

async function fetchDashboardData() {
  loading.value = true;
  const params = getParams();

  try {
    const [execRes, invRes, posRes, custRes, storeRes, trendRes, prodRes, salesRes, stocksRes] = await Promise.allSettled([
      api.get('/dashboard/executive-kpi', { params }),
      api.get('/dashboard/inventory-kpi', { params }),
      api.get('/dashboard/pos-register-kpi', { params }),
      api.get('/dashboard/customer-kpi', { params }),
      api.get('/dashboard/store-performance', { params }),
      api.get('/dashboard/sales-trend', { params }),
      api.get('/dashboard/product-performance', { params }),
      api.get('/pos/sales', { params: { ...params, per_page: 5 } }),
      api.get('/reports/stock-valuation', { params }),
    ]);

    if (execRes.status === 'fulfilled') {
      const payload = execRes.value.data || execRes.value;
      kpis.value = payload.data || payload || {};
    }
    if (invRes.status === 'fulfilled') {
      const payload = invRes.value.data || invRes.value;
      inventoryKpi.value = payload.data || payload || {};
    }
    if (posRes.status === 'fulfilled') {
      const payload = posRes.value.data || posRes.value;
      posKpi.value = payload.data || payload || {};
    }
    if (custRes.status === 'fulfilled') {
      const payload = custRes.value.data || custRes.value;
      customerKpi.value = payload.data || payload || {};
    }
    if (storeRes.status === 'fulfilled') {
      const payload = storeRes.value.data || storeRes.value;
      const list = Array.isArray(payload) ? payload : (payload.data || []);
      storeRankings.value = list.map(st => ({
        name: st.store_name || st.name,
        sales: formatAmount(st.net_sales || st.gross_sales || 0),
        orders: st.completed_orders || st.orders || 0,
        profit: formatAmount(st.gross_profit || st.profit || 0),
        targetPct: st.gross_margin_percentage || st.targetPct || 0,
      }));
    }
    if (trendRes.status === 'fulfilled') {
      const payload = trendRes.value.data || trendRes.value;
      salesTrendList.value = Array.isArray(payload) ? payload : (payload.data || []);
    }
    if (prodRes.status === 'fulfilled') {
      const payload = prodRes.value.data || prodRes.value;
      const data = payload.data || payload || {};
      const prods = data.top_products || data.top_skus || [];
      topProductsList.value = prods.map(p => ({
        icon: '👟',
        name: p.product_name || p.sku,
        sku: p.sku || p.article_number || 'N/A',
        units: p.quantity_sold || p.total_qty || 0,
        revenue: formatAmount(p.net_revenue || 0),
      }));
    }
    if (salesRes.status === 'fulfilled') {
      const payload = salesRes.value.data || salesRes.value;
      const list = Array.isArray(payload) ? payload : (payload.items || payload.data || []);
      recentSalesList.value = list.map(s => ({
        invoice: s.invoice_number,
        customer: s.customer ? s.customer.name : 'Walk-in Customer',
        store: s.store ? s.store.name : 'Main Store',
        items: s.items ? s.items.length : 1,
        amount: formatAmount(s.grand_total || 0),
        payment: s.payment_status ? s.payment_status.toUpperCase() : 'COMPLETED',
        time: s.created_at ? new Date(s.created_at).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' }) : '',
      }));
    }

    if (stocksRes.status === 'fulfilled') {
      const payload = stocksRes.value.data || stocksRes.value;
      const items = (payload.data ? payload.data.items : payload.items) || [];
      const lowList = items.filter(it => it.stock_quantity <= 10).slice(0, 4);
      lowStockItems.value = lowList.map(it => ({
        icon: '👟',
        name: it.product_name,
        sku: it.sku,
        store: it.store_name || 'Main Outlet',
        stock: it.stock_quantity,
        min: 10,
      }));
    }

    // Build Recent Activities from real data
    const activities = [];
    if (recentSalesList.value.length > 0) {
      recentSalesList.value.slice(0, 3).forEach(s => {
        activities.push({
          type: 'sale',
          title: `Sales Invoice ${s.invoice} created`,
          detail: `Customer: ${s.customer} | ₹${s.amount}`,
          location: s.store,
          time: s.time || 'Today',
        });
      });
    }
    recentActivitiesList.value = activities;

  } catch (err) {
    console.error('Failed to load dashboard metrics:', err);
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  fetchDashboardData();
});
</script>
