<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-16 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">📑 Purchase Bills & Accounts Payable</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Manage vendor purchase bills, supplier financial liabilities, and payments for STR-001
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          @click="openCreateBillModal"
          class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md shadow-red-600/20 transition-all uppercase tracking-wider flex items-center gap-1.5 cursor-pointer"
        >
          <span>📑</span>
          <span>New Purchase Bill</span>
        </button>
      </div>
    </div>

    <!-- FILTER BAR -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex flex-wrap items-center justify-between gap-3 text-xs">
      <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
        <div class="relative max-w-xs w-64">
          <input
            v-model="filters.search"
            @input="debouncedFetch"
            type="text"
            placeholder="Search Bill #, Invoice #, Supplier..."
            class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-red-500/20"
          />
          <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
        </div>

        <select v-model="filters.payment_status" @change="fetchBills(1)" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-800 focus:outline-none">
          <option value="">All Payment Statuses</option>
          <option value="unpaid">Unpaid / Due</option>
          <option value="partially_paid">Partially Paid</option>
          <option value="paid">Paid</option>
        </select>
      </div>
    </div>

    <!-- BILLS TABLE -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="bg-slate-900 text-white font-extrabold uppercase tracking-wider text-[11px]">
              <th class="py-3.5 px-4">Bill # & Date</th>
              <th class="py-3.5 px-4">Supplier</th>
              <th class="py-3.5 px-4">Vendor Invoice #</th>
              <th class="py-3.5 px-4 text-right">Grand Total</th>
              <th class="py-3.5 px-4 text-right">Paid Amount</th>
              <th class="py-3.5 px-4 text-right font-mono text-red-400">Due Amount</th>
              <th class="py-3.5 px-4 text-center">Status</th>
              <th class="py-3.5 px-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium">
            <tr v-if="loading">
              <td colspan="8" class="text-center py-12 text-slate-400 font-bold">
                Loading purchase bills...
              </td>
            </tr>
            <tr v-else-if="billsList.length === 0">
              <td colspan="8" class="text-center py-12 text-slate-500 font-bold">
                No purchase bills found. Click "+ New Purchase Bill" to record one.
              </td>
            </tr>
            <tr v-for="bill in billsList" :key="bill.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="py-3 px-4">
                <div class="font-mono font-black text-red-600 text-xs">{{ bill.bill_number }}</div>
                <div class="text-[10px] text-slate-400 font-medium">{{ bill.bill_date }}</div>
              </td>
              <td class="py-3 px-4">
                <div class="font-bold text-slate-900">{{ bill.supplier_name }}</div>
                <div class="text-[10px] text-slate-500 font-mono">Phone: {{ bill.supplier_phone }}</div>
              </td>
              <td class="py-3 px-4 font-mono font-bold text-slate-700">
                {{ bill.supplier_invoice_number || 'N/A' }}
              </td>
              <td class="py-3 px-4 text-right font-mono font-black text-slate-900">
                ₹{{ formatCurrency(bill.grand_total) }}
              </td>
              <td class="py-3 px-4 text-right font-mono font-bold text-emerald-700">
                ₹{{ formatCurrency(bill.paid_amount) }}
              </td>
              <td class="py-3 px-4 text-right font-mono font-black text-red-600">
                ₹{{ formatCurrency(bill.due_amount) }}
              </td>
              <td class="py-3 px-4 text-center">
                <span :class="getPaymentBadgeClass(bill.payment_status)">
                  {{ formatPaymentStatus(bill.payment_status) }}
                </span>
              </td>
              <td class="py-3 px-4 text-right flex items-center justify-end gap-1.5">
                <button
                  v-if="bill.due_amount > 0"
                  @click="openPayModal(bill)"
                  class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-black text-[10px] shadow-xs transition-all cursor-pointer"
                >
                  💳 Make Payment
                </button>
                <button
                  @click="confirmDeleteBill(bill)"
                  class="px-2.5 py-1 bg-red-600 hover:bg-red-700 text-white rounded-lg font-black text-[10px] shadow-xs transition-all cursor-pointer"
                  title="Safe Purchase Transaction Reversal"
                >
                  🗑️ Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between text-xs font-bold text-slate-600">
        <div>Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} Bills)</div>
        <div class="flex items-center gap-2">
          <button @click="fetchBills(pagination.current_page - 1)" :disabled="pagination.current_page <= 1" class="px-3 py-1 bg-white border rounded-lg disabled:opacity-40 cursor-pointer">← Prev</button>
          <button @click="fetchBills(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page" class="px-3 py-1 bg-white border rounded-lg disabled:opacity-40 cursor-pointer">Next →</button>
        </div>
      </div>
    </div>

    <!-- NEW PURCHASE BILL MODAL -->
    <Teleport to="body">
      <div v-if="showCreateBillModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-[400] flex items-center justify-center p-0 sm:p-4 overflow-hidden font-sans">
        <div class="bg-white rounded-none sm:rounded-3xl max-w-5xl w-full p-0 shadow-2xl border-0 sm:border sm:border-slate-200 flex flex-col h-[100dvh] sm:h-auto sm:max-h-[90dvh] overflow-hidden my-0 sm:my-auto">
          <!-- Header -->
          <div class="flex items-center justify-between border-b border-slate-200 px-4 sm:px-6 py-3.5 sm:py-4 shrink-0 bg-white z-10">
            <div>
              <h2 class="text-base sm:text-lg font-black text-slate-900">📑 New Purchase Bill & Vendor Liability</h2>
              <p class="text-[11px] sm:text-xs text-slate-500 font-medium">Record vendor invoice, items, tax, discount, and initial supplier payment</p>
            </div>
            <button @click="showCreateBillModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-lg cursor-pointer p-1">✕</button>
          </div>

          <!-- Body -->
          <div class="p-4 sm:p-6 overflow-y-auto flex-1 space-y-5 text-xs text-slate-800">
            <div v-if="createBillError" class="p-3.5 bg-red-50 border border-red-200 rounded-2xl text-xs font-bold text-red-700 leading-relaxed">
              ⚠️ {{ createBillError }}
            </div>

            <!-- 1. MANDATORY SUPPLIER SELECTION CARD -->
            <div class="bg-red-50/50 p-4 rounded-2xl border-2 border-red-200/80 space-y-3">
              <label class="block font-black text-xs text-red-900 uppercase tracking-wider">
                1. Supplier Selection <span class="text-red-600">* Mandatory</span>
              </label>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <select v-model="billForm.supplier_id" @change="onSupplierSelect" class="w-full bg-white border border-red-300 rounded-xl px-3.5 py-2.5 font-bold text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                    <option value="">-- Choose Supplier --</option>
                    <option v-for="sup in suppliersList" :key="sup.id" :value="sup.id">
                      {{ sup.name }} ({{ sup.company_name || 'No Company' }}) — Phone: {{ sup.phone }}
                    </option>
                  </select>
                </div>

                <div v-if="selectedSupplier" class="p-3 bg-white rounded-xl border border-slate-200 text-xs space-y-1">
                  <div class="font-black text-slate-900">{{ selectedSupplier.name }}</div>
                  <div class="text-[11px] text-slate-500">GSTIN: {{ selectedSupplier.gstin || 'N/A' }} | Phone: {{ selectedSupplier.phone }}</div>
                  <div class="font-bold text-red-600">Current Outstanding Liability: ₹{{ formatCurrency(selectedSupplier.current_due_amount || selectedSupplier.current_balance) }}</div>
                </div>
              </div>
            </div>

            <!-- 2. BILL META DETAILS -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 text-xs">
              <div>
                <label class="block font-bold text-slate-700 mb-1">Bill Date <span class="text-red-600">*</span></label>
                <input type="date" v-model="billForm.bill_date" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-900" />
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">Due Date</label>
                <input type="date" v-model="billForm.due_date" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-900" />
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">Payment Terms</label>
                <input type="text" v-model="billForm.payment_terms" placeholder="e.g. Net 30 Days" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-900" />
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">Vendor Invoice #</label>
                <input type="text" v-model="billForm.supplier_invoice_number" placeholder="Supplier Bill / Invoice #" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-900" />
              </div>
            </div>

            <!-- 3. LINKED PO / GRN OPTION OR FOOTWEAR SEARCH -->
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <h3 class="font-black text-xs text-slate-900 uppercase tracking-wider">
                  2. Bill Line Items Entry
                </h3>
              </div>

              <!-- LINK TO EXISTING PO / GRN IF AVAILABLE -->
              <div v-if="selectedSupplier" class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-3.5 rounded-2xl border border-slate-200">
                <div>
                  <label class="block font-bold text-slate-700 mb-1">Link Pending Purchase Order (Optional)</label>
                  <select v-model="selectedPoId" @change="onPoSelect" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-900 text-xs">
                    <option value="">-- Direct Bill (No Linked PO) --</option>
                    <option v-for="po in filteredPos" :key="po.id" :value="po.id">
                      {{ po.po_number }} (Date: {{ po.order_date }}) — Total: ₹{{ formatCurrency(po.grand_total) }}
                    </option>
                  </select>
                </div>
                <div>
                  <label class="block font-bold text-slate-700 mb-1">Link Goods Receive Note (GRN) (Optional)</label>
                  <select v-model="selectedGrnId" @change="onGrnSelect" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-900 text-xs">
                    <option value="">-- Direct Bill (No Linked GRN) --</option>
                    <option v-for="grn in filteredGrns" :key="grn.id" :value="grn.id">
                      {{ grn.grn_number }} (Date: {{ grn.received_date }}) — Total: ₹{{ formatCurrency(grn.total_cost) }}
                    </option>
                  </select>
                </div>
              </div>

              <!-- FOOTWEAR SEARCH AUTOCOMPLETE -->
              <div class="relative">
                <input
                  v-model="productSearchQuery"
                  @input="onProductSearchInput"
                  type="text"
                  placeholder="🔍 Search footwear by Name, Article # (e.g. RP-805), SKU, or Brand to add to bill..."
                  class="w-full pl-10 pr-10 py-2.5 bg-slate-50 border-2 border-slate-200 rounded-2xl font-bold text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-red-500 focus:bg-white transition-all"
                />
                <span class="absolute left-3.5 top-3 text-slate-400 text-sm">👟</span>
                <span v-if="searchLoading" class="absolute right-3.5 top-3 text-red-600 animate-spin text-sm">⌛</span>

                <!-- Search Results Dropdown -->
                <div
                  v-if="showSearchDropdown && searchResultProducts.length > 0"
                  class="absolute left-0 right-0 top-full mt-2 bg-white rounded-2xl border border-slate-200 shadow-2xl z-50 overflow-hidden max-h-60 overflow-y-auto divide-y divide-slate-100"
                >
                  <div
                    v-for="p in searchResultProducts"
                    :key="p.id"
                    @click="addFootwearProductToMatrix(p)"
                    class="p-3 hover:bg-red-50/70 cursor-pointer transition-colors flex items-center justify-between text-xs"
                  >
                    <div class="flex items-center gap-3">
                      <div>
                        <div class="font-black text-slate-900">
                          <span class="font-mono text-red-600">[{{ p.article_number }}]</span> {{ p.product_name }}
                        </div>
                        <div class="text-[11px] text-slate-500 font-medium mt-0.5">Brand: {{ p.brand_name }}</div>
                      </div>
                    </div>
                    <span class="px-2.5 py-1 bg-red-600 text-white rounded-lg font-black text-[10px] uppercase">
                      + Add Matrix
                    </span>
                  </div>
                </div>
              </div>

              <!-- FOOTWEAR MATRIX ITEMS TABLE -->
              <div v-if="selectedFootwearProducts.length > 0" class="space-y-4">
                <div
                  v-for="(prod, pIdx) in selectedFootwearProducts"
                  :key="prod.id"
                  class="bg-white border-2 border-slate-200 rounded-2xl overflow-hidden shadow-xs space-y-3"
                >
                  <div class="bg-slate-900 text-white p-3 flex items-center justify-between">
                    <div>
                      <span class="font-mono text-red-400 font-black">[{{ prod.article_number }}]</span>
                      <span class="font-black ml-2">{{ prod.product_name }}</span>
                      <span class="text-slate-400 text-xs ml-2">Brand: {{ prod.brand_name }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <button @click="promptBulkFillRate(prod)" type="button" class="px-2 py-0.5 bg-slate-800 hover:bg-slate-700 text-amber-400 text-[10px] font-black rounded border border-slate-700 cursor-pointer">⚡ Fill Rate</button>
                      <button @click="promptBulkFillQty(prod)" type="button" class="px-2 py-0.5 bg-slate-800 hover:bg-slate-700 text-emerald-400 text-[10px] font-black rounded border border-slate-700 cursor-pointer">⚡ Fill Qty</button>
                      <button @click="removeFootwearProduct(pIdx)" type="button" class="px-2 py-0.5 bg-red-600 hover:bg-red-700 text-white text-[10px] font-bold rounded cursor-pointer">🗑️</button>
                    </div>
                  </div>

                  <div class="px-3 pb-3 overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse min-w-[600px]">
                      <thead>
                        <tr class="bg-slate-100 text-slate-700 font-black uppercase text-[10px] border-b border-slate-200">
                          <th class="p-2">Size & Color</th>
                          <th class="p-2 text-center font-mono">SKU</th>
                          <th class="p-2 text-center">Billed Qty</th>
                          <th class="p-2 text-center">Cost Price (₹)</th>
                          <th class="p-2 text-center">Discount (₹)</th>
                          <th class="p-2 text-center">Tax (₹)</th>
                          <th class="p-2 text-right font-mono">Line Total</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-100 font-medium">
                        <template v-for="v in prod.variants" :key="v.variant_id || v.id">
                          <tr v-for="s in v.sizes" :key="s.product_variant_size_id || s.id" class="hover:bg-slate-50">
                            <td class="p-2 font-black text-slate-900">
                              <span class="px-2 py-0.5 bg-slate-900 text-white font-mono rounded text-[10px]">{{ s.size_display || s.size_number }}</span>
                              <span class="text-[10px] text-slate-500 ml-1">({{ v.color_name }})</span>
                            </td>
                            <td class="p-2 text-center font-mono text-[10px] text-slate-600">{{ s.sku }}</td>
                            <td class="p-2 text-center">
                              <input type="number" min="0" v-model.number="s.quantity_ordered" class="w-16 bg-slate-50 border border-slate-300 rounded-lg px-2 py-1 text-center font-mono font-bold text-slate-900" />
                            </td>
                            <td class="p-2 text-center">
                              <input type="number" step="0.01" min="0" v-model.number="s.cost_price" class="w-20 bg-slate-50 border border-slate-300 rounded-lg px-2 py-1 text-center font-mono font-bold text-slate-900" />
                            </td>
                            <td class="p-2 text-center">
                              <input type="number" step="0.01" min="0" v-model.number="s.discount_amount" class="w-16 bg-slate-50 border border-slate-300 rounded-lg px-2 py-1 text-center font-mono font-bold text-slate-900" />
                            </td>
                            <td class="p-2 text-center">
                              <input type="number" step="0.01" min="0" v-model.number="s.tax_amount" class="w-16 bg-slate-50 border border-slate-300 rounded-lg px-2 py-1 text-center font-mono font-bold text-slate-900" />
                            </td>
                            <td class="p-2 text-right font-mono font-black text-slate-900">
                              ₹{{ formatCurrency(((s.quantity_ordered || 0) * (s.cost_price || 0)) - (s.discount_amount || 0) + (s.tax_amount || 0)) }}
                            </td>
                          </tr>
                        </template>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <!-- 4. FINANCIAL SUMMARY & INITIAL PAYMENT -->
            <div class="bg-slate-900 text-white p-5 rounded-2xl space-y-4">
              <h3 class="font-black text-xs uppercase tracking-wider text-red-400">3. Financial Calculation & Payment</h3>

              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <div>
                  <label class="block text-slate-300 font-bold mb-1">Subtotal (Items Total)</label>
                  <div class="p-2.5 bg-slate-800 rounded-xl font-mono font-black text-base text-white">
                    ₹{{ formatCurrency(computedSubtotal) }}
                  </div>
                </div>

                <div>
                  <label class="block text-slate-300 font-bold mb-1">Bill Tax / GST (₹)</label>
                  <input type="number" step="0.01" min="0" v-model.number="billForm.tax_amount" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 font-mono font-bold text-white text-sm focus:outline-none focus:border-red-500" />
                </div>

                <div>
                  <label class="block text-slate-300 font-bold mb-1">Bill Overall Discount (₹)</label>
                  <input type="number" step="0.01" min="0" v-model.number="billForm.discount_amount" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 font-mono font-bold text-white text-sm focus:outline-none focus:border-red-500" />
                </div>
              </div>

              <div class="border-t border-slate-800 pt-3 grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs items-center">
                <div>
                  <span class="text-slate-400 font-medium">Grand Total Liability:</span>
                  <div class="font-mono font-black text-xl text-amber-400">₹{{ formatCurrency(computedGrandTotal) }}</div>
                </div>

                <div>
                  <label class="block text-emerald-400 font-bold mb-1">Initial Paid Amount (₹)</label>
                  <input type="number" step="0.01" min="0" :max="computedGrandTotal" v-model.number="billForm.paid_amount" class="w-full bg-slate-800 border border-emerald-500/50 rounded-xl px-3 py-2 font-mono font-black text-emerald-400 text-sm focus:outline-none" />
                </div>

                <div>
                  <span class="text-slate-400 font-medium">Remaining Due Amount:</span>
                  <div class="font-mono font-black text-xl text-red-400">₹{{ formatCurrency(computedDueAmount) }}</div>
                </div>
              </div>

              <!-- Payment details if paid_amount > 0 -->
              <div v-if="billForm.paid_amount > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-800 text-xs">
                <div>
                  <label class="block text-slate-300 font-bold mb-1">Payment Method</label>
                  <select v-model="billForm.payment_method" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 font-bold text-white">
                    <option value="cash">💵 Cash</option>
                    <option value="upi">📱 UPI / QR</option>
                    <option value="bank_transfer">🏦 Bank Transfer</option>
                    <option value="card">💳 Card</option>
                    <option value="other">Other</option>
                  </select>
                </div>
                <div>
                  <label class="block text-slate-300 font-bold mb-1">Transaction Ref #</label>
                  <input type="text" v-model="billForm.transaction_reference" placeholder="UTR / Ref ID" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 font-bold text-white" />
                </div>
              </div>

              <div>
                <label class="block text-slate-300 font-bold mb-1">Bill Notes / Remarks</label>
                <input type="text" v-model="billForm.notes" placeholder="Notes for vendor bill..." class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 font-bold text-white text-xs" />
              </div>
            </div>
          </div>

          <!-- Actions Footer -->
          <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-4 sm:px-6 py-3 sm:py-4 shrink-0 bg-slate-50 sm:rounded-b-3xl z-10">
            <button @click="showCreateBillModal = false" type="button" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 rounded-xl font-bold text-xs cursor-pointer">Cancel</button>
            <button
              @click="submitCreateBill"
              :disabled="saving"
              type="button"
              class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md shadow-red-600/20 uppercase tracking-wider cursor-pointer flex items-center gap-2"
            >
              <span v-if="saving" class="animate-spin">⏳</span>
              <span>{{ saving ? 'Creating Purchase Bill...' : 'Create Purchase Bill' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- MAKE PAYMENT MODAL -->
    <Teleport to="body">
      <div v-if="showPayModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-[400] flex items-center justify-center p-4 font-sans">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-5">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h2 class="text-base font-black text-slate-900">💳 Record Supplier Payment</h2>
            <button @click="showPayModal = false" class="text-slate-400 font-bold cursor-pointer">✕</button>
          </div>

          <div v-if="activeBillForPay" class="p-3 bg-red-50 rounded-xl border border-red-200 text-xs space-y-1">
            <div class="font-black text-red-900">Supplier: {{ activeBillForPay.supplier_name }}</div>
            <div class="text-[11px] text-slate-600">Bill Number: {{ activeBillForPay.bill_number }}</div>
            <div class="font-bold text-slate-900">Current Bill Due: ₹{{ formatCurrency(activeBillForPay.due_amount) }}</div>
          </div>

          <div class="space-y-3 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Payment Amount (₹) <span class="text-red-600">*</span></label>
              <input type="number" step="0.01" v-model.number="payForm.amount" class="w-full bg-slate-50 border rounded-xl px-3 py-2 font-mono font-black text-slate-900 text-sm" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Payment Method</label>
              <select v-model="payForm.payment_method" class="w-full bg-slate-50 border rounded-xl px-3 py-2 font-bold text-slate-900">
                <option value="cash">💵 Cash</option>
                <option value="upi">📱 UPI / QR</option>
                <option value="bank_transfer">🏦 Bank Transfer (NEFT/RTGS)</option>
                <option value="card">💳 Card</option>
                <option value="other">Other</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Transaction Ref #</label>
              <input type="text" v-model="payForm.transaction_reference" placeholder="UTR / Ref ID" class="w-full bg-slate-50 border rounded-xl px-3 py-2 font-bold text-slate-900" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Payment Date</label>
              <input type="date" v-model="payForm.payment_date" class="w-full bg-slate-50 border rounded-xl px-3 py-2 font-bold text-slate-900" />
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
            <button @click="showPayModal = false" type="button" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl font-bold text-xs cursor-pointer">Cancel</button>
            <button @click="submitPayment" :disabled="saving" type="button" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-black text-xs shadow-md shadow-emerald-600/20 cursor-pointer">
              {{ saving ? 'Recording Payment...' : 'Submit Payment' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- DELETE PURCHASE BILL CONFIRMATION MODAL / BOTTOM SHEET -->
    <Teleport to="body">
      <div v-if="deleteBillTarget" class="fixed inset-0 z-[400] bg-slate-900/60 backdrop-blur-xs flex items-end sm:items-center justify-center p-0 sm:p-4">
        <div class="bg-white w-full sm:max-w-lg rounded-t-3xl sm:rounded-3xl border border-slate-200 shadow-2xl p-6 space-y-5 animate-in slide-in-from-bottom duration-200">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
              <span class="p-2 bg-red-100 text-red-600 rounded-xl text-lg">⚠️</span>
              <div>
                <h3 class="text-base font-black text-slate-900">Delete Purchase Transaction?</h3>
                <p class="text-xs text-slate-500 font-medium">Safe Purchase Reversal</p>
              </div>
            </div>
            <button @click="deleteBillTarget = null" class="text-slate-400 hover:text-slate-700 font-bold text-lg cursor-pointer">✕</button>
          </div>

          <div class="space-y-3">
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/80 space-y-2 text-xs">
              <div class="flex justify-between">
                <span class="text-slate-500 font-medium">Purchase Bill Number:</span>
                <span class="font-mono font-black text-red-600">{{ deleteBillTarget.bill_number }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-500 font-medium">Bill Date:</span>
                <span class="font-mono font-bold text-slate-800">{{ deleteBillTarget.bill_date }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-500 font-medium">Supplier:</span>
                <span class="font-bold text-slate-900">{{ deleteBillTarget.supplier_name }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-500 font-medium">Item Count:</span>
                <span class="font-mono font-bold text-slate-800">{{ deleteBillTarget.items?.length || 0 }} items</span>
              </div>
              <div class="flex justify-between border-t border-slate-200 pt-2">
                <span class="text-slate-500 font-medium">Grand Total:</span>
                <span class="font-mono font-black text-slate-900 text-sm">₹{{ formatCurrency(deleteBillTarget.grand_total) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-500 font-medium">Paid Amount:</span>
                <span class="font-mono font-bold text-emerald-700">₹{{ formatCurrency(deleteBillTarget.paid_amount) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-500 font-medium">Due Amount:</span>
                <span class="font-mono font-bold text-red-600">₹{{ formatCurrency(deleteBillTarget.due_amount) }}</span>
              </div>
            </div>

            <div class="p-3.5 bg-amber-50 border border-amber-200 rounded-2xl text-xs text-amber-900 space-y-1">
              <span class="font-black block">⚠️ Stock & Supplier Reversal Warning:</span>
              <p class="font-medium text-[11px] leading-relaxed">
                This action will reverse inventory stock additions and supplier financial liabilities. Deletion will be blocked if stock from this purchase has already been sold or consumed in subsequent transactions.
              </p>
            </div>

            <div v-if="deleteError" class="p-3 bg-red-50 border border-red-200 rounded-2xl text-xs font-bold text-red-700 leading-relaxed">
              {{ deleteError }}
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 pt-2">
            <button
              @click="deleteBillTarget = null"
              :disabled="isDeleting"
              class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs transition-colors cursor-pointer"
            >
              Cancel
            </button>

            <button
              @click="executeDeleteBill"
              :disabled="isDeleting"
              class="px-5 py-2.5 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white rounded-xl font-black text-xs shadow-md shadow-red-600/20 transition-all flex items-center gap-2 cursor-pointer"
            >
              <span v-if="isDeleting" class="animate-spin">⏳</span>
              <span>{{ isDeleting ? 'Reversing Purchase...' : 'Confirm Safe Delete' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import api from '../../services/api';

const loading = ref(false);
const saving = ref(false);
const showPayModal = ref(false);
const showCreateBillModal = ref(false);
const deleteBillTarget = ref(null);
const isDeleting = ref(false);
const deleteError = ref(null);
const createBillError = ref(null);

const billsList = ref([]);
const suppliersList = ref([]);
const pendingPosList = ref([]);
const pendingGrnsList = ref([]);
const selectedSupplier = ref(null);
const activeBillForPay = ref(null);

const selectedPoId = ref('');
const selectedGrnId = ref('');

const productSearchQuery = ref('');
const searchResultProducts = ref([]);
const searchLoading = ref(false);
const showSearchDropdown = ref(false);
const selectedFootwearProducts = ref([]);

const filters = reactive({
  search: '',
  payment_status: '',
});

const pagination = reactive({
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1,
});

const payForm = reactive({
  amount: 0,
  payment_method: 'cash',
  transaction_reference: '',
  payment_date: new Date().toISOString().substring(0, 10),
});

const billForm = reactive({
  supplier_id: '',
  supplier_invoice_number: '',
  bill_date: new Date().toISOString().substring(0, 10),
  due_date: new Date(Date.now() + 30 * 86400000).toISOString().substring(0, 10),
  payment_terms: 'Net 30 Days',
  tax_amount: 0,
  discount_amount: 0,
  paid_amount: 0,
  payment_method: 'cash',
  transaction_reference: '',
  notes: '',
});

watch([showCreateBillModal, showPayModal, deleteBillTarget], (modalStates) => {
  if (modalStates.some(Boolean)) {
    document.body.style.overflow = 'hidden';
  } else {
    document.body.style.overflow = '';
  }
});

const filteredPos = computed(() => {
  if (!billForm.supplier_id) return pendingPosList.value;
  return pendingPosList.value.filter(p => p.supplier_id === Number(billForm.supplier_id));
});

const filteredGrns = computed(() => {
  if (!billForm.supplier_id) return pendingGrnsList.value;
  return pendingGrnsList.value.filter(g => g.supplier_id === Number(billForm.supplier_id));
});

const computedSubtotal = computed(() => {
  let sub = 0;
  selectedFootwearProducts.value.forEach(p => {
    p.variants?.forEach(v => {
      v.sizes?.forEach(s => {
        const qty = Number(s.quantity_ordered || 0);
        const cost = Number(s.cost_price || 0);
        const disc = Number(s.discount_amount || 0);
        const tax = Number(s.tax_amount || 0);
        if (qty > 0) {
          sub += (qty * cost) - disc + tax;
        }
      });
    });
  });
  return Math.round(sub * 100) / 100;
});

const computedGrandTotal = computed(() => {
  return Math.max(0, Math.round((computedSubtotal.value + Number(billForm.tax_amount || 0) - Number(billForm.discount_amount || 0)) * 100) / 100);
});

const computedDueAmount = computed(() => {
  return Math.max(0, Math.round((computedGrandTotal.value - Number(billForm.paid_amount || 0)) * 100) / 100);
});

let debounceTimer = null;
function debouncedFetch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => fetchBills(1), 300);
}

async function fetchBills(page = 1) {
  loading.value = true;
  try {
    const res = await api.get('/purchases/bills', {
      params: {
        page,
        per_page: pagination.per_page,
        search: filters.search || undefined,
        payment_status: filters.payment_status || undefined,
      },
    });
    const payload = res.data?.data || res.data;
    billsList.value = payload.items || [];
    if (payload.pagination) Object.assign(pagination, payload.pagination);
  } catch (err) {
    console.error('Failed to load purchase bills:', err);
  } finally {
    loading.value = false;
  }
}

async function openCreateBillModal() {
  createBillError.value = null;
  billForm.supplier_id = '';
  billForm.supplier_invoice_number = '';
  billForm.bill_date = new Date().toISOString().substring(0, 10);
  billForm.due_date = new Date(Date.now() + 30 * 86400000).toISOString().substring(0, 10);
  billForm.payment_terms = 'Net 30 Days';
  billForm.tax_amount = 0;
  billForm.discount_amount = 0;
  billForm.paid_amount = 0;
  billForm.payment_method = 'cash';
  billForm.transaction_reference = '';
  billForm.notes = '';

  selectedSupplier.value = null;
  selectedPoId.value = '';
  selectedGrnId.value = '';
  selectedFootwearProducts.value = [];
  productSearchQuery.value = '';
  searchResultProducts.value = [];

  showCreateBillModal.value = true;

  try {
    const [supRes, poRes, grnRes] = await Promise.all([
      api.get('/suppliers', { params: { per_page: 100 } }),
      api.get('/purchases/orders', { params: { per_page: 100 } }),
      api.get('/purchases/grn', { params: { per_page: 100 } }),
    ]);

    suppliersList.value = supRes.data?.data?.items || supRes.data?.data || supRes.data?.items || [];
    pendingPosList.value = poRes.data?.data?.items || poRes.data?.items || [];
    pendingGrnsList.value = grnRes.data?.data?.items || grnRes.data?.items || [];
  } catch (e) {
    console.error('Failed to load master data for Purchase Bill creation:', e);
  }
}

function onSupplierSelect() {
  selectedSupplier.value = suppliersList.value.find(s => s.id === Number(billForm.supplier_id)) || null;
}

async function onPoSelect() {
  if (!selectedPoId.value) return;
  selectedGrnId.value = '';
  try {
    const res = await api.get(`/purchases/orders/${selectedPoId.value}`);
    const po = res.data?.data || res.data;
    if (po) {
      if (po.supplier_id) {
        billForm.supplier_id = po.supplier_id;
        onSupplierSelect();
      }
      billForm.supplier_invoice_number = po.supplier_invoice_number || billForm.supplier_invoice_number;

      // Group PO items into footwear product structure
      const groupedProducts = [];
      (po.items || []).forEach(pi => {
        const article = pi.article_number || pi.variantSize?.variant?.product?.article_number || 'N/A';
        const productName = pi.product_name || pi.variantSize?.variant?.product?.name || 'Footwear Item';
        const brandName = pi.brand_name || pi.variantSize?.variant?.product?.brand?.name || 'Generic';
        const prodId = pi.product_id || pi.variantSize?.variant?.product_id || 1;

        let prod = groupedProducts.find(p => p.id === prodId);
        if (!prod) {
          prod = {
            id: prodId,
            product_name: productName,
            article_number: article,
            brand_name: brandName,
            variants: [],
          };
          groupedProducts.push(prod);
        }

        const colorName = pi.color_name || pi.variantSize?.variant?.color?.name || 'Std';
        const varId = pi.variant_id || pi.variantSize?.variant_id || 1;

        let variant = prod.variants.find(v => v.variant_id === varId);
        if (!variant) {
          variant = { variant_id: varId, color_name: colorName, sizes: [] };
          prod.variants.push(variant);
        }

        variant.sizes.push({
          product_variant_size_id: pi.product_variant_size_id,
          size_display: pi.size_display || pi.variantSize?.size?.size_number || 'N/A',
          sku: pi.sku || pi.variantSize?.sku || 'N/A',
          quantity_ordered: pi.quantity_ordered || pi.quantity || 0,
          cost_price: (float)(pi.cost_price || 0),
          discount_amount: 0,
          tax_amount: 0,
        });
      });

      selectedFootwearProducts.value = groupedProducts;
    }
  } catch (e) {
    console.error('Failed to auto-populate PO items:', e);
  }
}

async function onGrnSelect() {
  if (!selectedGrnId.value) return;
  selectedPoId.value = '';
  try {
    const res = await api.get(`/purchases/grn/${selectedGrnId.value}`);
    const grn = res.data?.data || res.data;
    if (grn) {
      if (grn.supplier_id) {
        billForm.supplier_id = grn.supplier_id;
        onSupplierSelect();
      }
      billForm.supplier_invoice_number = grn.supplier_invoice_number || billForm.supplier_invoice_number;

      const groupedProducts = [];
      (grn.items || []).forEach(gi => {
        const article = gi.article_number || gi.variantSize?.variant?.product?.article_number || 'N/A';
        const productName = gi.product_name || gi.variantSize?.variant?.product?.name || 'Footwear Item';
        const brandName = gi.brand_name || gi.variantSize?.variant?.product?.brand?.name || 'Generic';
        const prodId = gi.product_id || gi.variantSize?.variant?.product_id || 1;

        let prod = groupedProducts.find(p => p.id === prodId);
        if (!prod) {
          prod = {
            id: prodId,
            product_name: productName,
            article_number: article,
            brand_name: brandName,
            variants: [],
          };
          groupedProducts.push(prod);
        }

        const colorName = gi.color_name || gi.variantSize?.variant?.color?.name || 'Std';
        const varId = gi.variant_id || gi.variantSize?.variant_id || 1;

        let variant = prod.variants.find(v => v.variant_id === varId);
        if (!variant) {
          variant = { variant_id: varId, color_name: colorName, sizes: [] };
          prod.variants.push(variant);
        }

        variant.sizes.push({
          product_variant_size_id: gi.product_variant_size_id,
          size_display: gi.size_display || gi.variantSize?.size?.size_number || 'N/A',
          sku: gi.sku || gi.variantSize?.sku || 'N/A',
          quantity_ordered: gi.quantity_received || gi.quantity || 0,
          cost_price: Number(gi.cost_price || 0),
          discount_amount: 0,
          tax_amount: 0,
        });
      });

      selectedFootwearProducts.value = groupedProducts;
    }
  } catch (e) {
    console.error('Failed to auto-populate GRN items:', e);
  }
}

let searchDebounce = null;
function onProductSearchInput() {
  clearTimeout(searchDebounce);
  const q = productSearchQuery.value.trim();
  if (!q) {
    searchResultProducts.value = [];
    showSearchDropdown.value = false;
    return;
  }

  showSearchDropdown.value = true;
  searchLoading.value = true;

  searchDebounce = setTimeout(async () => {
    try {
      const res = await api.get('/products/size-matrix-search', { params: { q } });
      const list = Array.isArray(res) ? res : (Array.isArray(res?.data) ? res.data : (Array.isArray(res?.data?.items) ? res.data.items : []));
      searchResultProducts.value = list;
    } catch (e) {
      console.error('Failed to search size matrix:', e);
      searchResultProducts.value = [];
    } finally {
      searchLoading.value = false;
    }
  }, 300);
}

function addFootwearProductToMatrix(prod) {
  if (selectedFootwearProducts.value.some(p => p.id === prod.id)) {
    showSearchDropdown.value = false;
    productSearchQuery.value = '';
    return;
  }

  const cloned = JSON.parse(JSON.stringify(prod));
  cloned.variants?.forEach(v => {
    v.sizes?.forEach(s => {
      s.quantity_ordered = 0;
      s.cost_price = s.cost_price || 0;
      s.discount_amount = 0;
      s.tax_amount = 0;
    });
  });

  selectedFootwearProducts.value.push(cloned);
  searchResultProducts.value = [];
  showSearchDropdown.value = false;
  productSearchQuery.value = '';
}

function removeFootwearProduct(idx) {
  selectedFootwearProducts.value.splice(idx, 1);
}

function promptBulkFillRate(prod) {
  const input = prompt(`Enter Purchase Rate (₹) for all sizes of "${prod.product_name}":`, '500');
  if (input !== null && !isNaN(Number(input))) {
    const rate = Math.max(0, Number(input));
    prod.variants?.forEach(v => {
      v.sizes?.forEach(s => { s.cost_price = rate; });
    });
  }
}

function promptBulkFillQty(prod) {
  const input = prompt(`Enter Quantity for all sizes of "${prod.product_name}":`, '10');
  if (input !== null && !isNaN(Number(input))) {
    const qty = Math.max(0, parseInt(input, 10));
    prod.variants?.forEach(v => {
      v.sizes?.forEach(s => { s.quantity_ordered = qty; });
    });
  }
}

async function submitCreateBill() {
  createBillError.value = null;

  if (!billForm.supplier_id) {
    createBillError.value = 'Supplier selection is required to issue a Purchase Bill.';
    return;
  }

  const itemsToSubmit = [];
  selectedFootwearProducts.value.forEach(p => {
    p.variants?.forEach(v => {
      v.sizes?.forEach(s => {
        const qty = Number(s.quantity_ordered || 0);
        if (qty > 0) {
          itemsToSubmit.push({
            product_variant_size_id: s.product_variant_size_id || s.id,
            quantity: qty,
            cost_price: Number(s.cost_price || 0),
            discount_amount: Number(s.discount_amount || 0),
            tax_amount: Number(s.tax_amount || 0),
          });
        }
      });
    });
  });

  if (itemsToSubmit.length === 0) {
    createBillError.value = 'Please enter at least one line item with quantity > 0 for this Purchase Bill.';
    return;
  }

  saving.value = true;
  try {
    const payload = {
      supplier_id: Number(billForm.supplier_id),
      supplier_invoice_number: billForm.supplier_invoice_number || null,
      purchase_order_id: selectedPoId.value ? Number(selectedPoId.value) : null,
      goods_receive_id: selectedGrnId.value ? Number(selectedGrnId.value) : null,
      bill_date: billForm.bill_date,
      due_date: billForm.due_date,
      payment_terms: billForm.payment_terms || 'Net 30 Days',
      tax_amount: Number(billForm.tax_amount || 0),
      discount_amount: Number(billForm.discount_amount || 0),
      paid_amount: Number(billForm.paid_amount || 0),
      payment_method: Number(billForm.paid_amount || 0) > 0 ? billForm.payment_method : null,
      transaction_reference: Number(billForm.paid_amount || 0) > 0 ? billForm.transaction_reference : null,
      notes: billForm.notes || null,
      items: itemsToSubmit,
    };

    await api.post('/purchases/bills', payload);
    showCreateBillModal.value = false;
    fetchBills(1);
  } catch (err) {
    console.error('Failed to create purchase bill:', err);
    createBillError.value = err.response?.data?.message || 'Failed to create Purchase Bill. Please check form entries and retry.';
  } finally {
    saving.value = false;
  }
}

function openPayModal(bill) {
  activeBillForPay.value = bill;
  payForm.amount = bill.due_amount;
  payForm.payment_method = 'cash';
  payForm.transaction_reference = '';
  payForm.payment_date = new Date().toISOString().substring(0, 10);
  showPayModal.value = true;
}

async function submitPayment() {
  if (!payForm.amount || payForm.amount <= 0) {
    alert('Payment amount must be greater than zero.');
    return;
  }

  saving.value = true;
  try {
    await api.post(`/purchases/bills/${activeBillForPay.value.id}/pay`, payForm);
    showPayModal.value = false;
    fetchBills(pagination.current_page);
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to record payment.');
  } finally {
    saving.value = false;
  }
}

function confirmDeleteBill(bill) {
  deleteBillTarget.value = bill;
  deleteError.value = null;
}

async function executeDeleteBill() {
  if (!deleteBillTarget.value) return;
  isDeleting.value = true;
  deleteError.value = null;
  try {
    const billId = deleteBillTarget.value.id;
    await api.delete(`/purchases/bills/${billId}`);
    deleteBillTarget.value = null;
    fetchBills(pagination.current_page);
  } catch (err) {
    console.error('Failed to delete purchase bill:', err);
    deleteError.value = err.response?.data?.message || 'Failed to delete purchase transaction.';
  } finally {
    isDeleting.value = false;
  }
}

function formatPaymentStatus(st) {
  if (st === 'paid') return 'PAID';
  if (st === 'partially_paid') return 'PARTIALLY PAID';
  return 'UNPAID';
}

function getPaymentBadgeClass(st) {
  if (st === 'paid') return 'px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-black';
  if (st === 'partially_paid') return 'px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-black';
  return 'px-2 py-0.5 rounded-full bg-red-100 text-red-800 text-[10px] font-black';
}

function formatCurrency(val) {
  return Number(val || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

onMounted(() => {
  fetchBills(1);
});
</script>
