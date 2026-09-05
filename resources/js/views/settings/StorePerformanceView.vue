<template>
  <div class="space-y-6 pb-24 antialiased font-sans max-w-full">
    <!-- 1. Header & Controls Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs">
      <div>
        <div class="flex items-center gap-2">
          <span class="text-xl">🎯</span>
          <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100">Store Performance</h1>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          Performance overview based on actual sales, purchases, inventory and financial data.
        </p>
      </div>

      <!-- Controls & Dropdowns -->
      <div class="flex flex-wrap items-center gap-2.5">
        <!-- Store Selector -->
        <div v-if="stores.length > 0" class="flex items-center gap-1.5">
          <label class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider hidden sm:inline">Store:</label>
          <select
            v-model="selectedStoreId"
            @change="fetchPerformance"
            class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-800 dark:text-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-600 cursor-pointer min-h-[44px]"
          >
            <option v-for="s in stores" :key="s.id" :value="s.id">
              {{ s.code }} — {{ s.name }}
            </option>
          </select>
        </div>

        <!-- Date Range Presets -->
        <div class="flex items-center gap-1.5">
          <label class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider hidden sm:inline">Period:</label>
          <select
            v-model="selectedPeriod"
            @change="handlePeriodChange"
            class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-800 dark:text-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-600 cursor-pointer min-h-[44px]"
          >
            <option value="today">Today</option>
            <option value="yesterday">Yesterday</option>
            <option value="this_week">This Week</option>
            <option value="this_month">This Month</option>
            <option value="this_year">This Year</option>
            <option value="custom">Custom Range</option>
          </select>
        </div>

        <!-- Group By Filter -->
        <div class="flex items-center gap-1.5">
          <label class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider hidden sm:inline">Group:</label>
          <select
            v-model="selectedGroupBy"
            @change="fetchPerformance"
            class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-800 dark:text-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-600 cursor-pointer min-h-[44px]"
          >
            <option value="day">By Day</option>
            <option value="week">By Week</option>
            <option value="month">By Month</option>
          </select>
        </div>

        <!-- Refresh Button -->
        <button
          type="button"
          @click="fetchPerformance"
          :disabled="loading"
          class="w-11 h-11 rounded-xl bg-slate-900 text-white dark:bg-slate-800 hover:bg-slate-800 dark:hover:bg-slate-700 flex items-center justify-center font-bold text-sm cursor-pointer active:scale-95 transition-all min-h-[44px] shrink-0"
          title="Refresh Data"
        >
          <span :class="{ 'animate-spin': loading }">🔄</span>
        </button>

        <!-- Mobile Filter Sheet Launcher -->
        <button
          type="button"
          @click="showMobileFilterSheet = true"
          class="md:hidden px-3 py-2.5 rounded-xl bg-red-600 text-white font-bold text-xs flex items-center gap-1.5 shadow-md shadow-red-600/30 active:scale-95 transition-all min-h-[44px]"
        >
          <span>🔍</span>
          <span>Filters</span>
        </button>
      </div>
    </div>

    <!-- Custom Date Inputs (shown if period === 'custom') -->
    <div v-if="selectedPeriod === 'custom'" class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-center gap-4">
      <div class="flex items-center gap-2 w-full sm:w-auto">
        <label class="text-xs font-bold text-slate-600 dark:text-slate-400">From:</label>
        <input
          type="date"
          v-model="customDateFrom"
          class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-red-600 w-full sm:w-auto min-h-[44px]"
        />
      </div>
      <div class="flex items-center gap-2 w-full sm:w-auto">
        <label class="text-xs font-bold text-slate-600 dark:text-slate-400">To:</label>
        <input
          type="date"
          v-model="customDateTo"
          class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-red-600 w-full sm:w-auto min-h-[44px]"
        />
      </div>
      <button
        type="button"
        @click="fetchPerformance"
        class="px-4 py-2.5 bg-red-600 text-white rounded-xl font-bold text-xs hover:bg-red-700 active:scale-95 transition-all w-full sm:w-auto min-h-[44px]"
      >
        Apply Custom Dates
      </button>
    </div>

    <!-- Error Alert -->
    <ErrorAlert v-if="error" :message="error" />

    <!-- Loading Skeleton -->
    <div v-if="loading && !perfData" class="py-12 flex flex-col items-center justify-center text-slate-400">
      <span class="text-3xl animate-spin mb-2">🔄</span>
      <p class="text-xs font-bold">Loading store performance metrics...</p>
    </div>

    <!-- MAIN DATA DISPLAY -->
    <template v-else-if="perfData">
      <!-- 2. 8 Primary KPI Cards Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Total Gross Sales -->
        <KpiCard
          title="Total Gross Sales"
          :value="'₹' + formatAmount(kpis.total_sales)"
          icon="💳"
          icon-bg-class="bg-blue-50 text-blue-600 border border-blue-100"
          :supporting-text="'Discounts: ₹' + formatAmount(kpis.discounts)"
        />

        <!-- 2. Net Sales Revenue -->
        <KpiCard
          title="Net Sales Revenue"
          :value="'₹' + formatAmount(kpis.net_sales)"
          icon="🛍️"
          icon-bg-class="bg-emerald-50 text-emerald-600 border border-emerald-100"
          :supporting-text="'Returns: ₹' + formatAmount(kpis.returns)"
        />

        <!-- 3. Total Orders / Invoices -->
        <KpiCard
          title="Completed Orders"
          :value="kpis.total_orders"
          icon="🛒"
          icon-bg-class="bg-purple-50 text-purple-600 border border-purple-100"
          :supporting-text="kpis.items_sold + ' items sold'"
        />

        <!-- 4. Average Order Value -->
        <KpiCard
          title="Average Order Value"
          :value="'₹' + formatAmount(kpis.avg_order_value)"
          icon="📊"
          icon-bg-class="bg-amber-50 text-amber-600 border border-amber-100"
          supporting-text="Per completed invoice"
        />

        <!-- 5. Gross Profit -->
        <KpiCard
          title="Gross Profit"
          :value="'₹' + formatAmount(kpis.gross_profit)"
          icon="💹"
          icon-bg-class="bg-emerald-100 text-emerald-700 border border-emerald-200"
          :supporting-text="kpis.gross_margin_percentage + '% Margin'"
        />

        <!-- 6. Net Profit -->
        <KpiCard
          title="Net Profit"
          :value="'₹' + formatAmount(kpis.net_profit)"
          icon="📈"
          :icon-bg-class="kpis.net_profit >= 0 ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-red-50 text-red-600 border border-red-100'"
          :supporting-text="'Expenses: ₹' + formatAmount(kpis.operating_expenses)"
        />

        <!-- 7. Total Returns -->
        <KpiCard
          title="Total Returns"
          :value="'₹' + formatAmount(kpis.returns)"
          icon="↩️"
          icon-bg-class="bg-red-50 text-red-600 border border-red-100"
          :supporting-text="kpis.returns_count + ' return(s) (' + kpis.return_rate_percentage + '%)'"
        />

        <!-- 8. Items Sold -->
        <KpiCard
          title="Total Items Sold"
          :value="kpis.items_sold"
          icon="👟"
          icon-bg-class="bg-indigo-50 text-indigo-600 border border-indigo-100"
          supporting-text="Across footwear sizes"
        />
      </div>

      <!-- 3. Sales Trend Chart & Profit Breakdown Row -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Sales Trend Chart (8 Cols) -->
        <div class="lg:col-span-8 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs">
          <SalesOverview :sales-trend="perfData.sales_trend" />
        </div>

        <!-- Profit Performance Breakdown (4 Cols) -->
        <div class="lg:col-span-4 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs flex flex-col justify-between space-y-4">
          <div>
            <div class="flex items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-3 mb-4">
              <span class="text-base">📈</span>
              <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Profit Performance Statement</h3>
            </div>

            <div class="space-y-3 text-xs">
              <div class="flex justify-between items-center py-1 border-b border-slate-100 dark:border-slate-800">
                <span class="text-slate-600 dark:text-slate-400 font-medium">Gross Billed Sales</span>
                <span class="font-bold text-slate-900 dark:text-slate-100">₹{{ formatAmount(profitBreakdown.gross_sales) }}</span>
              </div>
              <div class="flex justify-between items-center py-1 border-b border-slate-100 dark:border-slate-800 text-red-600">
                <span class="font-medium">Less: Discounts & Returns</span>
                <span class="font-bold">-₹{{ formatAmount(kpis.discounts + kpis.returns) }}</span>
              </div>
              <div class="flex justify-between items-center py-1.5 bg-slate-50 dark:bg-slate-800/60 px-3 rounded-lg font-black text-slate-900 dark:text-slate-100">
                <span>Net Sales Revenue</span>
                <span>₹{{ formatAmount(profitBreakdown.net_sales) }}</span>
              </div>
              <div class="flex justify-between items-center py-1 border-b border-slate-100 dark:border-slate-800 text-slate-500">
                <span>Less: COGS (Cost of Goods)</span>
                <span class="font-bold">-₹{{ formatAmount(profitBreakdown.cogs) }}</span>
              </div>
              <div class="flex justify-between items-center py-2 px-3 bg-emerald-50 dark:bg-emerald-950/40 rounded-xl text-emerald-700 dark:text-emerald-400 font-black">
                <span>Gross Profit ({{ profitBreakdown.gross_margin_pct }}%)</span>
                <span>₹{{ formatAmount(profitBreakdown.gross_profit) }}</span>
              </div>
              <div class="flex justify-between items-center py-1 border-b border-slate-100 dark:border-slate-800 text-amber-600">
                <span>Less: Operating Expenses</span>
                <span class="font-bold">-₹{{ formatAmount(profitBreakdown.operating_expenses) }}</span>
              </div>
            </div>
          </div>

          <div
            :class="[
              'p-4 rounded-xl border flex items-center justify-between font-black text-sm',
              profitBreakdown.net_profit >= 0
                ? 'bg-emerald-500 text-white border-emerald-600 shadow-md shadow-emerald-500/20'
                : 'bg-red-600 text-white border-red-700 shadow-md shadow-red-600/20'
            ]"
          >
            <span>NET PROFIT / (LOSS)</span>
            <span>₹{{ formatAmount(profitBreakdown.net_profit) }}</span>
          </div>
        </div>
      </div>

      <!-- 4. Top Selling Products & Inventory Status Row -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Top Products (7 Cols) -->
        <div class="lg:col-span-7 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs">
          <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3 mb-4">
            <div class="flex items-center gap-2">
              <span class="text-base">🏆</span>
              <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Top Selling Products</h3>
            </div>
            <span class="text-[10px] font-extrabold text-slate-400 uppercase bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded">Top 10 SKUs</span>
          </div>

          <div v-if="topProducts.length === 0" class="py-12 text-center text-slate-400 text-xs font-bold">
            No product sales recorded for this period.
          </div>
          <div v-else class="overflow-x-auto">
            <table class="w-full text-left text-xs font-sans">
              <thead>
                <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 uppercase text-[10px] font-black tracking-wider">
                  <th class="pb-2">Product Name</th>
                  <th class="pb-2">Article #</th>
                  <th class="pb-2 text-right">Qty Sold</th>
                  <th class="pb-2 text-right">Sales Amount</th>
                  <th class="pb-2 text-right">Gross Profit</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="(p, idx) in topProducts" :key="p.product_id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                  <td class="py-2.5 font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                    <span class="w-5 h-5 rounded-full bg-slate-100 dark:bg-slate-800 text-[10px] font-black flex items-center justify-center text-slate-500 shrink-0">{{ idx + 1 }}</span>
                    <span class="truncate max-w-xs">{{ p.name }}</span>
                  </td>
                  <td class="py-2.5 font-mono text-slate-500 dark:text-slate-400 text-[11px]">{{ p.article_number }}</td>
                  <td class="py-2.5 text-right font-extrabold text-slate-900 dark:text-slate-100">{{ p.quantity_sold }}</td>
                  <td class="py-2.5 text-right font-black text-slate-900 dark:text-slate-100">₹{{ formatAmount(p.sales_amount) }}</td>
                  <td class="py-2.5 text-right font-extrabold text-emerald-600 dark:text-emerald-400">₹{{ formatAmount(p.gross_profit) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Inventory Performance Breakdown (5 Cols) -->
        <div class="lg:col-span-5 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-4">
          <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <div class="flex items-center gap-2">
              <span class="text-base">📦</span>
              <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Inventory Status & Assets</h3>
            </div>
            <span class="text-[10px] font-bold text-indigo-600 bg-indigo-50 dark:bg-indigo-950/60 px-2 py-0.5 rounded border border-indigo-200 dark:border-indigo-800">
              Live Stock
            </span>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200/80 dark:border-slate-700/60">
              <div class="text-[10px] font-bold uppercase text-slate-500">Total Stock Units</div>
              <div class="text-lg font-black text-slate-900 dark:text-slate-100 mt-0.5">{{ inventory.total_stock_units }}</div>
            </div>
            <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200/80 dark:border-slate-700/60">
              <div class="text-[10px] font-bold uppercase text-slate-500">Asset Cost Value</div>
              <div class="text-lg font-black text-slate-900 dark:text-slate-100 mt-0.5">₹{{ formatAmount(inventory.cost_value) }}</div>
            </div>
          </div>

          <!-- Stock Condition Indicators -->
          <div class="space-y-2.5 text-xs pt-2">
            <div class="flex items-center justify-between p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/50">
              <div class="flex items-center gap-2 font-bold text-emerald-800 dark:text-emerald-300">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                <span>In Stock SKUs</span>
              </div>
              <span class="font-black text-emerald-900 dark:text-emerald-200">{{ inventory.in_stock_items }}</span>
            </div>

            <div class="flex items-center justify-between p-3 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50">
              <div class="flex items-center gap-2 font-bold text-amber-800 dark:text-amber-300">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                <span>Low Stock Warning</span>
              </div>
              <span class="font-black text-amber-900 dark:text-amber-200">{{ inventory.low_stock_items }}</span>
            </div>

            <div class="flex items-center justify-between p-3 rounded-xl bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800/50">
              <div class="flex items-center gap-2 font-bold text-red-800 dark:text-red-300">
                <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
                <span>Out of Stock Items</span>
              </div>
              <span class="font-black text-red-900 dark:text-red-200">{{ inventory.out_of_stock_items }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 5. Payment Methods, Returns/Exchanges & Customer Performance Grid -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Payment Method Breakdown -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-4">
          <div class="flex items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
            <span class="text-base">💳</span>
            <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Payment Collection</h3>
          </div>

          <div class="space-y-2 text-xs">
            <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
              <span class="text-slate-600 dark:text-slate-400 font-medium">💵 Cash</span>
              <span class="font-bold text-slate-900 dark:text-slate-100">₹{{ formatAmount(payments.cash) }}</span>
            </div>
            <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
              <span class="text-slate-600 dark:text-slate-400 font-medium">📱 UPI Direct</span>
              <span class="font-bold text-slate-900 dark:text-slate-100">₹{{ formatAmount(payments.upi) }}</span>
            </div>
            <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
              <span class="text-slate-600 dark:text-slate-400 font-medium">💳 Debit / Credit Card</span>
              <span class="font-bold text-slate-900 dark:text-slate-100">₹{{ formatAmount(payments.card) }}</span>
            </div>
            <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
              <span class="text-slate-600 dark:text-slate-400 font-medium">🏦 Bank / Net Banking</span>
              <span class="font-bold text-slate-900 dark:text-slate-100">₹{{ formatAmount(payments.bank) }}</span>
            </div>
            <div class="flex justify-between py-2 bg-slate-50 dark:bg-slate-800/80 px-3 rounded-xl font-black text-slate-900 dark:text-slate-100 text-xs">
              <span>Total Collections</span>
              <span>₹{{ formatAmount(payments.total_collected) }}</span>
            </div>
          </div>
        </div>

        <!-- Returns & Exchanges -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-4">
          <div class="flex items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
            <span class="text-base">🔄</span>
            <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Returns & Exchanges</h3>
          </div>

          <div class="space-y-3 text-xs">
            <div class="p-3 bg-red-50 dark:bg-red-950/30 rounded-xl border border-red-100 dark:border-red-800/40 flex justify-between items-center">
              <span class="font-bold text-red-900 dark:text-red-300">Sales Returns Count</span>
              <span class="font-black text-red-700 dark:text-red-200 text-base">{{ returnsExchanges.returns_count }}</span>
            </div>
            <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 flex justify-between items-center">
              <span class="font-bold text-slate-700 dark:text-slate-300">Total Refund Amount</span>
              <span class="font-black text-slate-900 dark:text-slate-100 text-base">₹{{ formatAmount(returnsExchanges.returns_amount) }}</span>
            </div>
            <div class="p-3 bg-purple-50 dark:bg-purple-950/30 rounded-xl border border-purple-100 dark:border-purple-800/40 flex justify-between items-center">
              <span class="font-bold text-purple-900 dark:text-purple-300">Exchanges Processed</span>
              <span class="font-black text-purple-700 dark:text-purple-200 text-base">{{ returnsExchanges.exchanges_count }}</span>
            </div>
          </div>
        </div>

        <!-- Customer Metrics -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-4">
          <div class="flex items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
            <span class="text-base">👥</span>
            <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Customer Performance</h3>
          </div>

          <div class="space-y-2.5 text-xs">
            <div class="flex justify-between items-center py-1.5 border-b border-slate-100 dark:border-slate-800">
              <span class="text-slate-600 dark:text-slate-400 font-medium">Total Customers Served</span>
              <span class="font-black text-slate-900 dark:text-slate-100">{{ customers.total_customers_served }}</span>
            </div>
            <div class="flex justify-between items-center py-1.5 border-b border-slate-100 dark:border-slate-800">
              <span class="text-slate-600 dark:text-slate-400 font-medium">New Customers</span>
              <span class="font-black text-emerald-600">+{{ customers.new_customers }}</span>
            </div>
            <div class="flex justify-between items-center py-1.5 border-b border-slate-100 dark:border-slate-800">
              <span class="text-slate-600 dark:text-slate-400 font-medium">Repeat Customers</span>
              <span class="font-black text-indigo-600">{{ customers.repeat_customers }}</span>
            </div>
            <div class="flex justify-between items-center py-2 bg-slate-50 dark:bg-slate-800 px-3 rounded-xl font-bold text-slate-900 dark:text-slate-100">
              <span>Avg Spend / Customer</span>
              <span class="font-black">₹{{ formatAmount(customers.avg_customer_purchase) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 6. Recent Store Activity Stream -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs">
        <div class="flex items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-3 mb-4">
          <span class="text-base">⚡</span>
          <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Recent Store Activity</h3>
        </div>

        <div v-if="recentActivity.length === 0" class="py-8 text-center text-slate-400 text-xs font-bold">
          No recent activity logs for this store.
        </div>
        <div v-else class="divide-y divide-slate-100 dark:divide-slate-800 text-xs font-sans">
          <div v-for="(act, i) in recentActivity" :key="i" class="py-3 flex items-center justify-between gap-4 hover:bg-slate-50 dark:hover:bg-slate-800/40 px-2 rounded-xl transition-colors">
            <div class="flex items-center gap-3">
              <span
                :class="[
                  'w-8 h-8 rounded-xl flex items-center justify-center font-bold text-sm shrink-0',
                  act.type === 'sale' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'
                ]"
              >
                {{ act.type === 'sale' ? '🛒' : '↩️' }}
              </span>
              <div>
                <div class="font-bold text-slate-900 dark:text-slate-100">{{ act.description }}</div>
                <div class="text-[10px] text-slate-400 font-medium">{{ act.date }}</div>
              </div>
            </div>
            <div class="font-black text-slate-900 dark:text-slate-100 text-right">
              <span :class="act.type === 'sale' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                {{ act.type === 'sale' ? '+' : '-' }}₹{{ formatAmount(act.amount) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Mobile Filter Sheet -->
    <ReportFilterSheet
      v-model:show="showMobileFilterSheet"
      :filters="{ period: selectedPeriod, group_by: selectedGroupBy }"
      @apply="handleMobileFilterApply"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../services/api';

import KpiCard from '../../components/dashboard/KpiCard.vue';
import SalesOverview from '../../components/dashboard/SalesOverview.vue';
import ErrorAlert from '../../components/ui/ErrorAlert.vue';
import ReportFilterSheet from '../../components/ui/ReportFilterSheet.vue';

const route = useRoute();
const router = useRouter();

const stores = ref([]);
const selectedStoreId = ref(1);
const selectedPeriod = ref('this_month');
const selectedGroupBy = ref('day');
const customDateFrom = ref('');
const customDateTo = ref('');
const showMobileFilterSheet = ref(false);

const loading = ref(false);
const error = ref('');
const perfData = ref(null);

const kpis = computed(() => perfData.value?.kpis || {});
const profitBreakdown = computed(() => perfData.value?.profit_breakdown || {});
const topProducts = computed(() => perfData.value?.top_products || []);
const inventory = computed(() => perfData.value?.inventory || {});
const payments = computed(() => perfData.value?.payments || {});
const returnsExchanges = computed(() => perfData.value?.returns_exchanges || {});
const customers = computed(() => perfData.value?.customer_performance || {});
const recentActivity = computed(() => perfData.value?.recent_activity || []);

async function fetchStores() {
  try {
    const res = await api.get('/stores');
    if (res.success && res.data) {
      stores.value = res.data;
      if (route.query.store_id) {
        selectedStoreId.value = Number(route.query.store_id);
      } else if (res.data.length > 0) {
        selectedStoreId.value = res.data[0].id;
      }
    }
  } catch (err) {
    console.warn('Failed to load stores list:', err);
  }
}

async function fetchPerformance() {
  loading.value = true;
  error.value = '';
  try {
    const params = {
      period: selectedPeriod.value,
      group_by: selectedGroupBy.value,
    };
    if (selectedPeriod.value === 'custom') {
      if (customDateFrom.value) params.date_from = customDateFrom.value;
      if (customDateTo.value) params.date_to = customDateTo.value;
    }

    const res = await api.get(`/stores/${selectedStoreId.value}/performance`, { params });
    if (res.success && res.data) {
      perfData.value = res.data;
    }
  } catch (err) {
    error.value = err.message || 'Failed to load store performance metrics.';
  } finally {
    loading.value = false;
  }
}

function handlePeriodChange() {
  if (selectedPeriod.value !== 'custom') {
    fetchPerformance();
  }
}

function handleMobileFilterApply(filters) {
  if (filters.preset) selectedPeriod.value = filters.preset;
  if (filters.group_by) selectedGroupBy.value = filters.group_by;
  if (filters.date_from) customDateFrom.value = filters.date_from;
  if (filters.date_to) customDateTo.value = filters.date_to;
  fetchPerformance();
}

function formatAmount(val) {
  const num = Number(val || 0);
  return num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

watch(() => route.query.store_id, (newVal) => {
  if (newVal) {
    selectedStoreId.value = Number(newVal);
    fetchPerformance();
  }
});

onMounted(async () => {
  await fetchStores();
  fetchPerformance();
});
</script>
