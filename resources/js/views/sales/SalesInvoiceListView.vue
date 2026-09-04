<template>
  <div class="space-y-6 antialiased font-sans max-w-7xl mx-auto pb-16">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Sales Invoices</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          POS sales receipts, customer purchase history, GST breakdowns & billing receipts for Main Outlet (STR-001).
        </p>
      </div>
      <router-link
        to="/admin/pos"
        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white font-black text-xs rounded-xl shadow-md shadow-red-600/20 transition-all uppercase tracking-wider shrink-0"
      >
        <span>🖥️</span>
        <span>Open POS Terminal</span>
      </router-link>
    </div>

    <!-- Filters & Search Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex flex-col md:flex-row items-center justify-between gap-4 text-xs">
      <!-- Search Input -->
      <div class="relative w-full md:w-96">
        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
        <input
          type="text"
          v-model="filters.search"
          @input="debouncedSearch"
          placeholder="Search invoice #, customer name, mobile, article #..."
          class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-4 py-2.5 font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
        />
      </div>

      <div class="flex items-center gap-3 w-full md:w-auto overflow-x-auto">
        <!-- Payment Status Filter -->
        <select
          v-model="filters.payment_status"
          @change="fetchInvoices(1)"
          class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-red-600"
        >
          <option value="">All Payment Statuses</option>
          <option value="paid">Paid</option>
          <option value="partial">Partial</option>
          <option value="unpaid">Unpaid</option>
        </select>

        <!-- Sale Status Filter -->
        <select
          v-model="filters.status"
          @change="fetchInvoices(1)"
          class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-red-600"
        >
          <option value="">All Invoice Statuses</option>
          <option value="completed">Completed</option>
          <option value="partially_returned">Partially Returned</option>
          <option value="returned">Fully Returned</option>
          <option value="cancelled">Cancelled</option>
        </select>

        <!-- Payment Method Filter -->
        <select
          v-model="filters.payment_method"
          @change="fetchInvoices(1)"
          class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-red-600"
        >
          <option value="">All Methods</option>
          <option value="cash">Cash</option>
          <option value="upi">UPI / GPay</option>
          <option value="card">Card</option>
          <option value="store_credit">Store Credit</option>
        </select>

        <button
          @click="fetchInvoices(1)"
          class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition-colors shrink-0"
        >
          🔄 Refresh
        </button>
      </div>
    </div>

    <!-- Skeleton Loading -->
    <div v-if="loading" class="bg-white rounded-2xl border border-slate-200/90 p-6 space-y-4 shadow-xs">
      <div v-for="i in 5" :key="i" class="animate-pulse flex items-center justify-between gap-4">
        <div class="h-4 bg-slate-200 rounded w-1/4"></div>
        <div class="h-4 bg-slate-200 rounded w-1/6"></div>
        <div class="h-4 bg-slate-200 rounded w-1/6"></div>
        <div class="h-4 bg-slate-200 rounded w-1/8"></div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="p-6 bg-red-50 border border-red-200 rounded-2xl text-center space-y-3">
      <span class="text-2xl">⚠️</span>
      <h3 class="font-black text-sm text-red-900">Unable to load sales invoices</h3>
      <p class="text-xs text-red-700 font-medium max-w-md mx-auto">{{ error }}</p>
      <button @click="fetchInvoices(1)" class="px-4 py-2 bg-red-600 text-white font-bold rounded-xl text-xs shadow-xs">
        Try Again
      </button>
    </div>

    <!-- Empty State -->
    <div v-else-if="invoices.length === 0" class="p-12 bg-white rounded-2xl border border-slate-200/90 text-center space-y-4 shadow-xs">
      <div class="h-16 w-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto text-3xl">
        🧾
      </div>
      <div class="space-y-1">
        <h3 class="font-black text-base text-slate-900">No Sales Invoices Found</h3>
        <p class="text-xs text-slate-500 max-w-sm mx-auto">
          No POS sales invoices match your active query or search criteria.
        </p>
      </div>
    </div>

    <!-- Mobile Cards List View (< md) -->
    <div v-else class="space-y-3 md:hidden">
      <MobileListCard
        v-for="inv in invoices"
        :key="inv.id"
        :title="inv.invoice_number"
        :subtitle="formatDateTime(inv.created_at) + ' • ' + (inv.customer?.name || 'Walk-in Customer')"
        :status="inv.payment_status"
        :status-type="inv.payment_status === 'paid' ? 'success' : inv.payment_status === 'partial' ? 'warning' : 'danger'"
        :metric="'₹' + formatCurrency(inv.grand_total)"
      >
        <div class="flex items-center justify-between text-xs font-mono">
          <span class="text-slate-500">Items (Qty):</span>
          <span class="font-bold text-slate-900">{{ inv.items_count || inv.items?.length || 0 }} items ({{ getItemTotalQty(inv) }} pcs)</span>
        </div>
        <div class="flex items-center justify-between text-xs font-mono">
          <span class="text-slate-500">Paid Amount:</span>
          <span class="font-bold text-emerald-600">₹{{ formatCurrency(inv.paid_amount) }}</span>
        </div>

        <template #actions>
          <button
            @click="openInvoiceModal(inv)"
            class="px-3 py-1.5 bg-slate-900 text-white rounded-lg font-bold text-xs cursor-pointer flex items-center gap-1"
          >
            <span>🔍 View</span>
          </button>
          <button
            @click="confirmDeleteInvoice(inv)"
            class="px-2.5 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 rounded-lg font-bold text-xs cursor-pointer flex items-center gap-1"
          >
            <span>🗑️ Delete</span>
          </button>
        </template>
      </MobileListCard>
    </div>

    <!-- Desktop DataTable View (>= md) -->
    <div v-if="invoices.length > 0" class="hidden md:block bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
              <th class="py-3.5 px-4">Invoice #</th>
              <th class="py-3.5 px-4">Date & Time</th>
              <th class="py-3.5 px-4">Customer Details</th>
              <th class="py-3.5 px-4 text-center">Items (Qty)</th>
              <th class="py-3.5 px-4 text-right">Grand Total</th>
              <th class="py-3.5 px-4 text-right">Paid Amount</th>
              <th class="py-3.5 px-4 text-center">Payment Status</th>
              <th class="py-3.5 px-4 text-center">Methods</th>
              <th class="py-3.5 px-4 text-center">Status</th>
              <th class="py-3.5 px-4 text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
            <tr v-for="inv in invoices" :key="inv.id" class="hover:bg-slate-50/80 transition-colors">
              <!-- Invoice # -->
              <td class="py-3.5 px-4">
                <button @click="openInvoiceModal(inv)" class="font-mono font-black text-red-600 hover:underline">
                  {{ inv.invoice_number }}
                </button>
              </td>

              <!-- Date & Time -->
              <td class="py-3.5 px-4 font-mono text-[11px] text-slate-600">
                {{ formatDateTime(inv.created_at) }}
              </td>

              <!-- Customer Details -->
              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900">{{ inv.customer?.name || 'Walk-in Customer' }}</div>
                <div v-if="inv.customer?.mobile_number" class="text-[10px] font-mono text-slate-400">
                  {{ inv.customer.mobile_number }}
                </div>
              </td>

              <!-- Items & Quantity -->
              <td class="py-3.5 px-4 text-center">
                <span class="px-2 py-0.5 bg-slate-100 border border-slate-200 rounded font-mono font-bold text-slate-800 text-[11px]">
                  {{ inv.items_count || inv.items?.length || 0 }} ({{ getItemTotalQty(inv) }} pcs)
                </span>
              </td>

              <!-- Grand Total -->
              <td class="py-3.5 px-4 text-right font-mono font-black text-slate-900 text-sm">
                ₹{{ formatCurrency(inv.grand_total) }}
              </td>

              <!-- Paid Amount -->
              <td class="py-3.5 px-4 text-right font-mono font-bold text-emerald-700">
                ₹{{ formatCurrency(inv.paid_amount) }}
              </td>

              <!-- Payment Status -->
              <td class="py-3.5 px-4 text-center">
                <span
                  :class="[
                    'px-2 py-0.5 rounded text-[9px] font-black uppercase border',
                    inv.payment_status === 'paid' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' :
                    inv.payment_status === 'partial' ? 'bg-amber-50 text-amber-800 border-amber-200' :
                    'bg-red-50 text-red-800 border-red-200'
                  ]"
                >
                  {{ inv.payment_status || 'Paid' }}
                </span>
              </td>

              <!-- Payment Methods -->
              <td class="py-3.5 px-4 text-center">
                <div class="flex items-center justify-center gap-1 flex-wrap">
                  <span
                    v-for="pm in (inv.payments || [])"
                    :key="pm.id"
                    class="px-1.5 py-0.5 bg-slate-100 border border-slate-200 rounded text-[9px] font-mono uppercase font-bold text-slate-700"
                  >
                    {{ pm.payment_method }}
                  </span>
                  <span v-if="!inv.payments || inv.payments.length === 0" class="text-[10px] text-slate-400">Cash</span>
                </div>
              </td>

              <!-- Sale Status -->
              <td class="py-3.5 px-4 text-center">
                <span
                  :class="[
                    'px-2 py-0.5 rounded text-[9px] font-black uppercase border',
                    inv.status === 'completed' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' :
                    inv.status === 'partially_returned' ? 'bg-amber-50 text-amber-800 border-amber-200' :
                    inv.status === 'returned' ? 'bg-purple-50 text-purple-800 border-purple-200' :
                    'bg-slate-100 text-slate-600 border-slate-200'
                  ]"
                >
                  {{ inv.status }}
                </span>
              </td>

              <!-- Actions -->
              <td class="py-3.5 px-4 text-center">
                <div class="flex items-center justify-center gap-1.5 whitespace-nowrap">
                  <button
                    @click="openInvoiceModal(inv)"
                    class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-bold text-[11px] transition-colors"
                  >
                    👁️ Details
                  </button>

                  <button
                    @click="openThermalReceipt(inv)"
                    class="px-2.5 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 rounded-lg font-bold text-[11px] border border-red-200 transition-colors"
                  >
                    🧾 Receipt
                  </button>

                  <button
                    @click="confirmDeleteInvoice(inv)"
                    class="px-2.5 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg font-bold text-[11px] transition-colors shadow-xs"
                    title="Safe Sale Transaction Reversal"
                  >
                    🗑️ Delete
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="p-4 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs font-bold text-slate-600">
        <div class="flex items-center gap-3">
          <span>Rows per page:</span>
          <select
            v-model="pagination.per_page"
            @change="fetchInvoices(1)"
            class="bg-white border border-slate-200 rounded-lg px-2 py-1 text-xs text-slate-800 font-bold focus:ring-2 focus:ring-red-600"
          >
            <option :value="10">10 per page</option>
            <option :value="15">15 per page</option>
            <option :value="25">25 per page</option>
            <option :value="50">50 per page</option>
          </select>
          <span>Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} Total Invoices)</span>
        </div>

        <div class="flex items-center gap-2">
          <button
            @click="fetchInvoices(pagination.current_page - 1)"
            :disabled="pagination.current_page === 1 || loading"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg disabled:opacity-30 hover:bg-slate-100 transition-colors"
          >
            ← Previous
          </button>
          <span class="px-3 py-1.5 font-mono font-bold bg-slate-100 rounded-lg border border-slate-200 text-slate-900">
            {{ pagination.current_page }} / {{ pagination.last_page }}
          </span>
          <button
            @click="fetchInvoices(pagination.current_page + 1)"
            :disabled="pagination.current_page === pagination.last_page || loading"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg disabled:opacity-30 hover:bg-slate-100 transition-colors"
          >
            Next →
          </button>
        </div>
      </div>
    </div>

    <!-- INVOICE DETAILS MODAL -->
    <div v-if="selectedInvoice" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
      <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-4xl w-full p-6 sm:p-8 space-y-6 max-h-[90vh] overflow-y-auto text-xs">
        <div class="flex items-center justify-between border-b border-slate-200 pb-4">
          <div>
            <h3 class="text-lg font-black text-slate-900">Sales Invoice: {{ selectedInvoice.invoice_number }}</h3>
            <p class="text-xs text-slate-500 font-medium mt-0.5">
              Issued on {{ formatDateTime(selectedInvoice.created_at) }} • Main Outlet (STR-001)
            </p>
          </div>
          <div class="flex items-center gap-2">
            <button
              @click="openThermalReceipt(selectedInvoice)"
              class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold text-xs shadow-xs transition-colors flex items-center gap-1"
            >
              <span>🧾</span>
              <span>80mm Thermal Receipt</span>
            </button>
            <button @click="selectedInvoice = null" class="p-2 rounded-xl text-slate-400 hover:text-slate-800 font-black text-lg">✕</button>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200/80">
          <div class="space-y-1">
            <span class="font-bold text-slate-400 uppercase text-[10px] tracking-wider block">Store & Customer</span>
            <div class="font-black text-slate-900 text-sm">RUPSA PADUKALAYA - Main Outlet (STR-001)</div>
            <div class="font-bold text-slate-800">Customer: {{ selectedInvoice.customer?.name || 'Walk-in Customer' }}</div>
            <div v-if="selectedInvoice.customer?.mobile_number" class="font-mono text-slate-600">Mobile: {{ selectedInvoice.customer.mobile_number }}</div>
          </div>

          <div class="space-y-1 text-left sm:text-right">
            <span class="font-bold text-slate-400 uppercase text-[10px] tracking-wider block">Billing Meta</span>
            <div class="font-bold text-slate-900">Sale Status: {{ selectedInvoice.status }}</div>
            <div class="font-bold text-emerald-700">Payment Status: {{ selectedInvoice.payment_status || 'Paid' }}</div>
            <div class="font-mono text-slate-500">Cashier: {{ selectedInvoice.creator?.name || 'Staff' }}</div>
          </div>
        </div>

        <!-- Itemized Footwear Table -->
        <div class="space-y-2">
          <h4 class="font-black text-slate-900 uppercase tracking-wider text-[11px]">Purchased Footwear Articles</h4>
          <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-100 border-b border-slate-200 text-[10px] font-black text-slate-600 uppercase">
                <tr>
                  <th class="py-2.5 px-3">Article #</th>
                  <th class="py-2.5 px-3">Product Name</th>
                  <th class="py-2.5 px-3">Color</th>
                  <th class="py-2.5 px-3">Size (IND)</th>
                  <th class="py-2.5 px-3 text-center">Qty</th>
                  <th class="py-2.5 px-3 text-right">MRP (₹)</th>
                  <th class="py-2.5 px-3 text-right">Selling Price (₹)</th>
                  <th class="py-2.5 px-3 text-right">Line Total (₹)</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                <tr v-for="item in (selectedInvoice.items || [])" :key="item.id">
                  <td class="py-2.5 px-3 font-mono font-bold text-red-600">{{ getItemArticle(item) }}</td>
                  <td class="py-2.5 px-3 font-bold text-slate-900">{{ getItemProductName(item) }}</td>
                  <td class="py-2.5 px-3">{{ getItemColorName(item) }}</td>
                  <td class="py-2.5 px-3 font-bold">
                    <span class="px-1.5 py-0.5 bg-slate-100 border border-slate-200 rounded text-[10px]">
                      IND {{ getItemSizeNumber(item) }}
                    </span>
                  </td>
                  <td class="py-2.5 px-3 text-center font-bold font-mono">{{ item.quantity }}</td>
                  <td class="py-2.5 px-3 text-right font-mono text-slate-400">₹{{ formatCurrency(item.mrp || item.unit_price) }}</td>
                  <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900">₹{{ formatCurrency(item.unit_price) }}</td>
                  <td class="py-2.5 px-3 text-right font-mono font-black text-slate-900">₹{{ formatCurrency(item.subtotal) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Financial Summary -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-2">
          <!-- Payment Log -->
          <div class="space-y-2">
            <h4 class="font-black text-slate-900 uppercase tracking-wider text-[11px]">Payment Breakdowns</h4>
            <div class="space-y-1.5 bg-slate-50 p-3 rounded-xl border border-slate-200">
              <div v-for="pay in (selectedInvoice.payments || [])" :key="pay.id" class="flex justify-between items-center text-xs font-medium">
                <span class="font-bold uppercase text-slate-700">{{ pay.payment_method }}:</span>
                <span class="font-mono font-bold text-emerald-700">₹{{ formatCurrency(pay.amount) }}</span>
              </div>
              <div v-if="!selectedInvoice.payments || selectedInvoice.payments.length === 0" class="text-xs text-slate-500 font-medium">
                Cash Payment: ₹{{ formatCurrency(selectedInvoice.paid_amount) }}
              </div>
            </div>
          </div>

          <!-- Total Calculation -->
          <div class="space-y-2 text-right">
            <div class="space-y-1 bg-slate-50 p-4 rounded-xl border border-slate-200">
              <div class="flex justify-between text-xs text-slate-600">
                <span>Subtotal:</span>
                <span class="font-mono font-bold">₹{{ formatCurrency(selectedInvoice.subtotal) }}</span>
              </div>
              <div v-if="selectedInvoice.discount_amount > 0" class="flex justify-between text-xs text-emerald-700">
                <span>Discount:</span>
                <span class="font-mono font-bold">- ₹{{ formatCurrency(selectedInvoice.discount_amount) }}</span>
              </div>
              <div v-if="selectedInvoice.total_tax > 0" class="flex justify-between text-xs text-slate-600">
                <span>GST Tax:</span>
                <span class="font-mono font-bold">+ ₹{{ formatCurrency(selectedInvoice.total_tax) }}</span>
              </div>
              <div class="flex justify-between text-sm font-black text-slate-900 pt-2 border-t border-slate-200">
                <span>Grand Total:</span>
                <span class="font-mono font-black text-red-600">₹{{ formatCurrency(selectedInvoice.grand_total) }}</span>
              </div>
              <div class="flex justify-between text-xs font-bold text-emerald-700">
                <span>Paid Amount:</span>
                <span class="font-mono">₹{{ formatCurrency(selectedInvoice.paid_amount) }}</span>
              </div>
              <div v-if="selectedInvoice.grand_total > selectedInvoice.paid_amount" class="flex justify-between text-xs font-bold text-red-600">
                <span>Due Amount:</span>
                <span class="font-mono">₹{{ formatCurrency(selectedInvoice.grand_total - selectedInvoice.paid_amount) }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-end pt-4 border-t border-slate-200">
          <button @click="selectedInvoice = null" class="px-5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold">
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- 80MM POS THERMAL RECEIPT MODAL -->
    <div v-if="receiptInvoice" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-sm w-full p-6 space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
          <span class="font-black text-xs text-slate-900 uppercase">POS Thermal Receipt Preview</span>
          <button @click="receiptInvoice = null" class="text-slate-400 hover:text-slate-800 font-bold">✕</button>
        </div>

        <!-- Receipt Print Container -->
        <div class="p-2 bg-slate-100 rounded-2xl border border-slate-200 overflow-x-auto flex justify-center">
          <ThermalReceipt document-type="invoice" :data="receiptInvoice" />
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-slate-200">
          <button @click="receiptInvoice = null" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl font-bold text-xs">
            Close
          </button>
          <button @click="printReceipt" class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md">
            🖨️ Print Receipt
          </button>
        </div>
      </div>
    </div>

    <!-- DELETE SALE CONFIRMATION MODAL / BOTTOM SHEET -->
    <div v-if="deleteInvoiceTarget" class="fixed inset-0 z-[400] bg-slate-900/60 backdrop-blur-xs flex items-end sm:items-center justify-center p-0 sm:p-4">
      <div class="bg-white w-full sm:max-w-lg rounded-t-3xl sm:rounded-3xl border border-slate-200 shadow-2xl p-6 space-y-5 animate-in slide-in-from-bottom duration-200">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div class="flex items-center gap-2">
            <span class="p-2 bg-red-100 text-red-600 rounded-xl text-lg">⚠️</span>
            <div>
              <h3 class="text-base font-black text-slate-900">Delete Sale Transaction?</h3>
              <p class="text-xs text-slate-500 font-medium">Safe Transaction Reversal</p>
            </div>
          </div>
          <button @click="deleteInvoiceTarget = null" class="text-slate-400 hover:text-slate-700 font-bold text-lg">✕</button>
        </div>

        <div class="space-y-3">
          <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/80 space-y-2 text-xs">
            <div class="flex justify-between">
              <span class="text-slate-500 font-medium">Invoice Number:</span>
              <span class="font-mono font-black text-red-600">{{ deleteInvoiceTarget.invoice_number }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500 font-medium">Date & Time:</span>
              <span class="font-mono font-bold text-slate-800">{{ formatDateTime(deleteInvoiceTarget.created_at) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500 font-medium">Customer:</span>
              <span class="font-bold text-slate-900">{{ deleteInvoiceTarget.customer?.name || 'Walk-in Customer' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500 font-medium">Items Count:</span>
              <span class="font-mono font-bold text-slate-800">{{ deleteInvoiceTarget.items_count || deleteInvoiceTarget.items?.length || 0 }} items ({{ getItemTotalQty(deleteInvoiceTarget) }} pcs)</span>
            </div>
            <div class="flex justify-between border-t border-slate-200 pt-2">
              <span class="text-slate-500 font-medium">Grand Total:</span>
              <span class="font-mono font-black text-slate-900 text-sm">₹{{ formatCurrency(deleteInvoiceTarget.grand_total) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500 font-medium">Paid Amount:</span>
              <span class="font-mono font-bold text-emerald-700">₹{{ formatCurrency(deleteInvoiceTarget.paid_amount) }}</span>
            </div>
            <div v-if="deleteInvoiceTarget.grand_total > deleteInvoiceTarget.paid_amount" class="flex justify-between">
              <span class="text-slate-500 font-medium">Due Amount:</span>
              <span class="font-mono font-bold text-red-600">₹{{ formatCurrency(deleteInvoiceTarget.grand_total - deleteInvoiceTarget.paid_amount) }}</span>
            </div>
          </div>

          <div class="p-3.5 bg-amber-50 border border-amber-200 rounded-2xl text-xs text-amber-900 space-y-1">
            <span class="font-black block">⚠️ Transaction Reversal Warning:</span>
            <p class="font-medium text-[11px] leading-relaxed">
              This action will reverse inventory stock deductions, payment records, customer balances, store credit, and loyalty points. This transaction will be removed from all financial reports.
            </p>
          </div>

          <div v-if="deleteError" class="p-3 bg-red-50 border border-red-200 rounded-2xl text-xs font-bold text-red-700 leading-relaxed">
            {{ deleteError }}
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
          <button
            @click="deleteInvoiceTarget = null"
            :disabled="isDeleting"
            class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs transition-colors"
          >
            Cancel
          </button>

          <button
            @click="executeDeleteInvoice"
            :disabled="isDeleting"
            class="px-5 py-2.5 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white rounded-xl font-black text-xs shadow-md shadow-red-600/20 transition-all flex items-center gap-2"
          >
            <span v-if="isDeleting" class="animate-spin">⏳</span>
            <span>{{ isDeleting ? 'Reversing Transaction...' : 'Confirm Safe Delete' }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '../../services/api';
import ThermalReceipt from '../../components/printing/ThermalReceipt.vue';
import MobileListCard from '../../components/ui/MobileListCard.vue';

const invoices = ref([]);
const loading = ref(false);
const error = ref(null);

const selectedInvoice = ref(null);
const receiptInvoice = ref(null);
const deleteInvoiceTarget = ref(null);
const isDeleting = ref(false);
const deleteError = ref(null);

const pagination = reactive({
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1,
});

const filters = reactive({
  search: '',
  payment_status: '',
  status: '',
  payment_method: '',
});

function formatCurrency(val) {
  return Number(val || 0).toLocaleString('en-IN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatDateTime(dtStr) {
  if (!dtStr) return 'N/A';
  try {
    const d = new Date(dtStr);
    return d.toLocaleString('en-IN', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch (e) {
    return dtStr;
  }
}

function getItemArticle(item) {
  return item.article_number_snapshot || item.variant_size?.variant?.product?.article_number || item.sku_snapshot || 'N/A';
}

function getItemProductName(item) {
  return item.product_name_snapshot || item.variant_size?.variant?.product?.name || 'Footwear Item';
}

function getItemColorName(item) {
  return item.color_name_snapshot || item.variant_size?.variant?.color?.name || 'Standard';
}

function getItemSizeNumber(item) {
  return item.size_number_snapshot || item.variant_size?.size?.size_number || 'N/A';
}

function getItemTotalQty(inv) {
  if (!inv.items || !Array.isArray(inv.items)) return 0;
  return inv.items.reduce((acc, i) => acc + (Number(i.quantity) || 1), 0);
}

let debounceTimer = null;
function debouncedSearch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchInvoices(1);
  }, 350);
}

async function fetchInvoices(page = 1) {
  loading.value = true;
  error.value = null;
  try {
    const params = {
      page,
      per_page: pagination.per_page,
    };
    if (filters.search) params.search = filters.search;
    if (filters.payment_status) params.payment_status = filters.payment_status;
    if (filters.status) params.status = filters.status;
    if (filters.payment_method) params.payment_method = filters.payment_method;

    const res = await api.get('/pos/sales', { params });
    const payload = res.data || res;

    if (payload.items && Array.isArray(payload.items)) {
      invoices.value = payload.items;
      if (payload.pagination) Object.assign(pagination, payload.pagination);
    } else if (Array.isArray(payload)) {
      invoices.value = payload;
    } else {
      invoices.value = [];
    }
  } catch (err) {
    console.error('Failed to fetch sales invoices:', err);
    error.value = err.response?.data?.message || 'Unable to connect to sales database.';
  } finally {
    loading.value = false;
  }
}

function openInvoiceModal(inv) {
  selectedInvoice.value = inv;
}

function openThermalReceipt(inv) {
  receiptInvoice.value = inv;
}

function printReceipt() {
  window.print();
}

function confirmDeleteInvoice(inv) {
  deleteInvoiceTarget.value = inv;
  deleteError.value = null;
}

async function executeDeleteInvoice() {
  if (!deleteInvoiceTarget.value) return;
  isDeleting.value = true;
  deleteError.value = null;
  try {
    const invId = deleteInvoiceTarget.value.id;
    await api.delete(`/pos/sales/${invId}`);
    deleteInvoiceTarget.value = null;
    fetchInvoices(pagination.current_page);
  } catch (err) {
    console.error('Failed to delete sale invoice:', err);
    deleteError.value = err.response?.data?.message || 'Failed to delete sale transaction.';
  } finally {
    isDeleting.value = false;
  }
}

onMounted(() => {
  fetchInvoices(1);
});
</script>

