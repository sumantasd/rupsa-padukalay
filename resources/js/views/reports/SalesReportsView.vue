<template>
  <div class="space-y-4 antialiased font-sans pb-24 max-w-full overflow-x-hidden">
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
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
          <h2 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
            <span>📊</span>
            <span>Sales & Revenue Analytics</span>
          </h2>
          <p class="text-xs text-slate-500 font-medium">Overview billing, item-level sales breakdown, and date-wise trends</p>
        </div>

        <div class="flex items-center gap-2">
          <div class="relative flex-1 sm:w-64">
            <span class="absolute left-3 top-2.5 text-xs text-slate-400">🔍</span>
            <input
              type="text"
              v-model="searchQuery"
              @input="debounceSearch"
              placeholder="Search SKU, invoice, product..."
              class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl pl-9 pr-3 py-2 text-xs font-bold text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-red-500"
            />
          </div>
        </div>
      </div>

      <!-- Selected Date Range & Active Filter Badge -->
      <div class="flex items-center justify-between text-[11px] pt-1 border-t border-slate-100 dark:border-slate-800">
        <span class="font-extrabold text-slate-500 flex items-center gap-1.5">
          <span>📅</span>
          <span>Period: <strong class="text-slate-900 dark:text-white">{{ getFilterDesc() }}</strong></span>
        </span>
        <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-300 font-extrabold text-[10px]">
          Single Store Global
        </span>
      </div>

      <!-- Sub-tabs Pills & EXPORT PDF Action Button -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-1.5 bg-slate-100 dark:bg-slate-950 rounded-xl">
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            @click="activeTab = tab.id"
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

        <button
          @click="handleDownloadPdf"
          class="flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-lg bg-red-600 hover:bg-red-700 active:scale-95 text-white font-black text-xs shadow-md shadow-red-600/30 transition-all cursor-pointer whitespace-nowrap min-h-[36px]"
          title="Export Currently Selected Report to PDF"
        >
          <span>📄</span>
          <span>EXPORT PDF</span>
        </button>
      </div>
    </div>

    <!-- API Error State with Retry -->
    <div v-if="errorMessage" class="p-6 text-center bg-red-50 dark:bg-red-950/20 rounded-2xl border border-red-200 dark:border-red-900/40 space-y-3">
      <div class="text-2xl text-red-600">⚠️</div>
      <h3 class="font-black text-sm text-red-800 dark:text-red-300">Unable to Load Sales Report</h3>
      <p class="text-xs text-red-600 dark:text-red-400 font-medium">{{ errorMessage }}</p>
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
      <p class="text-xs font-extrabold text-slate-500">Fetching sales analytics...</p>
    </div>

    <div v-else class="space-y-4">
      <!-- 8 Mobile KPI Summary Cards Grid -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <ReportKpiCard
          title="Total Sales"
          :value="summary.total_grand_total"
          :isCurrency="true"
          icon="💰"
          accentColor="emerald"
          subtext="Gross billed amount"
        />
        <ReportKpiCard
          title="No. of Invoices"
          :value="summary.total_sales_count"
          icon="🧾"
          accentColor="indigo"
          subtext="Total completed bills"
        />
        <ReportKpiCard
          title="Total Items Sold"
          :value="totalItemsSold"
          icon="👟"
          accentColor="slate"
          subtext="Line units sold"
        />
        <ReportKpiCard
          title="Avg Invoice Value"
          :value="avgInvoiceValue"
          :isCurrency="true"
          icon="📈"
          accentColor="amber"
          subtext="Per sale average"
        />
        <ReportKpiCard
          title="Cash Sales"
          :value="summary.total_paid_amount"
          :isCurrency="true"
          icon="💵"
          accentColor="emerald"
          subtext="Collected payments"
        />
        <ReportKpiCard
          title="Credit / Due Sales"
          :value="summary.total_outstanding_amount"
          :isCurrency="true"
          icon="⏳"
          accentColor="amber"
          subtext="Unpaid balance"
        />
        <ReportKpiCard
          title="Sales Returns"
          :value="summary.total_refund_amount"
          :isCurrency="true"
          icon="↩️"
          accentColor="red"
          subtext="Refunded transactions"
        />
        <ReportKpiCard
          title="Net Sales"
          :value="summary.net_revenue"
          :isCurrency="true"
          icon="🎯"
          accentColor="indigo"
          subtext="After returns & discounts"
        />
      </div>

      <!-- TAB 1: OVERVIEW (INVOICES LIST) -->
      <div v-if="activeTab === 'overview'" class="space-y-3">
        <div v-if="invoices.length === 0" class="p-12 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
          <div class="text-3xl text-slate-400">🧾</div>
          <h3 class="font-black text-sm text-slate-700 dark:text-slate-300">No Sales Invoices Found</h3>
          <p class="text-xs text-slate-400 font-medium">No sales recorded for {{ getFilterDesc() }}.</p>
        </div>

      <div v-else class="space-y-3">
        <!-- Mobile Cards (<768px) -->
        <div class="space-y-3 md:hidden">
          <ReportListCard
            v-for="inv in invoices"
            :key="inv.id"
            :title="inv.invoice_number"
            :subtitle="inv.created_at ? formatDate(inv.created_at) : ''"
            :status="inv.payment_status || 'completed'"
            :statusType="inv.payment_status === 'paid' ? 'success' : 'warning'"
            :metric="'₹' + formatCurrency(inv.grand_total)"
            metricLabel="Invoice Amount"
            :details="[
              { label: 'Customer', value: inv.customer ? inv.customer.name : 'Walk-in Customer' },
              { label: 'Items Count', value: inv.items_count || (inv.items ? inv.items.length : '1') },
              { label: 'Paid Amount', value: '₹' + formatCurrency(inv.paid_amount) },
              { label: 'Due Balance', value: '₹' + formatCurrency(inv.due_amount || 0) }
            ]"
          >
            <template #details>
              <div class="space-y-2 text-xs">
                <div v-if="inv.items && inv.items.length" class="space-y-1">
                  <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Items Purchased:</span>
                  <div v-for="item in inv.items" :key="item.id" class="flex justify-between items-center bg-slate-50 dark:bg-slate-950 p-2 rounded-lg text-[11px]">
                    <div>
                      <span class="font-bold text-slate-900 dark:text-white block">{{ item.product_name_snapshot || item.sku_snapshot }}</span>
                      <span class="text-slate-400 text-[10px]">Qty: {{ item.quantity }} × ₹{{ item.unit_price }}</span>
                    </div>
                    <span class="font-bold font-mono">₹{{ item.subtotal }}</span>
                  </div>
                </div>
                <div class="flex justify-between items-center text-[11px] pt-1 text-slate-500 font-medium">
                  <span>Cashier: {{ inv.created_by_user ? inv.created_by_user.name : 'System' }}</span>
                  <span>Store: {{ inv.store ? inv.store.name : 'Main Store' }}</span>
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
                <th class="py-3 px-4">Invoice No</th>
                <th class="py-3 px-4">Date</th>
                <th class="py-3 px-4">Customer</th>
                <th class="py-3 px-4 text-center">Status</th>
                <th class="py-3 px-4 text-right">Grand Total</th>
                <th class="py-3 px-4 text-right">Paid</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
              <tr v-for="inv in invoices" :key="inv.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                <td class="py-3 px-4 font-mono font-bold text-slate-900 dark:text-white">{{ inv.invoice_number }}</td>
                <td class="py-3 px-4 text-slate-500">{{ formatDate(inv.created_at) }}</td>
                <td class="py-3 px-4 font-bold text-slate-800 dark:text-slate-200">{{ inv.customer ? inv.customer.name : 'Walk-in Customer' }}</td>
                <td class="py-3 px-4 text-center">
                  <span :class="[
                    'px-2 py-0.5 text-[9px] font-black rounded-md uppercase',
                    inv.payment_status === 'paid' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                  ]">
                    {{ inv.payment_status || 'paid' }}
                  </span>
                </td>
                <td class="py-3 px-4 text-right font-mono font-bold text-slate-900 dark:text-white">₹{{ formatCurrency(inv.grand_total) }}</td>
                <td class="py-3 px-4 text-right font-mono text-emerald-600 font-bold">₹{{ formatCurrency(inv.paid_amount) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Control -->
        <div v-if="pagination.last_page > 1" class="flex items-center justify-between p-4 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 text-xs">
          <span class="text-slate-500 font-bold">
            Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} records)
          </span>
          <div class="flex items-center gap-2">
            <button
              @click="changePage(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1"
              class="px-3 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 disabled:opacity-40 font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition-all cursor-pointer"
            >
              ◀ Prev
            </button>
            <button
              @click="changePage(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page"
              class="px-3 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 disabled:opacity-40 font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition-all cursor-pointer"
            >
              Next ▶
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 2: ITEM & SIZE-WISE SALES -->
    <div v-else-if="activeTab === 'item_wise'" class="space-y-3">
      <!-- Item & Size-Wise Sales Action Header Banner -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-xs">
        <div>
          <h3 class="font-black text-sm text-slate-900 dark:text-white flex items-center gap-2">
            <span>📦</span>
            <span>Item & Size-wise Sales Breakdown</span>
          </h3>
          <p class="text-xs text-slate-500 font-medium">Stock management breakdown showing exact quantity sold per article and size</p>
        </div>
        <div class="flex items-center gap-2">
          <button
            @click="handleViewPdf"
            class="flex-1 sm:flex-initial flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-extrabold text-xs transition-all cursor-pointer min-h-[38px]"
            title="View PDF Report in Browser"
          >
            <span>👁️</span>
            <span>VIEW PDF</span>
          </button>
          <button
            @click="handleDownloadPdf"
            class="flex-1 sm:flex-initial flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 active:scale-95 text-white font-black text-xs shadow-md shadow-red-600/30 transition-all cursor-pointer min-h-[38px]"
            title="Download Item & Size-wise Sales PDF Report"
          >
            <span>📄</span>
            <span>EXPORT PDF</span>
          </button>
        </div>
      </div>

      <div v-if="itemWiseSales.length === 0" class="p-12 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
        <div class="text-3xl text-slate-400">📦</div>
        <h3 class="font-black text-sm text-slate-700 dark:text-slate-300">No Item Sales Records Found</h3>
        <p class="text-xs text-slate-400 font-medium">No items sold for {{ getFilterDesc() }}.</p>
      </div>

      <div v-else class="space-y-3">
        <!-- Mobile Cards (<768px) -->
        <div class="space-y-3 md:hidden">
          <div
            v-for="item in itemWiseSales"
            :key="item.sku || item.product_name + item.size"
            class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-2"
          >
            <div class="flex items-start justify-between">
              <div>
                <h4 class="font-black text-sm text-slate-900 dark:text-white">{{ item.product_name }}</h4>
                <p class="text-[11px] text-slate-500 font-mono">Item #: {{ item.article_number || 'N/A' }} • SKU: {{ item.sku || 'N/A' }}</p>
              </div>
              <span class="px-2.5 py-1 text-xs font-black rounded-lg bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-300">
                Size {{ item.size }}
              </span>
            </div>

            <div class="flex items-center justify-between p-2.5 bg-slate-50 dark:bg-slate-950 rounded-xl text-xs">
              <div>
                <span class="text-[10px] font-extrabold text-slate-400 uppercase block">Quantity Sold</span>
                <span class="font-mono font-black text-slate-900 dark:text-white text-sm">{{ item.qty_sold }} PCS</span>
              </div>
              <div class="text-right">
                <span class="text-[10px] font-extrabold text-slate-400 uppercase block">Net Revenue</span>
                <span class="font-mono font-black text-emerald-600">₹{{ formatCurrency(item.net_sales) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Desktop Table (≥768px) -->
        <div class="hidden md:block bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 overflow-x-auto shadow-xs">
          <table class="w-full text-left text-xs font-sans whitespace-nowrap">
            <thead class="bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 text-[10px] uppercase font-black tracking-wider text-slate-500 dark:text-slate-400">
              <tr>
                <th class="py-3 px-4">Item Number / Article</th>
                <th class="py-3 px-4">Item Name</th>
                <th class="py-3 px-4 text-center">Size</th>
                <th class="py-3 px-4 text-center">Quantity Sold</th>
                <th class="py-3 px-4 text-right">Net Sales (₹)</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
              <tr v-for="item in itemWiseSales" :key="item.sku || item.product_name + item.size" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                <td class="py-3 px-4 font-mono font-extrabold text-slate-900 dark:text-white">{{ item.article_number || '-' }}</td>
                <td class="py-3 px-4 font-bold text-slate-800 dark:text-slate-200">{{ item.product_name }}</td>
                <td class="py-3 px-4 text-center font-mono font-black text-red-600 dark:text-red-400">{{ item.size }}</td>
                <td class="py-3 px-4 text-center font-mono font-black text-slate-900 dark:text-white">{{ item.qty_sold }} PCS</td>
                <td class="py-3 px-4 text-right font-mono font-bold text-emerald-600">₹{{ formatCurrency(item.net_sales) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Total Units Sold Summary Bar -->
        <div class="flex items-center justify-between p-4 bg-slate-900 text-white rounded-2xl shadow-md font-bold text-sm">
          <span class="flex items-center gap-2">
            <span>📦</span>
            <span>Total Units Sold:</span>
          </span>
          <span class="font-mono font-black text-lg text-emerald-400">{{ totalItemsSold }} PCS</span>
        </div>
      </div>
    </div>

    <!-- TAB 3: DATE-WISE SALES -->
    <div v-else-if="activeTab === 'date_wise'" class="space-y-3">
      <div v-if="dateWiseSales.length === 0" class="p-12 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
        <div class="text-3xl text-slate-400">📅</div>
        <h3 class="font-black text-sm text-slate-700 dark:text-slate-300">No Date-Wise Sales Data</h3>
        <p class="text-xs text-slate-400 font-medium">No sales recorded for {{ getFilterDesc() }}.</p>
      </div>

      <div v-else class="space-y-3">
        <!-- Mobile Cards (<768px) -->
        <div class="space-y-3 md:hidden">
          <div
            v-for="d in dateWiseSales"
            :key="d.period_label"
            class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-2"
          >
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
              <span class="font-black font-mono text-sm text-slate-900 dark:text-white">{{ d.period_label }}</span>
              <span class="px-2.5 py-0.5 text-[10px] font-black bg-indigo-100 text-indigo-800 dark:bg-indigo-950/50 dark:text-indigo-300 rounded-full">
                {{ d.invoice_count }} Invoices
              </span>
            </div>
            <div class="grid grid-cols-2 gap-2 p-2.5 bg-slate-50 dark:bg-slate-950 rounded-xl text-xs">
              <div>
                <span class="text-[10px] font-extrabold text-slate-400 uppercase block">Gross Sales</span>
                <span class="font-mono font-bold text-slate-800 dark:text-slate-200">₹{{ formatCurrency(d.gross_sales) }}</span>
              </div>
              <div>
                <span class="text-[10px] font-extrabold text-slate-400 uppercase block">Discount</span>
                <span class="font-mono text-amber-600">₹{{ formatCurrency(d.discount) }}</span>
              </div>
              <div>
                <span class="text-[10px] font-extrabold text-slate-400 uppercase block">Returns</span>
                <span class="font-mono text-red-600">₹{{ formatCurrency(d.returns || 0) }}</span>
              </div>
              <div>
                <span class="text-[10px] font-extrabold text-slate-400 uppercase block">Net Sales</span>
                <span class="font-mono font-black text-emerald-600">₹{{ formatCurrency(d.net_sales) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Desktop Table (≥768px) -->
        <div class="hidden md:block bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 overflow-x-auto shadow-xs">
          <table class="w-full text-left text-xs font-sans whitespace-nowrap min-w-[700px]">
            <thead class="bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 text-[10px] uppercase font-black tracking-wider text-slate-500 dark:text-slate-400">
              <tr>
                <th class="py-3 px-4">Period / Date</th>
                <th class="py-3 px-4 text-center">Bills Count</th>
                <th class="py-3 px-4 text-right">Gross Sales</th>
                <th class="py-3 px-4 text-right">Discounts</th>
                <th class="py-3 px-4 text-right">Returns</th>
                <th class="py-3 px-4 text-right">Net Sales</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
              <tr v-for="d in dateWiseSales" :key="d.period_label" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                <td class="py-3 px-4 font-extrabold font-mono text-slate-900 dark:text-white">{{ d.period_label }}</td>
                <td class="py-3 px-4 text-center font-mono font-bold text-indigo-600">{{ d.invoice_count }}</td>
                <td class="py-3 px-4 text-right font-mono text-slate-700 dark:text-slate-300">₹{{ formatCurrency(d.gross_sales) }}</td>
                <td class="py-3 px-4 text-right font-mono text-amber-600">₹{{ formatCurrency(d.discount) }}</td>
                <td class="py-3 px-4 text-right font-mono text-red-600">₹{{ formatCurrency(d.returns || 0) }}</td>
                <td class="py-3 px-4 text-right font-mono font-black text-slate-900 dark:text-white">₹{{ formatCurrency(d.net_sales) }}</td>
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
import { ref, reactive, computed, watch, onMounted } from 'vue';
import ReportNavHeader from '../../components/ui/ReportNavHeader.vue';
import ReportKpiCard from '../../components/ui/ReportKpiCard.vue';
import ReportListCard from '../../components/ui/ReportListCard.vue';
import ReportFilterSheet from '../../components/ui/ReportFilterSheet.vue';
import api from '../../services/api';
import { useReportPdf } from '../../composables/useReportPdf';

const { exportReportPdf } = useReportPdf();

function handleViewPdf() {
  const reportType = activeTab.value === 'item_wise' ? 'sales_itemwise' : activeTab.value === 'date_wise' ? 'sales_datewise' : 'sales_summary';
  exportReportPdf(reportType, { ...filterState, search: searchQuery.value }, 'inline');
}

function handleDownloadPdf() {
  const reportType = activeTab.value === 'item_wise' ? 'sales_itemwise' : activeTab.value === 'date_wise' ? 'sales_datewise' : 'sales_summary';
  exportReportPdf(reportType, { ...filterState, search: searchQuery.value }, 'attachment');
}

const loading = ref(false);
const errorMessage = ref('');
const showFilterSheet = ref(false);
const searchQuery = ref('');
const activeTab = ref('overview');

const summary = ref({
  total_sales_count: 0,
  total_grand_total: 0,
  total_paid_amount: 0,
  total_outstanding_amount: 0,
  total_refund_amount: 0,
  net_revenue: 0,
});

const invoices = ref([]);
const itemWiseSales = ref([]);
const dateWiseSales = ref([]);

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  total: 0,
});

const filterState = reactive({
  date_from: '',
  date_to: '',
  group_by: 'day',
});

const tabs = [
  { id: 'overview', label: 'Sales Invoices', icon: '🧾' },
  { id: 'item_wise', label: 'Item-Wise Sales', icon: '📦' },
  { id: 'date_wise', label: 'Date-Wise Sales', icon: '📅' },
];

const activeFilterCount = computed(() => {
  let count = 0;
  if (filterState.date_from || filterState.date_to) count++;
  return count;
});

const totalItemsSold = computed(() => {
  return itemWiseSales.value.reduce((sum, item) => sum + Number(item.qty_sold || 0), 0);
});

const avgInvoiceValue = computed(() => {
  const count = Number(summary.value.total_sales_count) || 0;
  const total = Number(summary.value.total_grand_total) || 0;
  return count > 0 ? (total / count) : 0;
});

let searchTimeout = null;
function debounceSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    pagination.current_page = 1;
    loadAllData();
  }, 350);
}

async function loadAllData() {
  loading.value = true;
  errorMessage.value = '';
  try {
    await fetchSalesSummary();
    if (activeTab.value === 'overview') {
      await fetchInvoices();
    } else if (activeTab.value === 'item_wise') {
      await fetchItemWiseSales();
    } else if (activeTab.value === 'date_wise') {
      await fetchDateWiseSales();
    }
  } catch (e) {
    console.error('Failed to fetch sales reports:', e);
    errorMessage.value = e?.message || 'Failed to load report data from server.';
  } finally {
    loading.value = false;
  }
}

async function fetchSalesSummary() {
  const params = {};
  if (filterState.date_from) params.date_from = filterState.date_from;
  if (filterState.date_to) params.date_to = filterState.date_to;

  const res = await api.get('/reports/sales-summary', { params });
  const payload = (res && res.data !== undefined) ? res.data : res;
  if (payload) {
    summary.value = payload;
  }
}

async function fetchInvoices() {
  const params = {
    page: pagination.current_page,
    per_page: 15,
  };
  if (filterState.date_from) params.date_from = filterState.date_from;
  if (filterState.date_to) params.date_to = filterState.date_to;
  if (searchQuery.value) params.search = searchQuery.value;

  const res = await api.get('/pos/sales', { params });
  const payload = (res && res.data !== undefined) ? res.data : res;
  if (payload) {
    const itemsList = payload.items || (Array.isArray(payload) ? payload : (payload.data || []));
    invoices.value = itemsList;
    if (payload.pagination) {
      pagination.current_page = payload.pagination.current_page;
      pagination.last_page = payload.pagination.last_page;
      pagination.total = payload.pagination.total;
    }
  }
}

async function fetchItemWiseSales() {
  const params = {};
  if (filterState.date_from) params.date_from = filterState.date_from;
  if (filterState.date_to) params.date_to = filterState.date_to;
  if (searchQuery.value) params.search = searchQuery.value;

  const res = await api.get('/reports/item-wise-sales', { params });
  const payload = (res && res.data !== undefined) ? res.data : res;
  if (payload) {
    itemWiseSales.value = payload.items || (Array.isArray(payload) ? payload : []);
  }
}

async function fetchDateWiseSales() {
  const params = {
    group_by: filterState.group_by || 'day',
  };
  if (filterState.date_from) params.date_from = filterState.date_from;
  if (filterState.date_to) params.date_to = filterState.date_to;

  const res = await api.get('/reports/date-wise-sales', { params });
  const payload = (res && res.data !== undefined) ? res.data : res;
  if (payload) {
    dateWiseSales.value = payload.items || (Array.isArray(payload) ? payload : []);
  }
}

watch(activeTab, () => {
  loadAllData();
});

function handleApplyFilters(newFilters) {
  Object.assign(filterState, newFilters);
  pagination.current_page = 1;
  loadAllData();
}

function handleResetFilters() {
  filterState.date_from = '';
  filterState.date_to = '';
  filterState.group_by = 'day';
  pagination.current_page = 1;
  loadAllData();
}

function changePage(page) {
  if (page >= 1 && page <= pagination.last_page) {
    pagination.current_page = page;
    fetchInvoices();
  }
}

// EXPORT PDF HANDLER
function handleExportPdf() {
  const filterDesc = getFilterDesc();
  const kpiCards = [
    { title: 'Total Billed Sales', value: '₹' + formatCurrency(summary.value.total_grand_total) },
    { title: 'Invoices Count', value: summary.value.total_sales_count },
    { title: 'Collected Payments', value: '₹' + formatCurrency(summary.value.total_paid_amount) },
    { title: 'Net Revenue', value: '₹' + formatCurrency(summary.value.net_revenue) },
  ];

  if (activeTab.value === 'overview') {
    const cols = [
      { label: 'Invoice No', field: 'invoice_number' },
      { label: 'Date', value: r => formatDate(r.created_at) },
      { label: 'Customer', value: r => r.customer ? r.customer.name : 'Walk-in' },
      { label: 'Status', field: 'payment_status', isStatus: true },
      { label: 'Grand Total', value: r => '₹' + formatCurrency(r.grand_total), align: 'right' },
      { label: 'Paid Amount', value: r => '₹' + formatCurrency(r.paid_amount), align: 'right' },
    ];
    exportToPdf('Sales & Revenue Report (Summary)', filterDesc, kpiCards, cols, invoices.value);
  } else if (activeTab.value === 'item_wise') {
    const cols = [
      { label: 'Product Name', field: 'product_name' },
      { label: 'Article No', field: 'article_number' },
      { label: 'SKU', field: 'sku' },
      { label: 'Qty Sold', field: 'qty_sold', align: 'center' },
      { label: 'Net Sales', value: r => '₹' + formatCurrency(r.net_sales), align: 'right' },
      { label: 'COGS', value: r => '₹' + formatCurrency(r.cogs), align: 'right' },
      { label: 'Gross Profit', value: r => '₹' + formatCurrency(r.gross_profit), align: 'right' },
      { label: 'Margin %', value: r => r.margin_pct + '%', align: 'right' },
    ];
    exportToPdf('Item-Wise Sales & Profitability Report', filterDesc, kpiCards, cols, itemWiseSales.value);
  } else if (activeTab.value === 'date_wise') {
    const cols = [
      { label: 'Period / Date', field: 'period_label' },
      { label: 'Bills Count', field: 'invoice_count', align: 'center' },
      { label: 'Gross Sales', value: r => '₹' + formatCurrency(r.gross_sales), align: 'right' },
      { label: 'Discounts', value: r => '₹' + formatCurrency(r.discount), align: 'right' },
      { label: 'Net Sales', value: r => '₹' + formatCurrency(r.net_sales), align: 'right' },
    ];
    exportToPdf('Date-Wise Sales Trend Report', filterDesc, kpiCards, cols, dateWiseSales.value);
  }
}

// EXPORT CSV HANDLER
function handleExportCsv() {
  if (activeTab.value === 'overview') {
    const cols = [
      { label: 'Invoice Number', field: 'invoice_number' },
      { label: 'Date', value: r => formatDate(r.created_at) },
      { label: 'Customer', value: r => r.customer ? r.customer.name : 'Walk-in' },
      { label: 'Status', field: 'payment_status' },
      { label: 'Grand Total', field: 'grand_total' },
      { label: 'Paid Amount', field: 'paid_amount' },
      { label: 'Due Amount', field: 'due_amount' },
    ];
    exportToCsv('sales_summary_report', cols, invoices.value);
  } else if (activeTab.value === 'item_wise') {
    const cols = [
      { label: 'Product Name', field: 'product_name' },
      { label: 'Article Number', field: 'article_number' },
      { label: 'SKU', field: 'sku' },
      { label: 'Qty Sold', field: 'qty_sold' },
      { label: 'Gross Sales', field: 'gross_sales' },
      { label: 'Discount', field: 'discount' },
      { label: 'Net Sales', field: 'net_sales' },
      { label: 'COGS', field: 'cogs' },
      { label: 'Gross Profit', field: 'gross_profit' },
      { label: 'Margin %', field: 'margin_pct' },
    ];
    exportToCsv('item_wise_sales_report', cols, itemWiseSales.value);
  } else if (activeTab.value === 'date_wise') {
    const cols = [
      { label: 'Date Period', field: 'period_label' },
      { label: 'Total Bills', field: 'invoice_count' },
      { label: 'Gross Sales', field: 'gross_sales' },
      { label: 'Total Discount', field: 'discount' },
      { label: 'Net Sales', field: 'net_sales' },
    ];
    exportToCsv('date_wise_sales_report', cols, dateWiseSales.value);
  }
}

function getFilterDesc() {
  if (filterState.date_from || filterState.date_to) {
    if (filterState.date_from === filterState.date_to) {
      return formatDate(filterState.date_from);
    }
    return `${formatDate(filterState.date_from || 'Start')} to ${formatDate(filterState.date_to || 'Today')}`;
  }
  return 'All Time';
}

function formatCurrency(val) {
  const num = Number(val) || 0;
  return num.toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

function formatDate(dateStr) {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function roundVal(v) {
  return Math.round((v + Number.EPSILON) * 100) / 100;
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
