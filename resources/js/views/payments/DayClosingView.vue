<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-16 antialiased font-sans">
    <!-- Toast Notification Banner -->
    <div v-if="notification.show" :class="['p-4 rounded-2xl border font-bold text-xs flex items-center justify-between shadow-md transition-all', notification.type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-red-50 border-red-200 text-red-900']">
      <div class="flex items-center gap-2">
        <span>{{ notification.type === 'success' ? '✅' : '⚠️' }}</span>
        <span>{{ notification.message }}</span>
      </div>
      <button @click="notification.show = false" class="text-slate-400 hover:text-slate-600 text-sm font-black cursor-pointer">✕</button>
    </div>

    <!-- Header & Date/Store Selectors -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">🔒 End-of-Day Financial Reconciliation & Closing</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Comprehensive business closing, category expense breakdown, digital matrix & denomination cash counting for STR-001
        </p>
      </div>

      <!-- DATE & STORE FILTER BAR -->
      <div class="flex flex-wrap items-center gap-2 text-xs">
        <div class="flex items-center gap-1.5 bg-white border border-slate-200 rounded-xl px-3 py-1.5 shadow-xs font-bold text-slate-700">
          <span>📅 Date:</span>
          <input type="date" v-model="selectedDate" @change="fetchDailySummary" class="bg-transparent focus:outline-none font-mono cursor-pointer" />
        </div>

        <div class="flex items-center gap-1.5 bg-white border border-slate-200 rounded-xl px-3 py-1.5 shadow-xs font-bold text-slate-700">
          <span>🏬 Store:</span>
          <select v-model="selectedStoreId" @change="fetchDailySummary" class="bg-transparent focus:outline-none cursor-pointer">
            <option :value="1">RUPSA PADUKALAYA — Main Outlet (STR-001)</option>
          </select>
        </div>

        <button
          @click="fetchDailySummary"
          class="px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold text-xs shadow-xs transition-all uppercase tracking-wider flex items-center gap-1.5 cursor-pointer"
        >
          <span>🔄 Reload</span>
        </button>
      </div>
    </div>

    <div v-if="loadingSummary" class="py-16 text-center text-slate-400 font-bold text-xs">
      Loading comprehensive financial summary for {{ selectedDate }}...
    </div>

    <template v-else>
      <!-- 1. HIGH-LEVEL FINANCIAL SUMMARY CARDS -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs">
          <div class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Gross Sales</div>
          <div class="text-lg font-black font-mono text-slate-900 mt-1">₹{{ formatCurrency(summary.sales_summary?.gross_sales) }}</div>
          <div class="text-[10px] text-slate-400 font-bold mt-0.5">Discounts: ₹{{ formatCurrency(summary.sales_summary?.discount_total) }}</div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs">
          <div class="text-[10px] font-black text-emerald-600 uppercase tracking-wider">Net Sales</div>
          <div class="text-lg font-black font-mono text-emerald-600 mt-1">₹{{ formatCurrency(summary.sales_summary?.net_sales) }}</div>
          <div class="text-[10px] text-slate-500 font-mono mt-0.5">{{ summary.sales_summary?.total_sales_count || 0 }} Txns | {{ summary.sales_summary?.total_items_sold || 0 }} Pairs</div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs">
          <div class="text-[10px] font-black text-blue-600 uppercase tracking-wider">Due Collections</div>
          <div class="text-lg font-black font-mono text-blue-600 mt-1">₹{{ formatCurrency(summary.customer_collections?.total_collections) }}</div>
          <div class="text-[10px] text-slate-400 font-bold mt-0.5">Cash: ₹{{ formatCurrency(summary.customer_collections?.cash_collections) }}</div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs">
          <div class="text-[10px] font-black text-red-600 uppercase tracking-wider">Refunds & Expenses</div>
          <div class="text-lg font-black font-mono text-red-600 mt-1">
            ₹{{ formatCurrency((summary.refunds_summary?.total_refunds || 0) + (summary.expenses_summary?.total_expenses || 0)) }}
          </div>
          <div class="text-[10px] text-slate-400 font-bold mt-0.5">Expenses: ₹{{ formatCurrency(summary.expenses_summary?.total_expenses) }}</div>
        </div>

        <div class="bg-slate-900 p-4 rounded-2xl border border-slate-900 shadow-md text-white col-span-2 sm:col-span-1">
          <div class="text-[10px] font-black text-amber-400 uppercase tracking-wider">Expected Cash</div>
          <div class="text-xl font-black font-mono text-amber-400 mt-1">₹{{ formatCurrency(summary.cash_drawer?.expected_cash) }}</div>
          <div class="text-[10px] text-slate-400 font-bold mt-0.5">Opening Float: ₹{{ formatCurrency(summary.cash_drawer?.opening_cash) }}</div>
        </div>
      </div>

      <!-- 2. DETAILED BREAKDOWN SECTIONS (SALES, COLLECTIONS, REFUNDS, SUPPLIERS) -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- A. SALES BREAKDOWN -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs space-y-2.5 text-xs">
          <div class="flex items-center justify-between border-b border-slate-100 pb-2">
            <h3 class="font-black text-slate-900 uppercase tracking-wider text-[11px]">🧾 Sales Breakdown</h3>
            <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 font-mono font-black text-[10px] rounded">{{ summary.transaction_counts?.sales_count || 0 }} Txns</span>
          </div>
          <div class="space-y-1.5 font-mono">
            <div class="flex justify-between text-slate-600">
              <span>Cash Sales:</span>
              <span class="font-bold text-slate-900">₹{{ formatCurrency(summary.sales_summary?.cash_sales) }}</span>
            </div>
            <div class="flex justify-between text-slate-600">
              <span>Card Sales:</span>
              <span class="font-bold text-slate-900">₹{{ formatCurrency(summary.sales_summary?.card_sales) }}</span>
            </div>
            <div class="flex justify-between text-slate-600">
              <span>UPI Sales:</span>
              <span class="font-bold text-slate-900">₹{{ formatCurrency(summary.sales_summary?.upi_sales) }}</span>
            </div>
            <div class="flex justify-between text-slate-600">
              <span>Bank / Other Sales:</span>
              <span class="font-bold text-slate-900">₹{{ formatCurrency(summary.sales_summary?.other_digital_sales) }}</span>
            </div>
            <div class="flex justify-between border-t border-slate-100 pt-1.5 font-black text-slate-900">
              <span>Total Net Sales:</span>
              <span class="text-emerald-600">₹{{ formatCurrency(summary.sales_summary?.net_sales) }}</span>
            </div>
          </div>
        </div>

        <!-- B. CUSTOMER COLLECTIONS -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs space-y-2.5 text-xs">
          <div class="flex items-center justify-between border-b border-slate-100 pb-2">
            <h3 class="font-black text-slate-900 uppercase tracking-wider text-[11px]">💳 Due Collections</h3>
            <span class="px-2 py-0.5 bg-blue-100 text-blue-800 font-mono font-black text-[10px] rounded">{{ summary.transaction_counts?.customer_collection_count || 0 }} Txns</span>
          </div>
          <div class="space-y-1.5 font-mono">
            <div class="flex justify-between text-slate-600">
              <span>Cash Collections:</span>
              <span class="font-bold text-emerald-600">+₹{{ formatCurrency(summary.customer_collections?.cash_collections) }}</span>
            </div>
            <div class="flex justify-between text-slate-600">
              <span>Card / UPI Collections:</span>
              <span class="font-bold text-slate-900">₹{{ formatCurrency((summary.customer_collections?.card_collections || 0) + (summary.customer_collections?.upi_collections || 0)) }}</span>
            </div>
            <div class="flex justify-between text-slate-600">
              <span>Bank / Other Collections:</span>
              <span class="font-bold text-slate-900">₹{{ formatCurrency(summary.customer_collections?.other_digital_collections) }}</span>
            </div>
            <div class="flex justify-between border-t border-slate-100 pt-1.5 font-black text-slate-900">
              <span>Total Collections:</span>
              <span class="text-blue-600">₹{{ formatCurrency(summary.customer_collections?.total_collections) }}</span>
            </div>
          </div>
        </div>

        <!-- C. REFUNDS -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs space-y-2.5 text-xs">
          <div class="flex items-center justify-between border-b border-slate-100 pb-2">
            <h3 class="font-black text-slate-900 uppercase tracking-wider text-[11px]">💸 Transaction Refunds</h3>
            <span class="px-2 py-0.5 bg-red-100 text-red-800 font-mono font-black text-[10px] rounded">{{ summary.transaction_counts?.refund_count || 0 }} Txns</span>
          </div>
          <div class="space-y-1.5 font-mono">
            <div class="flex justify-between text-slate-600">
              <span>Cash Refunds:</span>
              <span class="font-bold text-red-600">-₹{{ formatCurrency(summary.refunds_summary?.cash_refunds) }}</span>
            </div>
            <div class="flex justify-between text-slate-600">
              <span>Store Credit Refunds:</span>
              <span class="font-bold text-amber-600">₹{{ formatCurrency(summary.refunds_summary?.store_credit_refunds) }}</span>
            </div>
            <div class="flex justify-between text-slate-600">
              <span>Digital Refunds:</span>
              <span class="font-bold text-slate-900">₹{{ formatCurrency((summary.refunds_summary?.card_refunds || 0) + (summary.refunds_summary?.upi_refunds || 0)) }}</span>
            </div>
            <div class="flex justify-between border-t border-slate-100 pt-1.5 font-black text-slate-900">
              <span>Total Refunds:</span>
              <span class="text-red-600">₹{{ formatCurrency(summary.refunds_summary?.total_refunds) }}</span>
            </div>
          </div>
        </div>

        <!-- D. SUPPLIER PAYMENTS & PURCHASES -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs space-y-2.5 text-xs">
          <div class="flex items-center justify-between border-b border-slate-100 pb-2">
            <h3 class="font-black text-slate-900 uppercase tracking-wider text-[11px]">🏢 Supplier Payments</h3>
            <span class="px-2 py-0.5 bg-slate-100 text-slate-800 font-mono font-black text-[10px] rounded">{{ summary.supplier_summary?.purchase_bills_count || 0 }} Bills</span>
          </div>
          <div class="space-y-1.5 font-mono">
            <div class="flex justify-between text-slate-600">
              <span>Bills Total Amount:</span>
              <span class="font-bold text-slate-900">₹{{ formatCurrency(summary.supplier_summary?.purchase_bills_total) }}</span>
            </div>
            <div class="flex justify-between text-slate-600">
              <span>Cash Supplier Payments:</span>
              <span class="font-bold text-red-600">-₹{{ formatCurrency(summary.supplier_summary?.cash_supplier_payments) }}</span>
            </div>
            <div class="flex justify-between text-slate-600">
              <span>Digital Supplier Payments:</span>
              <span class="font-bold text-slate-900">₹{{ formatCurrency(summary.supplier_summary?.digital_supplier_payments) }}</span>
            </div>
            <div class="flex justify-between border-t border-slate-100 pt-1.5 font-black text-slate-900">
              <span>Total Supplier Payments:</span>
              <span class="text-slate-900">₹{{ formatCurrency(summary.supplier_summary?.total_supplier_payments) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 3. EXPENSE CATEGORY BREAKDOWN -->
      <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
          <h3 class="font-black text-xs text-slate-900 uppercase tracking-wider flex items-center gap-2">
            <span>📊</span>
            <span>Today's Expenses & Category Breakdown</span>
          </h3>
          <div class="font-mono font-black text-xs text-red-600">
            Total Expenses: ₹{{ formatCurrency(summary.expenses_summary?.total_expenses) }} ({{ summary.expenses_summary?.expenses_count || 0 }} Vouchers)
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="bg-slate-900 text-white font-extrabold uppercase tracking-wider text-[11px]">
                <th class="py-3 px-4">Expense Category</th>
                <th class="py-3 px-4 text-center">Voucher Count</th>
                <th class="py-3 px-4 text-right font-mono">Total Amount (₹)</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium">
              <tr v-if="!summary.expenses_summary?.category_breakdown?.length">
                <td colspan="3" class="text-center py-6 text-slate-400 font-bold">
                  No expenses recorded for this business date.
                </td>
              </tr>
              <tr v-for="cat in summary.expenses_summary?.category_breakdown" :key="cat.name" class="hover:bg-slate-50">
                <td class="py-2.5 px-4 font-bold text-slate-800">{{ cat.name }}</td>
                <td class="py-2.5 px-4 text-center font-mono font-bold text-slate-600">{{ cat.count }}</td>
                <td class="py-2.5 px-4 text-right font-mono font-black text-red-600">₹{{ formatCurrency(cat.total_amount) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 4. DIGITAL RECONCILIATION MATRIX -->
      <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-100">
          <h3 class="font-black text-xs text-slate-900 uppercase tracking-wider flex items-center gap-2">
            <span>💳</span>
            <span>Digital & Tender Payment Reconciliation Matrix</span>
          </h3>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="bg-slate-900 text-white font-extrabold uppercase tracking-wider text-[11px]">
                <th class="py-3.5 px-4">Payment Method</th>
                <th class="py-3.5 px-4 text-right font-mono">Sales</th>
                <th class="py-3.5 px-4 text-right font-mono">Collections</th>
                <th class="py-3.5 px-4 text-right font-mono">Refunds</th>
                <th class="py-3.5 px-4 text-right font-mono">Supplier Payments</th>
                <th class="py-3.5 px-4 text-right font-mono">Expenses</th>
                <th class="py-3.5 px-4 text-right font-mono font-black">Net Movement</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium">
              <tr v-for="row in summary.digital_reconciliation_matrix" :key="row.method" class="hover:bg-slate-50 font-mono">
                <td class="py-3 px-4 font-bold font-sans text-slate-900">{{ row.method }}</td>
                <td class="py-3 px-4 text-right text-emerald-600 font-bold">₹{{ formatCurrency(row.sales) }}</td>
                <td class="py-3 px-4 text-right text-blue-600 font-bold">₹{{ formatCurrency(row.collections) }}</td>
                <td class="py-3 px-4 text-right text-red-600 font-bold">₹{{ formatCurrency(row.refunds) }}</td>
                <td class="py-3 px-4 text-right text-slate-700">₹{{ formatCurrency(row.supplier_payments) }}</td>
                <td class="py-3 px-4 text-right text-red-600">₹{{ formatCurrency(row.expenses) }}</td>
                <td class="py-3 px-4 text-right font-black text-slate-900 text-sm">₹{{ formatCurrency(row.net_movement) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 5. INTERACTIVE CASH DENOMINATION CALCULATOR & CLOSING FORM -->
      <div class="bg-white p-6 rounded-2xl border-2 border-slate-200 shadow-xs space-y-6">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h3 class="font-black text-sm text-slate-900 uppercase tracking-wider flex items-center gap-2">
            <span>💵</span>
            <span>Interactive Cash Denomination Counter & Closing Verification</span>
          </h3>
          <span class="text-xs font-mono font-bold text-slate-500">Expected System Cash: ₹{{ formatCurrency(summary.cash_drawer?.expected_cash) }}</span>
        </div>

        <!-- DENOMINATION COUNTER GRID -->
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 text-xs">
          <div class="p-2.5 bg-slate-50 border rounded-xl space-y-1">
            <div class="font-mono font-black text-slate-700 text-center">₹500 Notes</div>
            <input type="number" min="0" v-model.number="denom.d500" placeholder="0" class="w-full bg-white border text-center rounded-lg p-1 font-mono font-bold text-slate-900 focus:ring-2 focus:ring-red-500/20" />
            <div class="text-[10px] font-mono text-slate-500 text-center font-bold">₹{{ formatCurrency(denom.d500 * 500) }}</div>
          </div>

          <div class="p-2.5 bg-slate-50 border rounded-xl space-y-1">
            <div class="font-mono font-black text-slate-700 text-center">₹200 Notes</div>
            <input type="number" min="0" v-model.number="denom.d200" placeholder="0" class="w-full bg-white border text-center rounded-lg p-1 font-mono font-bold text-slate-900 focus:ring-2 focus:ring-red-500/20" />
            <div class="text-[10px] font-mono text-slate-500 text-center font-bold">₹{{ formatCurrency(denom.d200 * 200) }}</div>
          </div>

          <div class="p-2.5 bg-slate-50 border rounded-xl space-y-1">
            <div class="font-mono font-black text-slate-700 text-center">₹100 Notes</div>
            <input type="number" min="0" v-model.number="denom.d100" placeholder="0" class="w-full bg-white border text-center rounded-lg p-1 font-mono font-bold text-slate-900 focus:ring-2 focus:ring-red-500/20" />
            <div class="text-[10px] font-mono text-slate-500 text-center font-bold">₹{{ formatCurrency(denom.d100 * 100) }}</div>
          </div>

          <div class="p-2.5 bg-slate-50 border rounded-xl space-y-1">
            <div class="font-mono font-black text-slate-700 text-center">₹50 Notes</div>
            <input type="number" min="0" v-model.number="denom.d50" placeholder="0" class="w-full bg-white border text-center rounded-lg p-1 font-mono font-bold text-slate-900 focus:ring-2 focus:ring-red-500/20" />
            <div class="text-[10px] font-mono text-slate-500 text-center font-bold">₹{{ formatCurrency(denom.d50 * 50) }}</div>
          </div>

          <div class="p-2.5 bg-slate-50 border rounded-xl space-y-1">
            <div class="font-mono font-black text-slate-700 text-center">₹20 Notes</div>
            <input type="number" min="0" v-model.number="denom.d20" placeholder="0" class="w-full bg-white border text-center rounded-lg p-1 font-mono font-bold text-slate-900 focus:ring-2 focus:ring-red-500/20" />
            <div class="text-[10px] font-mono text-slate-500 text-center font-bold">₹{{ formatCurrency(denom.d20 * 20) }}</div>
          </div>

          <div class="p-2.5 bg-slate-50 border rounded-xl space-y-1">
            <div class="font-mono font-black text-slate-700 text-center">₹10 Notes</div>
            <input type="number" min="0" v-model.number="denom.d10" placeholder="0" class="w-full bg-white border text-center rounded-lg p-1 font-mono font-bold text-slate-900 focus:ring-2 focus:ring-red-500/20" />
            <div class="text-[10px] font-mono text-slate-500 text-center font-bold">₹{{ formatCurrency(denom.d10 * 10) }}</div>
          </div>

          <div class="p-2.5 bg-slate-50 border rounded-xl space-y-1">
            <div class="font-mono font-black text-slate-700 text-center">Coins (₹)</div>
            <input type="number" step="0.01" min="0" v-model.number="denom.coins" placeholder="0" class="w-full bg-white border text-center rounded-lg p-1 font-mono font-bold text-slate-900 focus:ring-2 focus:ring-red-500/20" />
            <div class="text-[10px] font-mono text-slate-500 text-center font-bold">₹{{ formatCurrency(denom.coins) }}</div>
          </div>

          <div class="p-2.5 bg-slate-50 border rounded-xl space-y-1">
            <div class="font-mono font-black text-slate-700 text-center">Other (₹)</div>
            <input type="number" step="0.01" min="0" v-model.number="denom.other" placeholder="0" class="w-full bg-white border text-center rounded-lg p-1 font-mono font-bold text-slate-900 focus:ring-2 focus:ring-red-500/20" />
            <div class="text-[10px] font-mono text-slate-500 text-center font-bold">₹{{ formatCurrency(denom.other) }}</div>
          </div>
        </div>

        <!-- RECONCILIATION RESULT BAR -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 items-center pt-2">
          <div>
            <label class="block font-bold text-xs text-slate-700 mb-1">Expected System Cash</label>
            <div class="p-3 bg-slate-100 rounded-xl font-mono font-black text-slate-900 text-base">
              ₹{{ formatCurrency(summary.cash_drawer?.expected_cash) }}
            </div>
          </div>

          <div>
            <label class="block font-bold text-xs text-slate-700 mb-1">Actual Counted Cash (From Denominations)</label>
            <div class="p-3 bg-slate-900 text-white rounded-xl font-mono font-black text-base">
              ₹{{ formatCurrency(actualCountedCash) }}
            </div>
          </div>

          <div>
            <label class="block font-bold text-xs text-slate-700 mb-1">Cash Variance</label>
            <div :class="['p-3 rounded-xl font-mono font-black text-base', liveVariance === 0 ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : liveVariance < 0 ? 'bg-red-50 text-red-600 border border-red-200' : 'bg-amber-50 text-amber-600 border border-amber-200']">
              {{ liveVariance > 0 ? '+' : '' }}₹{{ formatCurrency(liveVariance) }}
              <span class="text-[10px] block font-sans font-bold">
                {{ liveVariance === 0 ? 'Exact Match' : liveVariance < 0 ? 'Cash Shortage' : 'Cash Surplus' }}
              </span>
            </div>
          </div>
        </div>

        <!-- DISCREPANCY NOTES -->
        <div>
          <label class="block font-bold text-xs text-slate-700 mb-1">
            Closing Notes / Discrepancy Explanation
            <span v-if="liveVariance !== 0" class="text-red-600 font-bold">* (MANDATORY due to variance)</span>
          </label>
          <textarea
            v-model="closingForm.notes"
            rows="2"
            placeholder="Explain any cash shortage or surplus discrepancy before closing..."
            class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 font-bold text-xs text-slate-900 focus:bg-white focus:outline-none"
          ></textarea>
        </div>

        <div class="flex justify-end pt-2">
          <button
            @click="openConfirmationModal"
            class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md shadow-red-600/20 uppercase tracking-wider flex items-center gap-2 cursor-pointer"
          >
            <span>🔒</span>
            <span>Review & Perform Day Closing</span>
          </button>
        </div>
      </div>

      <!-- 6. EXPANDABLE ITEMIZE DRILL-DOWN TABS -->
      <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden space-y-4">
        <div class="p-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
          <h3 class="font-black text-xs text-slate-900 uppercase tracking-wider">🔍 Today's Itemized Transaction Drill-Down</h3>
          <div class="flex flex-wrap items-center gap-1 text-xs">
            <button @click="activeTab = 'sales'" :class="['px-3 py-1.5 rounded-lg font-bold transition-all cursor-pointer', activeTab === 'sales' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200']">Sales ({{ summary.itemized_details?.sales?.length || 0 }})</button>
            <button @click="activeTab = 'collections'" :class="['px-3 py-1.5 rounded-lg font-bold transition-all cursor-pointer', activeTab === 'collections' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200']">Collections ({{ summary.itemized_details?.collections?.length || 0 }})</button>
            <button @click="activeTab = 'refunds'" :class="['px-3 py-1.5 rounded-lg font-bold transition-all cursor-pointer', activeTab === 'refunds' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200']">Refunds ({{ summary.itemized_details?.refunds?.length || 0 }})</button>
            <button @click="activeTab = 'expenses'" :class="['px-3 py-1.5 rounded-lg font-bold transition-all cursor-pointer', activeTab === 'expenses' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200']">Expenses ({{ summary.itemized_details?.expenses?.length || 0 }})</button>
            <button @click="activeTab = 'suppliers'" :class="['px-3 py-1.5 rounded-lg font-bold transition-all cursor-pointer', activeTab === 'suppliers' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200']">Suppliers ({{ summary.itemized_details?.supplier_payments?.length || 0 }})</button>
          </div>
        </div>

        <div class="p-4 pt-0">
          <!-- TAB A: SALES -->
          <div v-if="activeTab === 'sales'" class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead>
                <tr class="bg-slate-100 text-slate-700 font-extrabold uppercase text-[10px]">
                  <th class="py-2.5 px-3">Invoice #</th>
                  <th class="py-2.5 px-3">Time</th>
                  <th class="py-2.5 px-3">Customer</th>
                  <th class="py-2.5 px-3 text-center">Method</th>
                  <th class="py-2.5 px-3 text-right">Grand Total</th>
                  <th class="py-2.5 px-3 text-right">Paid Amount</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-medium">
                <tr v-for="inv in summary.itemized_details?.sales" :key="inv.id" class="hover:bg-slate-50">
                  <td class="py-2 px-3 font-mono font-bold text-red-600">{{ inv.invoice_number }}</td>
                  <td class="py-2 px-3 text-slate-500 font-mono text-[10px]">{{ formatDate(inv.created_at) }}</td>
                  <td class="py-2 px-3 font-bold text-slate-800">{{ inv.customer_name }}</td>
                  <td class="py-2 px-3 text-center font-bold text-slate-600 uppercase text-[10px]">{{ inv.payment_method }}</td>
                  <td class="py-2 px-3 text-right font-mono font-bold text-slate-900">₹{{ formatCurrency(inv.grand_total) }}</td>
                  <td class="py-2 px-3 text-right font-mono font-bold text-emerald-600">₹{{ formatCurrency(inv.paid_amount) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- TAB B: COLLECTIONS -->
          <div v-if="activeTab === 'collections'" class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead>
                <tr class="bg-slate-100 text-slate-700 font-extrabold uppercase text-[10px]">
                  <th class="py-2.5 px-3">Receipt #</th>
                  <th class="py-2.5 px-3">Customer</th>
                  <th class="py-2.5 px-3">Invoice #</th>
                  <th class="py-2.5 px-3 text-center">Method</th>
                  <th class="py-2.5 px-3 text-right">Amount</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-medium">
                <tr v-for="col in summary.itemized_details?.collections" :key="col.id" class="hover:bg-slate-50">
                  <td class="py-2 px-3 font-mono font-bold text-blue-600">{{ col.payment_number }}</td>
                  <td class="py-2 px-3 font-bold text-slate-800">{{ col.customer_name }}</td>
                  <td class="py-2 px-3 font-mono text-slate-600">{{ col.invoice_number }}</td>
                  <td class="py-2 px-3 text-center font-bold text-slate-600 uppercase text-[10px]">{{ col.payment_method }}</td>
                  <td class="py-2 px-3 text-right font-mono font-black text-blue-600">₹{{ formatCurrency(col.amount) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- TAB C: REFUNDS -->
          <div v-if="activeTab === 'refunds'" class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead>
                <tr class="bg-slate-100 text-slate-700 font-extrabold uppercase text-[10px]">
                  <th class="py-2.5 px-3">Refund #</th>
                  <th class="py-2.5 px-3">Invoice #</th>
                  <th class="py-2.5 px-3">Customer</th>
                  <th class="py-2.5 px-3">Reason</th>
                  <th class="py-2.5 px-3 text-right">Amount</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-medium">
                <tr v-for="refItem in summary.itemized_details?.refunds" :key="refItem.id" class="hover:bg-slate-50">
                  <td class="py-2 px-3 font-mono font-bold text-red-600">{{ refItem.refund_number }}</td>
                  <td class="py-2 px-3 font-mono text-slate-600">{{ refItem.invoice_number }}</td>
                  <td class="py-2 px-3 font-bold text-slate-800">{{ refItem.customer_name }}</td>
                  <td class="py-2 px-3 font-bold text-slate-600">{{ refItem.reason }}</td>
                  <td class="py-2 px-3 text-right font-mono font-black text-red-600">₹{{ formatCurrency(refItem.amount) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- TAB D: EXPENSES -->
          <div v-if="activeTab === 'expenses'" class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead>
                <tr class="bg-slate-100 text-slate-700 font-extrabold uppercase text-[10px]">
                  <th class="py-2.5 px-3">Voucher #</th>
                  <th class="py-2.5 px-3">Category</th>
                  <th class="py-2.5 px-3">Description</th>
                  <th class="py-2.5 px-3 text-center">Method</th>
                  <th class="py-2.5 px-3 text-right">Amount</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-medium">
                <tr v-for="exp in summary.itemized_details?.expenses" :key="exp.id" class="hover:bg-slate-50">
                  <td class="py-2 px-3 font-mono font-bold text-slate-800">{{ exp.voucher_number }}</td>
                  <td class="py-2 px-3 font-bold text-slate-800">{{ exp.category_name }}</td>
                  <td class="py-2 px-3 text-slate-600">{{ exp.description }}</td>
                  <td class="py-2 px-3 text-center font-bold text-slate-600 uppercase text-[10px]">{{ exp.payment_method }}</td>
                  <td class="py-2 px-3 text-right font-mono font-black text-red-600">₹{{ formatCurrency(exp.amount) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- TAB E: SUPPLIERS -->
          <div v-if="activeTab === 'suppliers'" class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead>
                <tr class="bg-slate-100 text-slate-700 font-extrabold uppercase text-[10px]">
                  <th class="py-2.5 px-3">Payment #</th>
                  <th class="py-2.5 px-3">Supplier</th>
                  <th class="py-2.5 px-3">Purchase Bill #</th>
                  <th class="py-2.5 px-3 text-center">Method</th>
                  <th class="py-2.5 px-3 text-right">Amount</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-medium">
                <tr v-for="sp in summary.itemized_details?.supplier_payments" :key="sp.id" class="hover:bg-slate-50">
                  <td class="py-2 px-3 font-mono font-bold text-slate-800">{{ sp.payment_number }}</td>
                  <td class="py-2 px-3 font-bold text-slate-800">{{ sp.supplier_name }}</td>
                  <td class="py-2 px-3 font-mono text-slate-600">{{ sp.purchase_bill_number }}</td>
                  <td class="py-2 px-3 text-center font-bold text-slate-600 uppercase text-[10px]">{{ sp.payment_method }}</td>
                  <td class="py-2 px-3 text-right font-mono font-black text-slate-900">₹{{ formatCurrency(sp.amount) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- 7. CLOSED DAYS LEDGER TABLE -->
      <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
          <h3 class="font-black text-xs text-slate-900 uppercase tracking-wider">📜 Closed Days Ledger</h3>
          <span class="text-xs text-slate-500 font-bold">Total Closings: {{ closingsList.length }}</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="bg-slate-900 text-white font-extrabold uppercase tracking-wider text-[11px]">
                <th class="py-3.5 px-4">Closing # & Date</th>
                <th class="py-3.5 px-4 text-right font-mono">Gross Sales</th>
                <th class="py-3.5 px-4 text-right font-mono">Net Sales</th>
                <th class="py-3.5 px-4 text-right font-mono">Expected Cash</th>
                <th class="py-3.5 px-4 text-right font-mono">Actual Cash</th>
                <th class="py-3.5 px-4 text-right font-mono">Variance</th>
                <th class="py-3.5 px-4 text-center">Closed By</th>
                <th class="py-3.5 px-4 text-center">Status</th>
                <th class="py-3.5 px-4 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium">
              <tr v-if="loadingClosings">
                <td colspan="9" class="text-center py-12 text-slate-400 font-bold">
                  Loading closed days ledger...
                </td>
              </tr>
              <tr v-else-if="closingsList.length === 0">
                <td colspan="9" class="text-center py-12 text-slate-500 font-bold">
                  No closed business days recorded yet.
                </td>
              </tr>
              <tr v-for="c in closingsList" :key="c.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="py-3 px-4">
                  <div class="font-mono font-black text-red-600 text-xs">{{ c.closing_number }}</div>
                  <div class="text-[10px] text-slate-500 font-bold">{{ formatDateOnly(c.closing_date) }}</div>
                </td>
                <td class="py-3 px-4 text-right font-mono font-bold text-slate-700">
                  ₹{{ formatCurrency(c.snapshot_data?.sales_summary?.gross_sales || c.total_sales_grand) }}
                </td>
                <td class="py-3 px-4 text-right font-mono font-bold text-slate-900">
                  ₹{{ formatCurrency(c.total_sales_grand) }}
                </td>
                <td class="py-3 px-4 text-right font-mono font-bold text-slate-700">
                  ₹{{ formatCurrency(c.expected_cash) }}
                </td>
                <td class="py-3 px-4 text-right font-mono font-black text-slate-900">
                  ₹{{ formatCurrency(c.actual_cash) }}
                </td>
                <td class="py-3 px-4 text-right font-mono font-black" :class="Number(c.variance) === 0 ? 'text-emerald-600' : Number(c.variance) < 0 ? 'text-red-600' : 'text-amber-600'">
                  {{ Number(c.variance) > 0 ? '+' : '' }}₹{{ formatCurrency(c.variance) }}
                </td>
                <td class="py-3 px-4 text-center font-bold text-slate-700">
                  {{ c.closer?.name || 'Manager' }}
                </td>
                <td class="py-3 px-4 text-center">
                  <span :class="c.status === 'closed' ? 'px-2 py-0.5 rounded-full bg-slate-900 text-white text-[10px] font-black uppercase' : 'px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-black uppercase'">
                    {{ (c.status || 'CLOSED').toUpperCase() }}
                  </span>
                </td>
                <td class="py-3 px-4 text-center">
                  <div class="flex items-center justify-center gap-1.5">
                    <button @click="printClosingReport(c)" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg text-[10px] font-black cursor-pointer">
                      🖨️ Report
                    </button>
                    <button v-if="c.status === 'closed'" @click="openReopenModal(c)" class="px-2 py-1 bg-amber-100 hover:bg-amber-200 text-amber-900 rounded-lg text-[10px] font-black cursor-pointer">
                      🔓 Reopen
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <!-- PRE-CLOSING CONFIRMATION MODAL -->
    <div v-if="showConfirmModal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto font-sans">
      <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-5 my-8">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h2 class="text-lg font-black text-slate-900">🔒 Confirm Day Closing — {{ selectedDate }}</h2>
          <button @click="showConfirmModal = false" class="text-slate-400 hover:text-slate-600 font-bold cursor-pointer">✕</button>
        </div>

        <div class="space-y-2 text-xs font-mono bg-slate-50 p-4 rounded-2xl border border-slate-200">
          <div class="flex justify-between"><span>Business Date:</span><span class="font-bold text-slate-900">{{ selectedDate }}</span></div>
          <div class="flex justify-between"><span>Store:</span><span class="font-bold text-slate-900">RUPSA PADUKALAYA (STR-001)</span></div>
          <div class="flex justify-between"><span>Net Sales:</span><span class="font-bold text-emerald-600">₹{{ formatCurrency(summary.sales_summary?.net_sales) }}</span></div>
          <div class="flex justify-between"><span>Cash Sales:</span><span class="font-bold text-slate-900">₹{{ formatCurrency(summary.sales_summary?.cash_sales) }}</span></div>
          <div class="flex justify-between"><span>Cash Collections:</span><span class="font-bold text-blue-600">₹{{ formatCurrency(summary.customer_collections?.cash_collections) }}</span></div>
          <div class="flex justify-between"><span>Cash Refunds:</span><span class="font-bold text-red-600">-₹{{ formatCurrency(summary.refunds_summary?.cash_refunds) }}</span></div>
          <div class="flex justify-between"><span>Cash Expenses:</span><span class="font-bold text-red-600">-₹{{ formatCurrency(summary.expenses_summary?.cash_expenses) }}</span></div>
          <div class="flex justify-between"><span>Cash Supplier Payments:</span><span class="font-bold text-red-600">-₹{{ formatCurrency(summary.supplier_summary?.cash_supplier_payments) }}</span></div>
          <div class="flex justify-between border-t border-slate-200 pt-2 font-black text-slate-900 text-sm">
            <span>Expected System Cash:</span>
            <span>₹{{ formatCurrency(summary.cash_drawer?.expected_cash) }}</span>
          </div>
          <div class="flex justify-between font-black text-slate-900 text-sm">
            <span>Actual Counted Cash:</span>
            <span>₹{{ formatCurrency(actualCountedCash) }}</span>
          </div>
          <div class="flex justify-between font-black text-sm" :class="liveVariance === 0 ? 'text-emerald-600' : 'text-red-600'">
            <span>Cash Variance:</span>
            <span>{{ liveVariance > 0 ? '+' : '' }}₹{{ formatCurrency(liveVariance) }}</span>
          </div>
        </div>

        <p class="text-[11px] text-slate-500 font-medium">
          Confirming will lock register transactions for {{ selectedDate }} and record an immutable financial snapshot.
        </p>

        <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
          <button @click="showConfirmModal = false" type="button" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl font-bold text-xs cursor-pointer">Back</button>
          <button @click="submitDayClosing" :disabled="saving" type="button" class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md shadow-red-600/20 uppercase tracking-wider disabled:opacity-50 cursor-pointer">
            {{ saving ? 'Locking Business Day...' : 'Confirm & Finalize Day Closing' }}
          </button>
        </div>
      </div>
    </div>

    <!-- REOPEN MODAL -->
    <div v-if="showReopenModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto font-sans">
      <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-6 my-8">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
          <h2 class="text-lg font-black text-slate-900">🔓 Reopen Business Day</h2>
          <button @click="showReopenModal = false" class="text-slate-400 hover:text-slate-600 font-bold cursor-pointer">✕</button>
        </div>

        <div class="space-y-3 text-xs">
          <p class="text-slate-600 font-medium">
            Reopening Day Closing <strong>#{{ selectedReopenClosing?.closing_number }}</strong> will allow modifications to register transactions.
          </p>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Audit Reason for Reopening <span class="text-red-600">*</span></label>
            <textarea v-model="reopenReason" rows="3" placeholder="State audit reason..." class="w-full bg-slate-50 border rounded-xl p-3 font-bold text-slate-900 focus:bg-white"></textarea>
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
          <button @click="showReopenModal = false" type="button" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl font-bold text-xs cursor-pointer">Cancel</button>
          <button @click="submitReopen" :disabled="saving" type="button" class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl font-black text-xs shadow-xs uppercase tracking-wider disabled:opacity-50 cursor-pointer">
            {{ saving ? 'Reopening...' : 'Confirm & Reopen Day' }}
          </button>
        </div>
      </div>
    </div>

    <!-- PRINT COMPREHENSIVE A4 REPORT TELEPORT -->
    <Teleport to="body" v-if="selectedPrintReport">
      <div id="day-closing-print-root" class="hidden print:block p-8 font-sans text-black max-w-4xl mx-auto bg-white">
        <div class="text-center border-b-2 border-black pb-4 mb-4">
          <h1 class="text-2xl font-black uppercase">RUPSA PADUKALAYA</h1>
          <p class="text-xs font-bold">Main Outlet — STR-001 | Phone: 9876543210</p>
          <p class="text-xs font-bold uppercase mt-1 text-slate-800">COMPLETE END-OF-DAY FINANCIAL RECONCILIATION REPORT</p>
        </div>

        <div class="grid grid-cols-2 gap-4 text-xs font-bold border-b border-black pb-4 mb-4 font-mono">
          <div>
            <div>Closing #: {{ selectedPrintReport.closing_number }}</div>
            <div>Business Date: {{ formatDateOnly(selectedPrintReport.closing_date) }}</div>
            <div>Store: RUPSA PADUKALAYA (STR-001)</div>
          </div>
          <div class="text-right">
            <div>Closed By: {{ selectedPrintReport.closer?.name || 'Manager' }}</div>
            <div>Status: {{ (selectedPrintReport.status || 'CLOSED').toUpperCase() }}</div>
            <div>Generated At: {{ formatDate(selectedPrintReport.created_at) }}</div>
          </div>
        </div>

        <!-- PRINT SECTION 1: SALES & COLLECTIONS SUMMARY -->
        <div class="mb-4">
          <h3 class="font-black text-xs uppercase border-b border-black pb-1 mb-2">1. SALES & COLLECTIONS SUMMARY</h3>
          <table class="w-full text-xs border border-black font-mono mb-4">
            <thead>
              <tr class="bg-black text-white font-bold uppercase">
                <th class="p-1.5 text-left">Category</th>
                <th class="p-1.5 text-right">Gross</th>
                <th class="p-1.5 text-right">Discount</th>
                <th class="p-1.5 text-right">Tax</th>
                <th class="p-1.5 text-right">Net Amount</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-black font-bold">
              <tr>
                <td class="p-1.5">Net Sales</td>
                <td class="p-1.5 text-right">₹{{ formatCurrency(selectedPrintReport.snapshot_data?.sales_summary?.gross_sales || selectedPrintReport.total_sales_grand) }}</td>
                <td class="p-1.5 text-right">₹{{ formatCurrency(selectedPrintReport.snapshot_data?.sales_summary?.discount_total) }}</td>
                <td class="p-1.5 text-right">₹{{ formatCurrency(selectedPrintReport.snapshot_data?.sales_summary?.tax_total) }}</td>
                <td class="p-1.5 text-right">₹{{ formatCurrency(selectedPrintReport.total_sales_grand) }}</td>
              </tr>
              <tr>
                <td class="p-1.5">Customer Due Collections</td>
                <td class="p-1.5 text-right">—</td>
                <td class="p-1.5 text-right">—</td>
                <td class="p-1.5 text-right">—</td>
                <td class="p-1.5 text-right">₹{{ formatCurrency(selectedPrintReport.total_collections_grand) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- PRINT SECTION 2: DIGITAL & CASH RECONCILIATION MATRIX -->
        <div class="mb-4">
          <h3 class="font-black text-xs uppercase border-b border-black pb-1 mb-2">2. TENDER & DIGITAL RECONCILIATION MATRIX</h3>
          <table class="w-full text-xs border border-black font-mono mb-4">
            <thead>
              <tr class="bg-black text-white font-bold uppercase">
                <th class="p-1.5 text-left">Method</th>
                <th class="p-1.5 text-right">Sales</th>
                <th class="p-1.5 text-right">Collections</th>
                <th class="p-1.5 text-right">Refunds</th>
                <th class="p-1.5 text-right">Expenses</th>
                <th class="p-1.5 text-right">Supplier Payouts</th>
                <th class="p-1.5 text-right">Net</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-black font-bold">
              <tr v-for="row in selectedPrintReport.snapshot_data?.digital_reconciliation_matrix" :key="row.method">
                <td class="p-1.5 font-sans">{{ row.method }}</td>
                <td class="p-1.5 text-right">₹{{ formatCurrency(row.sales) }}</td>
                <td class="p-1.5 text-right">₹{{ formatCurrency(row.collections) }}</td>
                <td class="p-1.5 text-right">₹{{ formatCurrency(row.refunds) }}</td>
                <td class="p-1.5 text-right">₹{{ formatCurrency(row.expenses) }}</td>
                <td class="p-1.5 text-right">₹{{ formatCurrency(row.supplier_payments) }}</td>
                <td class="p-1.5 text-right font-black">₹{{ formatCurrency(row.net_movement) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- PRINT SECTION 3: EXPENSE BREAKDOWN -->
        <div v-if="selectedPrintReport.snapshot_data?.expenses_summary?.category_breakdown?.length" class="mb-4">
          <h3 class="font-black text-xs uppercase border-b border-black pb-1 mb-2">3. EXPENSE CATEGORY BREAKDOWN</h3>
          <table class="w-full text-xs border border-black font-mono mb-4">
            <thead>
              <tr class="bg-black text-white font-bold uppercase">
                <th class="p-1.5 text-left">Category</th>
                <th class="p-1.5 text-center">Count</th>
                <th class="p-1.5 text-right">Amount (₹)</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-black font-bold">
              <tr v-for="cat in selectedPrintReport.snapshot_data?.expenses_summary?.category_breakdown" :key="cat.name">
                <td class="p-1.5 font-sans">{{ cat.name }}</td>
                <td class="p-1.5 text-center">{{ cat.count }}</td>
                <td class="p-1.5 text-right">₹{{ formatCurrency(cat.total_amount) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- PRINT SECTION 4: CASH DRAWER & RECONCILIATION -->
        <div class="mb-4">
          <h3 class="font-black text-xs uppercase border-b border-black pb-1 mb-2">4. CASH DRAWER & PHYSICAL COUNT RECONCILIATION</h3>
          <div class="grid grid-cols-2 gap-4 text-xs font-mono border border-black p-3 mb-4">
            <div>
              <div>Opening Float: ₹{{ formatCurrency(selectedPrintReport.opening_cash) }}</div>
              <div>+ Cash Sales: ₹{{ formatCurrency(selectedPrintReport.total_sales_cash) }}</div>
              <div>+ Cash Collections: ₹{{ formatCurrency(selectedPrintReport.total_collections_cash) }}</div>
              <div>- Cash Refunds: ₹{{ formatCurrency(selectedPrintReport.total_refunds_cash) }}</div>
              <div>- Cash Expenses: ₹{{ formatCurrency(selectedPrintReport.total_expenses) }}</div>
            </div>
            <div class="border-l border-black pl-4">
              <div class="font-black">EXPECTED SYSTEM CASH: ₹{{ formatCurrency(selectedPrintReport.expected_cash) }}</div>
              <div class="font-black">ACTUAL PHYSICAL CASH: ₹{{ formatCurrency(selectedPrintReport.actual_cash) }}</div>
              <div class="font-black text-sm border-t border-black pt-1 mt-1">VARIANCE: ₹{{ formatCurrency(selectedPrintReport.variance) }}</div>
            </div>
          </div>
        </div>

        <div v-if="selectedPrintReport.notes" class="border border-black p-3 text-xs mb-6 font-mono">
          <strong>Discrepancy Notes / Explanation:</strong> {{ selectedPrintReport.notes }}
        </div>

        <div class="flex justify-between items-end pt-12 text-xs font-bold">
          <div>Prepared By (Cashier / Manager)</div>
          <div>Verified By (Store Admin)</div>
          <div>Authorized Signature / RUPSA PADUKALAYA</div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import api from '../../services/api';

const loadingSummary = ref(false);
const loadingClosings = ref(false);
const saving = ref(false);
const modalError = ref('');

const selectedDate = ref(new Date().toISOString().slice(0, 10));
const selectedStoreId = ref(1);
const activeTab = ref('sales');

const notification = reactive({ show: false, message: '', type: 'success' });

const summary = ref({
  sales_summary: {},
  customer_collections: {},
  refunds_summary: {},
  supplier_summary: {},
  expenses_summary: {},
  cash_drawer: {},
  digital_reconciliation_matrix: [],
  high_level_financial_summary: {},
  transaction_counts: {},
  itemized_details: { sales: [], collections: [], refunds: [], expenses: [], supplier_payments: [] },
});

const closingsList = ref([]);

const denom = reactive({
  d500: 0,
  d200: 0,
  d100: 0,
  d50: 0,
  d20: 0,
  d10: 0,
  coins: 0,
  other: 0,
});

const closingForm = reactive({ notes: '' });

const showConfirmModal = ref(false);
const showReopenModal = ref(false);
const selectedReopenClosing = ref(null);
const reopenReason = ref('');
const selectedPrintReport = ref(null);

const actualCountedCash = computed(() => {
  const c500 = Number(denom.d500 || 0) * 500;
  const c200 = Number(denom.d200 || 0) * 200;
  const c100 = Number(denom.d100 || 0) * 100;
  const c50 = Number(denom.d50 || 0) * 50;
  const c20 = Number(denom.d20 || 0) * 20;
  const c10 = Number(denom.d10 || 0) * 10;
  const coins = Number(denom.coins || 0);
  const other = Number(denom.other || 0);
  return Math.round((c500 + c200 + c100 + c50 + c20 + c10 + coins + other) * 100) / 100;
});

const liveVariance = computed(() => {
  const exp = Number(summary.value.cash_drawer?.expected_cash || 0);
  return Math.round((actualCountedCash.value - exp) * 100) / 100;
});

function showToast(msg, type = 'success') {
  notification.message = msg;
  notification.type = type;
  notification.show = true;
  setTimeout(() => { notification.show = false; }, 4000);
}

async function fetchDailySummary() {
  loadingSummary.value = true;
  try {
    const res = await api.get('/payments/day-closing/summary', {
      params: { store_id: selectedStoreId.value, closing_date: selectedDate.value },
    });
    summary.value = res.data?.data || res.data;
  } catch (err) {
    console.error('Failed to fetch comprehensive day closing summary:', err);
  } finally {
    loadingSummary.value = false;
  }
}

async function fetchClosings() {
  loadingClosings.value = true;
  try {
    const res = await api.get('/payments/day-closing', { params: { store_id: selectedStoreId.value } });
    const payload = res.data?.data || res.data;
    closingsList.value = payload.items || [];
  } catch (err) {
    console.error('Failed to fetch closed days ledger:', err);
  } finally {
    loadingClosings.value = false;
  }
}

function openConfirmationModal() {
  if (liveVariance.value !== 0 && (!closingForm.notes || closingForm.notes.trim().length < 3)) {
    showToast('Closing notes / discrepancy explanation is mandatory when cash variance is not zero.', 'error');
    return;
  }
  showConfirmModal.value = true;
}

async function submitDayClosing() {
  if (saving.value) return;

  saving.value = true;
  try {
    await api.post('/payments/day-closing', {
      store_id: selectedStoreId.value,
      closing_date: selectedDate.value,
      actual_cash: actualCountedCash.value,
      notes: closingForm.notes,
      denomination_breakdown: { ...denom },
    });
    showConfirmModal.value = false;
    showToast('Day closing completed & business date locked successfully!', 'success');
    fetchDailySummary();
    fetchClosings();
  } catch (err) {
    showToast(err.response?.data?.message || 'Failed to perform day closing.', 'error');
  } finally {
    saving.value = false;
  }
}

function openReopenModal(c) {
  modalError.value = '';
  selectedReopenClosing.value = c;
  reopenReason.value = '';
  showReopenModal.value = true;
}

async function submitReopen() {
  if (saving.value) return;
  modalError.value = '';

  if (!reopenReason.value.trim()) {
    modalError.value = 'Please enter a valid audit reason for reopening day closing.';
    return;
  }

  saving.value = true;
  try {
    await api.post(`/payments/day-closing/${selectedReopenClosing.value.id}/reopen`, {
      reason: reopenReason.value,
    });
    showReopenModal.value = false;
    showToast('Day closing reopened successfully!', 'success');
    fetchClosings();
  } catch (err) {
    modalError.value = err.response?.data?.message || 'Failed to reopen day closing.';
  } finally {
    saving.value = false;
  }
}

function printClosingReport(c) {
  selectedPrintReport.value = c;
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

function formatDateOnly(dt) {
  if (!dt) return '—';
  return new Date(dt).toLocaleDateString('en-IN', { dateStyle: 'medium' });
}

onMounted(() => {
  fetchDailySummary();
  fetchClosings();
});
</script>
