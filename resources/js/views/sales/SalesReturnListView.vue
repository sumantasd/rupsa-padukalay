<template>
  <div class="space-y-6 antialiased font-sans max-w-7xl mx-auto pb-16">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Sales Returns & Refunds</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Process customer returns, restock footwear inventory, issue cash/UPI refunds or Store Credit for Main Outlet (STR-001).
        </p>
      </div>
      <button
        @click="openNewReturnWizard"
        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white font-black text-xs rounded-xl shadow-md shadow-red-600/20 transition-all uppercase tracking-wider shrink-0"
      >
        <span>↩️</span>
        <span>Process New Return</span>
      </button>
    </div>

    <!-- Search & Filter Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex flex-col md:flex-row items-center justify-between gap-4 text-xs">
      <div class="relative w-full md:w-96">
        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
        <input
          type="text"
          v-model="filters.search"
          @input="debouncedSearch"
          placeholder="Search return #, original invoice #, customer..."
          class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-4 py-2.5 font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
        />
      </div>

      <div class="flex items-center gap-3 w-full md:w-auto overflow-x-auto">
        <select
          v-model="filters.refund_mode"
          @change="fetchReturns(1)"
          class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-red-600"
        >
          <option value="">All Refund Modes</option>
          <option value="cash">Cash Refund</option>
          <option value="upi">UPI Refund</option>
          <option value="card">Card Refund</option>
          <option value="store_credit">Store Credit Issued</option>
        </select>

        <button
          @click="fetchReturns(1)"
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
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="p-6 bg-red-50 border border-red-200 rounded-2xl text-center space-y-3">
      <span class="text-2xl">⚠️</span>
      <h3 class="font-black text-sm text-red-900">Unable to load sales returns</h3>
      <p class="text-xs text-red-700 font-medium max-w-md mx-auto">{{ error }}</p>
      <button @click="fetchReturns(1)" class="px-4 py-2 bg-red-600 text-white font-bold rounded-xl text-xs shadow-xs">
        Try Again
      </button>
    </div>

    <!-- Empty State -->
    <div v-else-if="returnsList.length === 0" class="p-12 bg-white rounded-2xl border border-slate-200/90 text-center space-y-4 shadow-xs">
      <div class="h-16 w-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto text-3xl">
        ↩️
      </div>
      <div class="space-y-1">
        <h3 class="font-black text-base text-slate-900">No Sales Returns Recorded</h3>
        <p class="text-xs text-slate-500 max-w-sm mx-auto">
          No customer product return notes have been logged yet.
        </p>
      </div>
    </div>

    <!-- Mobile Cards List View (< md) -->
    <div v-else-if="returnsList.length > 0" class="space-y-3 md:hidden">
      <MobileListCard
        v-for="ret in returnsList"
        :key="ret.id"
        :title="ret.return_number"
        :subtitle="formatDateTime(ret.created_at) + ' • ' + (ret.customer_name || ret.customer?.name || 'Walk-in Customer')"
        :status="ret.refund_mode || ret.payment_method || 'Refunded'"
        status-type="danger"
        :metric="'₹' + formatCurrency(ret.total_refund_amount)"
      >
        <div class="flex items-center justify-between text-xs font-mono">
          <span class="text-slate-500">Invoice #:</span>
          <span class="font-bold text-red-600">{{ ret.original_invoice_number || ret.original_invoice?.invoice_number || 'N/A' }}</span>
        </div>
        <div class="flex items-center justify-between text-xs font-mono">
          <span class="text-slate-500">Reason:</span>
          <span class="font-bold text-slate-800 truncate max-w-[180px]">{{ ret.reason || 'Customer Return' }}</span>
        </div>

        <template #actions>
          <button
            @click="selectedReturn = ret"
            class="px-3 py-1.5 bg-slate-900 text-white rounded-lg font-bold text-xs cursor-pointer flex items-center gap-1"
          >
            <span>🔍 View</span>
          </button>
          <button
            @click="openThermalReceipt(ret)"
            class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-300 rounded-lg font-bold text-xs cursor-pointer flex items-center gap-1"
          >
            <span>🖨️ Receipt</span>
          </button>
          <button
            @click="confirmDeleteReturn(ret)"
            class="px-2.5 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 rounded-lg font-bold text-xs cursor-pointer flex items-center gap-1"
          >
            <span>🗑️ Delete</span>
          </button>
        </template>
      </MobileListCard>
    </div>

    <!-- Desktop DataTable View (>= md) -->
    <div v-if="returnsList.length > 0" class="hidden md:block bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
              <th class="py-3.5 px-4">Return #</th>
              <th class="py-3.5 px-4">Original Invoice #</th>
              <th class="py-3.5 px-4">Return Date</th>
              <th class="py-3.5 px-4">Customer Details</th>
              <th class="py-3.5 px-4 text-right">Refund Amount</th>
              <th class="py-3.5 px-4 text-center">Refund Mode</th>
              <th class="py-3.5 px-4">Return Reason</th>
              <th class="py-3.5 px-4">Staff</th>
              <th class="py-3.5 px-4 text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
            <tr v-for="ret in returnsList" :key="ret.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="py-3.5 px-4 font-mono font-bold text-amber-700">
                {{ ret.return_number || ('RET-' + ret.id) }}
              </td>

              <td class="py-3.5 px-4 font-mono font-bold text-red-600">
                {{ ret.original_invoice?.invoice_number || ret.original_invoice_number || 'N/A' }}
              </td>

              <td class="py-3.5 px-4 font-mono text-[11px] text-slate-600">
                {{ formatDateTime(ret.created_at) }}
              </td>

              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900">{{ ret.customer?.name || 'Walk-in Customer' }}</div>
                <div v-if="ret.customer?.mobile_number" class="text-[10px] font-mono text-slate-400">
                  {{ ret.customer.mobile_number }}
                </div>
              </td>

              <td class="py-3.5 px-4 text-right font-mono font-black text-amber-700 text-sm">
                ₹{{ formatCurrency(ret.total_refund_amount) }}
              </td>

              <td class="py-3.5 px-4 text-center">
                <span
                  :class="[
                    'px-2 py-0.5 rounded text-[9px] font-black uppercase border',
                    ret.refund_mode === 'store_credit' ? 'bg-purple-50 text-purple-800 border-purple-200' : 'bg-slate-100 text-slate-800 border-slate-200'
                  ]"
                >
                  {{ ret.refund_mode }}
                </span>
              </td>

              <td class="py-3.5 px-4 text-slate-600 font-medium">
                {{ ret.reason || 'Customer Return' }}
              </td>

              <td class="py-3.5 px-4 text-slate-600">
                {{ ret.processor?.name || 'Staff' }}
              </td>

              <td class="py-3.5 px-4 text-center">
                <div class="flex items-center justify-center gap-1.5 whitespace-nowrap">
                  <button
                    @click="selectedReturn = ret"
                    class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-bold text-[11px] transition-colors"
                  >
                    👁️ Details
                  </button>
                  <button
                    v-if="hasPermission('sales_returns.delete')"
                    @click="confirmDeleteReturn(ret)"
                    class="px-2.5 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg font-bold text-[11px] transition-colors shadow-xs"
                    title="Delete Sales Return & Reverse Transaction"
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
        <div>Showing Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} Total Returns)</div>
        <div class="flex items-center gap-2">
          <button
            @click="fetchReturns(pagination.current_page - 1)"
            :disabled="pagination.current_page === 1 || loading"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg disabled:opacity-30 hover:bg-slate-100 transition-colors"
          >
            ← Previous
          </button>
          <button
            @click="fetchReturns(pagination.current_page + 1)"
            :disabled="pagination.current_page === pagination.last_page || loading"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg disabled:opacity-30 hover:bg-slate-100 transition-colors"
          >
            Next →
          </button>
        </div>
      </div>
    </div>

    <!-- PROCESS NEW RETURN WIZARD MODAL -->
    <Teleport to="body">
      <div v-if="showWizard" class="fixed inset-0 z-[500] bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-3xl w-full p-6 sm:p-8 space-y-6 max-h-[90vh] overflow-y-auto text-xs">
          <!-- Wizard Header -->
          <div class="flex items-center justify-between border-b border-slate-200 pb-4">
            <div>
              <h3 class="text-lg font-black text-slate-900">Process Sales Return</h3>
              <p class="text-xs text-slate-500 font-medium mt-0.5">Select original POS invoice, choose returned footwear items & refund mode.</p>
            </div>
            <button @click="showWizard = false" class="p-2 rounded-xl text-slate-400 hover:text-slate-800 font-black text-lg cursor-pointer">✕</button>
          </div>

          <!-- STEP 1: Search & Select Invoice -->
          <div v-if="wizardStep === 1" class="space-y-4">
            <div class="space-y-1.5">
              <label class="font-black text-slate-800 block">Search Original Sales Invoice # or Customer Phone</label>
              <div class="flex gap-2">
                <input
                  v-model="invoiceSearchQuery"
                  type="text"
                  placeholder="e.g. INV-20260903-XXXX or 9735125112"
                  class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 font-bold text-slate-900 focus:ring-2 focus:ring-red-600 focus:bg-white"
                />
                <button
                  @click="searchOriginalInvoices"
                  :disabled="searchingInvoices"
                  class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold shrink-0 cursor-pointer"
                >
                  {{ searchingInvoices ? 'Searching...' : 'Search' }}
                </button>
              </div>
            </div>

            <!-- Matching Invoices List -->
            <div v-if="foundInvoices.length > 0" class="space-y-2 max-h-60 overflow-y-auto border border-slate-200 rounded-2xl p-2 bg-slate-50">
              <div
                v-for="inv in foundInvoices"
                :key="inv.id"
                @click="selectInvoiceForReturn(inv)"
                class="p-3 bg-white hover:bg-red-50 border border-slate-200 rounded-xl cursor-pointer transition-colors flex items-center justify-between"
              >
                <div>
                  <div class="font-mono font-black text-red-600 text-xs">{{ inv.invoice_number }}</div>
                  <div class="text-[11px] text-slate-600 font-bold">{{ inv.customer?.name || 'Walk-in Customer' }} ({{ inv.customer?.mobile_number || 'N/A' }})</div>
                  <div class="text-[10px] text-slate-400">{{ formatDateTime(inv.created_at) }}</div>
                </div>
                <div class="text-right">
                  <div class="font-mono font-black text-slate-900">₹{{ formatCurrency(inv.grand_total) }}</div>
                  <div class="text-[10px] font-bold text-emerald-700 uppercase">{{ inv.status }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- STEP 2: Select Items to Return -->
          <div v-else-if="wizardStep === 2" class="space-y-5">
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 flex justify-between items-center">
              <div>
                <div class="font-mono font-black text-red-600">{{ targetInvoice.invoice_number }}</div>
                <div class="font-bold text-slate-800 text-xs">{{ targetInvoice.customer?.name || 'Walk-in Customer' }}</div>
              </div>
              <button @click="wizardStep = 1" class="text-xs font-bold text-slate-500 hover:text-red-600 cursor-pointer">Change Invoice</button>
            </div>

            <div class="space-y-2">
              <h4 class="font-black text-slate-900 uppercase text-[11px]">Select Items & Return Quantities</h4>
              <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-left text-xs">
                  <thead class="bg-slate-100 border-b border-slate-200 text-[10px] font-black text-slate-600 uppercase">
                    <tr>
                      <th class="py-2.5 px-3">Article #</th>
                      <th class="py-2.5 px-3">Product Name</th>
                      <th class="py-2.5 px-3">Color / Size (IND)</th>
                      <th class="py-2.5 px-3 text-center">Purchased</th>
                      <th class="py-2.5 px-3 text-center">Return Qty</th>
                      <th class="py-2.5 px-3 text-center">Condition</th>
                      <th class="py-2.5 px-3 text-right">Return Val (₹)</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100 font-medium text-slate-808">
                    <tr v-for="item in returnFormItems" :key="item.invoice_item_id">
                      <td class="py-2.5 px-3 font-mono font-bold text-red-600">{{ item.article_number }}</td>
                      <td class="py-2.5 px-3 font-bold text-slate-900">{{ item.product_name }}</td>
                      <td class="py-2.5 px-3">{{ item.color_name }} / IND {{ item.size_number }}</td>
                      <td class="py-2.5 px-3 text-center font-mono font-bold">{{ item.max_returnable }} pcs</td>
                      <td class="py-2.5 px-3 text-center">
                        <input
                          v-model.number="item.quantity"
                          type="number"
                          min="0"
                          :max="item.max_returnable"
                          class="w-16 bg-slate-50 border border-slate-300 rounded-lg px-2 py-1 text-center font-mono font-bold text-slate-900 focus:ring-2 focus:ring-red-600"
                        />
                      </td>
                      <td class="py-2.5 px-3 text-center">
                        <select
                          v-model="item.restock_condition"
                          class="bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 text-[11px] font-bold"
                        >
                          <option value="resellable">Resellable (Stock +)</option>
                          <option value="damaged">Damaged (No Stock)</option>
                        </select>
                      </td>
                      <td class="py-2.5 px-3 text-right font-mono font-black text-amber-700">
                        ₹{{ formatCurrency(item.quantity * item.unit_price) }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Refund Mode & Reason -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
              <div class="space-y-1">
                <label class="font-black text-slate-800 block">Refund Method <span class="text-red-600">*</span></label>
                <select
                  v-model="returnMeta.refund_mode"
                  class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 font-bold text-slate-900 focus:ring-2 focus:ring-red-600"
                >
                  <option value="cash">Cash Refund</option>
                  <option value="upi">UPI Refund</option>
                  <option value="card">Card Refund</option>
                  <option value="store_credit">Issue Customer Store Credit</option>
                </select>
              </div>

              <div class="space-y-1">
                <label class="font-black text-slate-800 block">Return Reason <span class="text-red-600">*</span></label>
                <select
                  v-model="returnMeta.reason"
                  class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 font-bold text-slate-900 focus:ring-2 focus:ring-red-600"
                >
                  <option value="Customer Return">Customer Return</option>
                  <option value="Wrong Size">Wrong Size</option>
                  <option value="Defective Product">Defective Product</option>
                  <option value="Wrong Product">Wrong Product</option>
                  <option value="Damaged Product">Damaged Product</option>
                  <option value="Other">Other</option>
                </select>
              </div>
            </div>

            <!-- Summary & Action -->
            <div class="flex items-center justify-between pt-4 border-t border-slate-200">
              <div>
                <span class="text-xs text-slate-500 font-bold block">Total Refund Value:</span>
                <span class="font-mono font-black text-amber-700 text-lg">₹{{ formatCurrency(calculatedTotalRefund) }}</span>
              </div>

              <button
                @click="submitReturn"
                :disabled="submittingReturn || calculatedTotalRefund <= 0"
                class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white font-black rounded-xl shadow-md shadow-red-600/20 cursor-pointer disabled:opacity-50"
              >
                {{ submittingReturn ? 'Processing...' : 'Confirm Sales Return' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- RETURN DETAILS MODAL -->
    <Teleport to="body">
      <div v-if="selectedReturn" class="fixed inset-0 z-[500] bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-3xl w-full p-6 sm:p-8 space-y-6 max-h-[90vh] overflow-y-auto text-xs">
          <div class="flex items-center justify-between border-b border-slate-200 pb-4">
            <div>
              <h3 class="text-lg font-black text-slate-900">Return Note: {{ selectedReturn.return_number }}</h3>
              <p class="text-xs text-slate-500 font-medium mt-0.5">Processed on {{ formatDateTime(selectedReturn.created_at) }}</p>
            </div>
            <button @click="selectedReturn = null" class="p-2 rounded-xl text-slate-400 hover:text-slate-800 font-black text-lg cursor-pointer">✕</button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200">
            <div>
              <div class="font-bold text-slate-400 uppercase text-[10px]">Original Invoice</div>
              <div class="font-mono font-black text-red-600 text-sm">{{ selectedReturn.original_invoice?.invoice_number }}</div>
              <div class="font-bold text-slate-800">Customer: {{ selectedReturn.customer?.name || 'Walk-in Customer' }}</div>
            </div>
            <div class="text-left sm:text-right">
              <div class="font-bold text-slate-400 uppercase text-[10px]">Refund Summary</div>
              <div class="font-mono font-black text-amber-700 text-lg">₹{{ formatCurrency(selectedReturn.total_refund_amount) }}</div>
              <div class="font-bold text-slate-700 uppercase">Refund Mode: {{ selectedReturn.refund_mode }}</div>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-4 border-t border-slate-200">
            <button @click="confirmDeleteReturn(selectedReturn); selectedReturn = null" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold flex items-center gap-1 cursor-pointer">
              <span>🗑️</span>
              <span>Delete Return</span>
            </button>
            <button @click="openThermalReceipt(selectedReturn); selectedReturn = null" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl font-bold flex items-center gap-1 cursor-pointer">
              <span>🖨️</span>
              <span>Print Receipt</span>
            </button>
            <button @click="selectedReturn = null" class="px-5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold cursor-pointer">
              Close
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- DELETE SALES RETURN CONFIRMATION MODAL -->
    <Teleport to="body">
      <div v-if="deleteReturnTarget" class="fixed inset-0 z-[550] bg-slate-900/60 backdrop-blur-xs flex items-end sm:items-center justify-center p-0 sm:p-4 overflow-y-auto">
        <div class="bg-white w-full sm:max-w-lg rounded-t-3xl sm:rounded-3xl border border-slate-200 shadow-2xl p-6 space-y-5 animate-in slide-in-from-bottom duration-200 text-xs">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
              <span class="p-2 bg-red-100 text-red-600 rounded-xl text-lg">⚠️</span>
              <div>
                <h3 class="text-base font-black text-slate-900">Delete Sales Return?</h3>
                <p class="text-xs text-slate-500 font-medium">Safe Reversal of Return Transaction</p>
              </div>
            </div>
            <button @click="deleteReturnTarget = null" class="text-slate-400 hover:text-slate-700 font-bold text-lg cursor-pointer">✕</button>
          </div>

          <div class="space-y-3">
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/80 space-y-2">
              <div class="flex justify-between">
                <span class="text-slate-500 font-medium">Return Number:</span>
                <span class="font-mono font-black text-amber-700">{{ deleteReturnTarget.return_number || ('RET-' + deleteReturnTarget.id) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-500 font-medium">Original Invoice #:</span>
                <span class="font-mono font-bold text-red-600">{{ deleteReturnTarget.original_invoice?.invoice_number || deleteReturnTarget.original_invoice_number || 'N/A' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-500 font-medium">Customer:</span>
                <span class="font-bold text-slate-900">{{ deleteReturnTarget.customer?.name || deleteReturnTarget.customer_name || 'Walk-in Customer' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-500 font-medium">Return Date:</span>
                <span class="font-mono font-bold text-slate-800">{{ formatDateTime(deleteReturnTarget.created_at) }}</span>
              </div>

              <!-- Returned Items Summary -->
              <div v-if="deleteReturnTarget.items && deleteReturnTarget.items.length > 0" class="pt-2 border-t border-slate-200">
                <span class="text-slate-500 font-bold block mb-1">Returned Items:</span>
                <div class="space-y-1 max-h-32 overflow-y-auto">
                  <div v-for="item in deleteReturnTarget.items" :key="item.id" class="flex justify-between bg-white p-2 rounded-lg border border-slate-200 font-mono text-[11px]">
                    <span>{{ item.product_name || 'Item' }} ({{ item.color || '' }} {{ item.size ? 'IND ' + item.size : '' }}) × {{ item.quantity }}</span>
                    <span class="font-bold text-slate-900">₹{{ formatCurrency(item.subtotal || (item.quantity * item.refund_unit_price)) }}</span>
                  </div>
                </div>
              </div>

              <div class="flex justify-between border-t border-slate-200 pt-2">
                <span class="text-slate-500 font-medium">Return Amount:</span>
                <span class="font-mono font-black text-amber-700 text-sm">₹{{ formatCurrency(deleteReturnTarget.total_refund_amount) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-500 font-medium">Refund Mode:</span>
                <span class="font-bold uppercase text-purple-700">{{ deleteReturnTarget.refund_mode }}</span>
              </div>
            </div>

            <div class="p-3.5 bg-amber-50 border border-amber-200 rounded-2xl text-amber-900 space-y-1">
              <span class="font-black block">⚠️ Warning:</span>
              <p class="font-medium text-[11px] leading-relaxed">
                This action will permanently remove this return transaction and reverse its related inventory/financial effects.
              </p>
            </div>

            <div v-if="deleteError" class="p-3 bg-red-50 border border-red-200 rounded-2xl font-bold text-red-700 leading-relaxed">
              {{ deleteError }}
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 pt-2">
            <button
              @click="deleteReturnTarget = null"
              :disabled="isDeleting"
              class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs transition-colors cursor-pointer"
            >
              Cancel
            </button>

            <button
              @click="executeDeleteReturn"
              :disabled="isDeleting"
              class="px-5 py-2.5 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white rounded-xl font-black text-xs shadow-md shadow-red-600/20 transition-all flex items-center gap-2 cursor-pointer"
            >
              <span v-if="isDeleting" class="animate-spin">⏳</span>
              <span>{{ isDeleting ? 'Reversing Return...' : 'Delete Return' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- SALES RETURN THERMAL RECEIPT MODAL -->
    <Teleport to="body">
      <div v-if="receiptReturn" class="fixed inset-0 z-[500] bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-sm w-full p-6 space-y-4">
          <!-- Header -->
          <div class="flex items-center justify-between border-b border-slate-200 pb-3">
            <span class="font-black text-xs text-slate-900 uppercase">Sales Return Receipt Preview</span>
            <button @click="receiptReturn = null" class="text-slate-400 hover:text-slate-800 font-bold cursor-pointer">✕</button>
          </div>

          <!-- Receipt Print Container -->
          <div class="p-2 bg-slate-100 rounded-2xl border border-slate-200 overflow-x-auto flex justify-center">
            <ThermalReceipt document-type="return" :data="receiptReturn" />
          </div>

          <div class="flex justify-end gap-2 pt-2 border-t border-slate-200">
            <button @click="receiptReturn = null" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl font-bold text-xs cursor-pointer">
              Close
            </button>
            <button @click="printReceipt" class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md cursor-pointer">
              🖨️ Print Receipt
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, onUnmounted, nextTick } from 'vue';
import api from '../../services/api';
import { usePrinterStore } from '../../stores/printerStore';
import ThermalReceipt from '../../components/printing/ThermalReceipt.vue';
import MobileListCard from '../../components/ui/MobileListCard.vue';

const printerStore = usePrinterStore();
const returnsList = ref([]);
const loading = ref(false);
const error = ref(null);

const showWizard = ref(false);
const wizardStep = ref(1);
const invoiceSearchQuery = ref('');
const searchingInvoices = ref(false);
const foundInvoices = ref([]);
const targetInvoice = ref(null);
const returnFormItems = ref([]);
const submittingReturn = ref(false);

const selectedReturn = ref(null);
const receiptReturn = ref(null);
const deleteReturnTarget = ref(null);
const isDeleting = ref(false);
const deleteError = ref(null);

function openThermalReceipt(ret) {
  receiptReturn.value = ret;
  if (!printerStore.loaded) {
    printerStore.fetchSettings();
  }
}

function printReceipt() {
  nextTick(() => {
    setTimeout(() => {
      window.print();
    }, 150);
  });
}

function confirmDeleteReturn(ret) {
  deleteReturnTarget.value = ret;
  deleteError.value = null;
}

async function executeDeleteReturn() {
  if (!deleteReturnTarget.value) return;
  isDeleting.value = true;
  deleteError.value = null;
  try {
    const retId = deleteReturnTarget.value.id;
    await api.delete(`/pos/sales/returns/${retId}`);
    deleteReturnTarget.value = null;
    fetchReturns(pagination.current_page);
  } catch (err) {
    console.error('Failed to delete sales return:', err);
    deleteError.value = err.response?.data?.message || 'Failed to delete sales return transaction.';
  } finally {
    isDeleting.value = false;
  }
}

const pagination = reactive({
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1,
});

const filters = reactive({
  search: '',
  refund_mode: '',
});

const returnMeta = reactive({
  refund_mode: 'cash',
  reason: 'Customer Return',
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

const calculatedTotalRefund = computed(() => {
  return returnFormItems.value.reduce((acc, i) => acc + (Number(i.quantity || 0) * Number(i.unit_price || 0)), 0);
});

let debounceTimer = null;
function debouncedSearch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchReturns(1);
  }, 350);
}

async function fetchReturns(page = 1) {
  loading.value = true;
  error.value = null;
  try {
    const params = {
      page,
      per_page: pagination.per_page,
    };
    if (filters.search) params.search = filters.search;
    if (filters.refund_mode) params.refund_mode = filters.refund_mode;

    const res = await api.get('/pos/sales/returns', { params });
    const payload = res.data || res;

    if (payload.items && Array.isArray(payload.items)) {
      returnsList.value = payload.items;
      if (payload.pagination) Object.assign(pagination, payload.pagination);
    } else if (Array.isArray(payload)) {
      returnsList.value = payload;
    } else {
      returnsList.value = [];
    }
  } catch (err) {
    console.error('Failed to fetch returns:', err);
    error.value = err.response?.data?.message || 'Unable to connect to sales returns database.';
  } finally {
    loading.value = false;
  }
}

function openNewReturnWizard() {
  showWizard.value = true;
  wizardStep.value = 1;
  invoiceSearchQuery.value = '';
  foundInvoices.value = [];
  targetInvoice.value = null;
  returnFormItems.value = [];
}

async function searchOriginalInvoices() {
  if (!invoiceSearchQuery.value) return;
  searchingInvoices.value = true;
  try {
    const res = await api.get('/pos/sales', { params: { search: invoiceSearchQuery.value, per_page: 5 } });
    const payload = res.data || res;
    foundInvoices.value = payload.items || (Array.isArray(payload) ? payload : []);
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to search invoice.');
  } finally {
    searchingInvoices.value = false;
  }
}

function selectInvoiceForReturn(inv) {
  targetInvoice.value = inv;
  returnFormItems.value = (inv.items || []).map(i => ({
    invoice_item_id: i.id,
    product_variant_size_id: i.product_variant_size_id,
    article_number: i.article_number_snapshot || i.sku_snapshot || 'N/A',
    product_name: i.product_name_snapshot || 'Footwear Item',
    color_name: i.color_name_snapshot || 'Standard',
    size_number: i.size_number_snapshot || 'N/A',
    max_returnable: Number(i.quantity || 1),
    quantity: 0,
    unit_price: Number(i.unit_price || 0),
    restock_condition: 'resellable',
  }));
  wizardStep.value = 2;
}

async function submitReturn() {
  const activeItems = returnFormItems.value
    .filter(i => i.quantity > 0)
    .map(i => ({
      invoice_item_id: i.invoice_item_id,
      quantity: i.quantity,
      restock_condition: i.restock_condition,
    }));

  if (activeItems.length === 0) {
    alert('Please specify return quantity greater than 0 for at least one item.');
    return;
  }

  submittingReturn.value = true;
  try {
    await api.post(`/pos/sales/${targetInvoice.value.id}/return`, {
      items: activeItems,
      refund_mode: returnMeta.refund_mode,
      reason: returnMeta.reason,
    });

    showWizard.value = false;
    fetchReturns(1);
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to process sales return.');
  } finally {
    submittingReturn.value = false;
  }
}

onMounted(() => {
  fetchReturns(1);
  if (!printerStore.loaded) {
    printerStore.fetchSettings();
  }
});

watch(
  [showWizard, selectedReturn, deleteReturnTarget, receiptReturn],
  (modalStates) => {
    const isAnyOpen = modalStates.some(Boolean);
    if (typeof document !== 'undefined') {
      document.body.style.overflow = isAnyOpen ? 'hidden' : '';
    }
  }
);

onUnmounted(() => {
  if (typeof document !== 'undefined') {
    document.body.style.overflow = '';
  }
});
</script>
