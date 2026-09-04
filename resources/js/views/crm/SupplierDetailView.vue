<template>
  <div class="space-y-6 antialiased font-sans max-w-7xl mx-auto pb-16">
    <!-- Top Header & Navigation -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
      <div>
        <div class="flex items-center gap-2 text-xs font-bold text-slate-500 mb-1">
          <router-link to="/admin/suppliers" class="hover:text-red-600 transition-colors">Suppliers</router-link>
          <span>/</span>
          <span class="text-slate-900">Supplier Management Dashboard</span>
        </div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
          <span>{{ supplier.company_name || supplier.name || 'Vendor Profile' }}</span>
          <span class="text-xs font-mono px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 font-black border border-slate-200">
            {{ supplier.code || ('SUP-' + String(supplier.id || id).padStart(4, '0')) }}
          </span>
          <span
            :class="[
              'text-[10px] font-black uppercase px-2 py-0.5 rounded border',
              supplier.is_active ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'
            ]"
          >
            {{ supplier.is_active ? 'Active Vendor' : 'Inactive' }}
          </span>
        </h1>
      </div>

      <div class="flex items-center gap-3 flex-wrap">
        <button
          type="button"
          @click="showPaymentModal = true"
          class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-black text-xs rounded-xl shadow-xs transition-all flex items-center gap-1.5"
        >
          <span>💳</span>
          <span>Make Payment</span>
        </button>

        <router-link
          v-if="hasPermission('suppliers.edit')"
          :to="'/admin/suppliers/' + id + '/edit'"
          class="px-4 py-2 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 font-black text-xs rounded-xl transition-all flex items-center gap-1.5"
        >
          <span>✏️</span>
          <span>Edit Profile</span>
        </router-link>

        <router-link
          to="/admin/suppliers"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-colors"
        >
          ← Back to Directory
        </router-link>
      </div>
    </div>

    <!-- Skeleton Loading -->
    <div v-if="loadingSupplier" class="p-8 bg-white rounded-3xl border border-slate-200 animate-pulse space-y-4">
      <div class="h-6 bg-slate-200 rounded w-1/3"></div>
      <div class="h-4 bg-slate-200 rounded w-1/2"></div>
      <div class="h-20 bg-slate-100 rounded-2xl"></div>
    </div>

    <div v-else-if="supplierError" class="p-6 bg-red-50 border border-red-200 rounded-2xl text-center space-y-2">
      <span class="text-2xl">⚠️</span>
      <p class="text-xs text-red-700 font-bold">{{ supplierError }}</p>
    </div>

    <div v-else class="space-y-6">
      <!-- 1. LIVE FINANCIAL KPI SUMMARY MATRIX (8 CARDS) -->
      <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-4 gap-4 text-xs">
        <!-- Total Purchases -->
        <div class="p-5 bg-white rounded-2xl border border-slate-200/90 shadow-xs space-y-1">
          <div class="flex items-center justify-between text-slate-500 font-bold uppercase text-[10px]">
            <span>Total Purchases</span>
            <span class="text-base">🛍️</span>
          </div>
          <div class="text-xl sm:text-2xl font-black text-slate-900 font-mono">
            ₹{{ formatCurrency(summary.total_spend || supplier.total_purchases_amount || 0) }}
          </div>
          <span class="text-[10px] text-slate-400 font-medium">Gross Procurement Value</span>
        </div>

        <!-- Total Paid -->
        <div class="p-5 bg-white rounded-2xl border border-slate-200/90 shadow-xs space-y-1">
          <div class="flex items-center justify-between text-slate-500 font-bold uppercase text-[10px]">
            <span>Total Paid</span>
            <span class="text-base">💳</span>
          </div>
          <div class="text-xl sm:text-2xl font-black text-emerald-700 font-mono">
            ₹{{ formatCurrency(summary.total_paid_amount || supplier.total_paid_amount || 0) }}
          </div>
          <span class="text-[10px] text-slate-400 font-medium">Recorded Payments</span>
        </div>

        <!-- Total Purchase Returns -->
        <div class="p-5 bg-white rounded-2xl border border-slate-200/90 shadow-xs space-y-1">
          <div class="flex items-center justify-between text-slate-500 font-bold uppercase text-[10px]">
            <span>Purchase Returns</span>
            <span class="text-base">↩️</span>
          </div>
          <div class="text-xl sm:text-2xl font-black text-amber-700 font-mono">
            ₹{{ formatCurrency(summary.total_returned_value || supplier.total_returns_amount || 0) }}
          </div>
          <span class="text-[10px] text-slate-400 font-medium">{{ summary.number_of_returns || 0 }} Vendor Return Notes</span>
        </div>

        <!-- Current Due / Payable -->
        <div class="p-5 bg-white rounded-2xl border border-slate-200/90 shadow-xs space-y-1 bg-red-50/30 border-red-200/80">
          <div class="flex items-center justify-between text-red-600 font-bold uppercase text-[10px]">
            <span>Current Due / Payable</span>
            <span class="text-base">⚠️</span>
          </div>
          <div class="text-xl sm:text-2xl font-black text-red-600 font-mono">
            ₹{{ formatCurrency(summary.total_due_amount || supplier.current_due_amount || supplier.current_balance || 0) }}
          </div>
          <span class="text-[10px] text-red-500 font-bold">Outstanding Payable Position</span>
        </div>

        <!-- Advance / Supplier Credit -->
        <div class="p-5 bg-white rounded-2xl border border-slate-200/90 shadow-xs space-y-1">
          <div class="flex items-center justify-between text-slate-500 font-bold uppercase text-[10px]">
            <span>Advance / Credit</span>
            <span class="text-base">🏦</span>
          </div>
          <div class="text-xl sm:text-2xl font-black text-purple-700 font-mono">
            ₹{{ formatCurrency(supplier.supplier_credit_amount || 0) }}
          </div>
          <span class="text-[10px] text-slate-400 font-medium">Prepaid Advance Ledger</span>
        </div>

        <!-- Number of Purchase Bills -->
        <div class="p-5 bg-white rounded-2xl border border-slate-200/90 shadow-xs space-y-1">
          <div class="flex items-center justify-between text-slate-500 font-bold uppercase text-[10px]">
            <span>Purchase Bills</span>
            <span class="text-base">📑</span>
          </div>
          <div class="text-2xl font-black text-slate-900 font-mono">
            {{ summary.total_purchase_orders || 0 }}
          </div>
          <span class="text-[10px] text-slate-400 font-medium">Procurement Invoices</span>
        </div>

        <!-- Payment Terms & Credit Limit -->
        <div class="p-5 bg-white rounded-2xl border border-slate-200/90 shadow-xs space-y-1">
          <div class="flex items-center justify-between text-slate-500 font-bold uppercase text-[10px]">
            <span>Credit Terms</span>
            <span class="text-base">⏳</span>
          </div>
          <div class="text-sm font-black text-slate-900 font-mono truncate">
            {{ supplier.payment_terms || 'Net 30 Days' }}
          </div>
          <span class="text-[10px] text-slate-400 font-medium">Limit: ₹{{ formatCurrency(supplier.credit_limit) }}</span>
        </div>

        <!-- Last Purchase Date -->
        <div class="p-5 bg-white rounded-2xl border border-slate-200/90 shadow-xs space-y-1">
          <div class="flex items-center justify-between text-slate-500 font-bold uppercase text-[10px]">
            <span>Last Purchase Date</span>
            <span class="text-base">🕒</span>
          </div>
          <div class="text-sm font-black text-slate-900 font-mono">
            {{ summary.last_order_date ? formatDate(summary.last_order_date) : 'No purchases' }}
          </div>
          <span class="text-[10px] text-slate-400 font-medium">Most Recent Invoice</span>
        </div>
      </div>

      <!-- 2. TABBED NAVIGATION SYSTEM -->
      <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden">
        <div class="border-b border-slate-200 bg-slate-50/80 px-6 pt-3 flex items-center gap-2 overflow-x-auto text-xs">
          <button
            v-for="t in [
              { key: 'overview', label: 'Overview', icon: '👤' },
              { key: 'purchases', label: 'Purchases (' + (summary.total_purchase_orders || 0) + ')', icon: '🧾' },
              { key: 'payments', label: 'Payments', icon: '💳' },
              { key: 'returns', label: 'Returns (' + (summary.number_of_returns || 0) + ')', icon: '↩️' },
              { key: 'ledger', label: 'Financial Ledger', icon: '📖' },
            ]"
            :key="t.key"
            @click="activeTab = t.key"
            :class="[
              'px-4 py-3 font-black transition-all border-b-2 flex items-center gap-1.5 whitespace-nowrap',
              activeTab === t.key ? 'border-red-600 text-red-600 bg-white rounded-t-xl' : 'border-transparent text-slate-500 hover:text-slate-900'
            ]"
          >
            <span>{{ t.icon }}</span>
            <span>{{ t.label }}</span>
          </button>
        </div>

        <div class="p-6 space-y-6">
          <!-- TAB 1: OVERVIEW -->
          <div v-if="activeTab === 'overview'" class="grid grid-cols-1 lg:grid-cols-3 gap-6 text-xs">
            <!-- Vendor Details -->
            <div class="lg:col-span-2 p-6 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-4">
              <h3 class="font-black text-sm text-slate-900 uppercase tracking-tight flex items-center gap-2">
                <span>🏢</span>
                <span>Vendor Master Information</span>
              </h3>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-slate-700">
                <div>
                  <span class="font-bold text-slate-400 uppercase text-[10px] block">Contact Person</span>
                  <div class="font-bold text-slate-900 text-sm">{{ supplier.name || 'N/A' }}</div>
                </div>

                <div>
                  <span class="font-bold text-slate-400 uppercase text-[10px] block">Company Name</span>
                  <div class="font-bold text-slate-900 text-sm">{{ supplier.company_name || 'N/A' }}</div>
                </div>

                <div>
                  <span class="font-bold text-slate-400 uppercase text-[10px] block">Primary Phone</span>
                  <a :href="'tel:' + (supplier.phone || '').replace(/\s+/g, '')" class="font-mono font-bold text-slate-900 hover:text-red-600">
                    {{ supplier.phone || 'N/A' }}
                  </a>
                </div>

                <div>
                  <span class="font-bold text-slate-400 uppercase text-[10px] block">Alternate Mobile</span>
                  <div class="font-mono font-bold text-slate-900">{{ supplier.alternate_mobile || 'N/A' }}</div>
                </div>

                <div>
                  <span class="font-bold text-slate-400 uppercase text-[10px] block">GSTIN</span>
                  <div class="font-mono font-bold text-slate-900 uppercase">{{ supplier.gstin || 'Unregistered' }}</div>
                </div>

                <div>
                  <span class="font-bold text-slate-400 uppercase text-[10px] block">PAN Number</span>
                  <div class="font-mono font-bold text-slate-900 uppercase">{{ supplier.pan || 'N/A' }}</div>
                </div>

                <div>
                  <span class="font-bold text-slate-400 uppercase text-[10px] block">Email Address</span>
                  <div class="font-bold text-slate-900">{{ supplier.email || 'None registered' }}</div>
                </div>

                <div>
                  <span class="font-bold text-slate-400 uppercase text-[10px] block">Location / Hub</span>
                  <div class="font-bold text-slate-900">{{ supplier.city || 'Kolkata' }}, {{ supplier.state || 'West Bengal' }} - {{ supplier.pincode || 'N/A' }}</div>
                </div>
              </div>

              <div class="pt-3 border-t border-slate-200">
                <span class="font-bold text-slate-400 uppercase text-[10px] block">Factory / Billing Address</span>
                <p class="font-medium text-slate-800 leading-relaxed mt-0.5">{{ supplier.address || 'Factory address not specified.' }}</p>
              </div>

              <div v-if="supplier.notes" class="pt-3 border-t border-slate-200">
                <span class="font-bold text-slate-400 uppercase text-[10px] block">Vendor Notes & Preferred Footwear Articles</span>
                <p class="p-3 bg-white border border-slate-200 rounded-xl text-slate-800 font-medium mt-1">
                  {{ supplier.notes }}
                </p>
              </div>
            </div>

            <!-- Quick Action & Financial Position -->
            <div class="p-6 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-4">
              <h3 class="font-black text-sm text-slate-900 uppercase tracking-tight flex items-center gap-2">
                <span>⚡</span>
                <span>Quick Actions</span>
              </h3>

              <div class="space-y-2">
                <button
                  type="button"
                  @click="showPaymentModal = true"
                  class="w-full py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold text-xs transition-colors flex items-center justify-center gap-2 shadow-xs"
                >
                  <span>💳</span>
                  <span>Record Supplier Payment</span>
                </button>

                <router-link
                  to="/admin/purchase-orders"
                  class="w-full py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold text-xs transition-colors flex items-center justify-center gap-2 shadow-xs block text-center"
                >
                  <span>🛍️</span>
                  <span>Create Purchase Order</span>
                </router-link>
              </div>

              <div class="pt-4 border-t border-slate-200 space-y-2">
                <span class="font-bold text-slate-400 uppercase text-[10px] block">Opening Position</span>
                <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-slate-200">
                  <span class="font-bold text-slate-700">Opening Balance:</span>
                  <span class="font-mono font-black text-slate-900">
                    ₹{{ formatCurrency(supplier.opening_balance) }} ({{ supplier.opening_balance_type || 'payable' }})
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- TAB 2: PURCHASES -->
          <div v-else-if="activeTab === 'purchases'" class="space-y-4">
            <!-- Filter Bar -->
            <div class="bg-slate-50 border border-slate-200 p-4 rounded-2xl grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
              <div>
                <label class="font-bold text-slate-700 block mb-1">Search Invoice / Bill #</label>
                <input
                  v-model="purchasesFilter.search"
                  @input="fetchPurchaseHistory(1)"
                  type="text"
                  placeholder="e.g. PO-001 or Inv..."
                  class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900"
                />
              </div>

              <div>
                <label class="font-bold text-slate-700 block mb-1">Status</label>
                <select
                  v-model="purchasesFilter.status"
                  @change="fetchPurchaseHistory(1)"
                  class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900"
                >
                  <option value="">All Statuses</option>
                  <option value="received">Received / Completed</option>
                  <option value="ordered">Ordered</option>
                  <option value="partial">Partial</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>

              <div>
                <label class="font-bold text-slate-700 block mb-1">Date From</label>
                <input
                  v-model="purchasesFilter.start_date"
                  @change="fetchPurchaseHistory(1)"
                  type="date"
                  class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900"
                />
              </div>

              <div>
                <label class="font-bold text-slate-700 block mb-1">Date To</label>
                <input
                  v-model="purchasesFilter.end_date"
                  @change="fetchPurchaseHistory(1)"
                  type="date"
                  class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900"
                />
              </div>
            </div>

            <!-- Purchases Table -->
            <div v-if="loadingPurchases" class="py-12 text-center text-xs font-bold text-slate-400">
              Loading purchase bills...
            </div>

            <div v-else-if="purchaseHistory.length === 0" class="py-12 text-center text-xs text-slate-500 font-medium bg-slate-50 rounded-2xl border border-slate-200">
              No purchase orders recorded for this supplier.
            </div>

            <div v-else class="overflow-x-auto rounded-2xl border border-slate-200">
              <table class="w-full text-left text-xs">
                <thead class="bg-slate-100 border-b border-slate-200 text-[10px] font-black text-slate-600 uppercase">
                  <tr>
                    <th class="py-3 px-4">PO / Bill #</th>
                    <th class="py-3 px-4">Order Date</th>
                    <th class="py-3 px-4">Received Date</th>
                    <th class="py-3 px-4 text-center">Items (Qty)</th>
                    <th class="py-3 px-4 text-right">Grand Total</th>
                    <th class="py-3 px-4 text-right">Paid Amount</th>
                    <th class="py-3 px-4 text-right">Due Amount</th>
                    <th class="py-3 px-4 text-center">Status</th>
                    <th class="py-3 px-4 text-center">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                  <tr v-for="po in purchaseHistory" :key="po.id" class="hover:bg-slate-50 transition-colors">
                    <td class="py-3 px-4">
                      <button @click="openPoModal(po)" class="font-mono font-black text-red-600 hover:underline text-xs">
                        {{ po.po_number }}
                      </button>
                      <div v-if="po.supplier_invoice_number" class="text-[10px] text-slate-400 font-mono">
                        Ref: {{ po.supplier_invoice_number }}
                      </div>
                    </td>

                    <td class="py-3 px-4 font-mono text-[11px] text-slate-600">
                      {{ formatDate(po.order_date) }}
                    </td>

                    <td class="py-3 px-4 font-mono text-[11px] text-slate-600">
                      {{ po.received_date ? formatDate(po.received_date) : 'Pending' }}
                    </td>

                    <td class="py-3 px-4 text-center">
                      <span class="px-2 py-0.5 bg-slate-100 border border-slate-200 rounded font-mono font-bold text-slate-800 text-[11px]">
                        {{ po.items_count || po.items?.length || 0 }} ({{ po.total_quantity_ordered || po.total_quantity_received || 0 }} pcs)
                      </span>
                    </td>

                    <td class="py-3 px-4 text-right font-mono font-black text-slate-900 text-sm">
                      ₹{{ formatCurrency(po.grand_total) }}
                    </td>

                    <td class="py-3 px-4 text-right font-mono text-emerald-700 font-bold">
                      ₹{{ formatCurrency(po.paid_amount) }}
                    </td>

                    <td class="py-3 px-4 text-right font-mono text-red-600 font-bold">
                      ₹{{ formatCurrency(po.due_amount) }}
                    </td>

                    <td class="py-3 px-4 text-center">
                      <span
                        :class="[
                          'px-2 py-0.5 rounded text-[9px] font-black uppercase border',
                          po.status === 'received' || po.status === 'completed' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-amber-50 text-amber-800 border-amber-200'
                        ]"
                      >
                        {{ po.status }}
                      </span>
                    </td>

                    <td class="py-3 px-4 text-center">
                      <button
                        @click="openPoModal(po)"
                        class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg text-[11px] font-bold transition-colors"
                      >
                        👁️ Details
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- TAB 3: PAYMENTS -->
          <div v-else-if="activeTab === 'payments'" class="space-y-4">
            <div class="flex items-center justify-between">
              <h3 class="font-black text-sm text-slate-900 uppercase tracking-tight">Recorded Supplier Payments</h3>
              <button
                @click="showPaymentModal = true"
                class="px-3.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold text-xs transition-colors flex items-center gap-1"
              >
                <span>➕</span>
                <span>Record Payment</span>
              </button>
            </div>

            <div v-if="loadingPayments" class="py-12 text-center text-xs font-bold text-slate-400">
              Loading payment history...
            </div>

            <div v-else-if="paymentsList.length === 0" class="py-12 text-center text-xs text-slate-500 font-medium bg-slate-50 rounded-2xl border border-slate-200">
              No payments recorded for this supplier yet.
            </div>

            <div v-else class="overflow-x-auto rounded-2xl border border-slate-200 text-xs">
              <table class="w-full text-left">
                <thead class="bg-slate-100 border-b border-slate-200 text-[10px] font-black text-slate-600 uppercase">
                  <tr>
                    <th class="py-3 px-4">Payment #</th>
                    <th class="py-3 px-4">Date</th>
                    <th class="py-3 px-4 text-right">Amount (₹)</th>
                    <th class="py-3 px-4 text-center">Method</th>
                    <th class="py-3 px-4">Reference Ref</th>
                    <th class="py-3 px-4">PO Ref</th>
                    <th class="py-3 px-4">Staff</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                  <tr v-for="pay in paymentsList" :key="pay.id" class="hover:bg-slate-50 transition-colors">
                    <td class="py-3 px-4 font-mono font-bold text-red-600">{{ pay.payment_number }}</td>
                    <td class="py-3 px-4 font-mono text-[11px] text-slate-600">{{ formatDate(pay.payment_date) }}</td>
                    <td class="py-3 px-4 text-right font-mono font-black text-emerald-700 text-sm">₹{{ formatCurrency(pay.amount) }}</td>
                    <td class="py-3 px-4 text-center">
                      <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-slate-100 border border-slate-200 text-slate-800">
                        {{ pay.payment_method }}
                      </span>
                    </td>
                    <td class="py-3 px-4 font-mono text-slate-500 text-[11px]">{{ pay.transaction_reference || '—' }}</td>
                    <td class="py-3 px-4 font-mono text-slate-700 font-bold text-[11px]">{{ pay.po_number || 'General Payment' }}</td>
                    <td class="py-3 px-4 text-slate-600">{{ pay.recorded_by_name || 'Staff' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- TAB 4: RETURNS -->
          <div v-else-if="activeTab === 'returns'" class="space-y-4">
            <h3 class="font-black text-sm text-slate-900 uppercase tracking-tight">Purchase Return Notes</h3>

            <div v-if="returnsList.length === 0" class="py-12 text-center text-xs text-slate-500 font-medium bg-slate-50 rounded-2xl border border-slate-200">
              No purchase returns recorded for this supplier.
            </div>

            <div v-else class="overflow-x-auto rounded-2xl border border-slate-200 text-xs">
              <table class="w-full text-left">
                <thead class="bg-slate-100 border-b border-slate-200 text-[10px] font-black text-slate-600 uppercase">
                  <tr>
                    <th class="py-3 px-4">Return #</th>
                    <th class="py-3 px-4">Date</th>
                    <th class="py-3 px-4">Original PO Ref</th>
                    <th class="py-3 px-4 text-right">Return Amount</th>
                    <th class="py-3 px-4">Reason</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                  <tr v-for="ret in returnsList" :key="ret.id" class="hover:bg-slate-50 transition-colors">
                    <td class="py-3 px-4 font-mono font-bold text-amber-700">{{ ret.return_number || ('RET-' + ret.id) }}</td>
                    <td class="py-3 px-4 font-mono text-[11px] text-slate-600">{{ formatDate(ret.created_at) }}</td>
                    <td class="py-3 px-4 font-mono text-slate-700 font-bold">{{ ret.po_number || 'PO Ref' }}</td>
                    <td class="py-3 px-4 text-right font-mono font-black text-amber-700 text-sm">₹{{ formatCurrency(ret.total_refund_amount || ret.total_return_amount) }}</td>
                    <td class="py-3 px-4 text-slate-600">{{ ret.reason || 'Vendor Return' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- TAB 5: FINANCIAL LEDGER -->
          <div v-else-if="activeTab === 'ledger'" class="space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="font-black text-sm text-slate-900 uppercase tracking-tight">Supplier Financial Statement / Ledger</h3>
                <p class="text-[11px] text-slate-500 font-medium">Chronological record of opening balances, purchase bills (debit) & payments/returns (credit).</p>
              </div>
              <div class="px-3 py-1 bg-slate-100 rounded-lg text-slate-800 font-mono font-bold text-xs border border-slate-200">
                Closing Payable: ₹{{ formatCurrency(ledgerData.closing_balance) }}
              </div>
            </div>

            <div v-if="loadingLedger" class="py-12 text-center text-xs font-bold text-slate-400">
              Generating financial ledger...
            </div>

            <div v-else-if="!ledgerData.entries || ledgerData.entries.length === 0" class="py-12 text-center text-xs text-slate-500 font-medium bg-slate-50 rounded-2xl border border-slate-200">
              No financial transactions found to generate ledger.
            </div>

            <div v-else class="overflow-x-auto rounded-2xl border border-slate-200 text-xs">
              <table class="w-full text-left">
                <thead class="bg-slate-100 border-b border-slate-200 text-[10px] font-black text-slate-600 uppercase">
                  <tr>
                    <th class="py-3 px-4">Date</th>
                    <th class="py-3 px-4">Particulars / Document</th>
                    <th class="py-3 px-4">Reference #</th>
                    <th class="py-3 px-4 text-right">Debit (Payable +)</th>
                    <th class="py-3 px-4 text-right">Credit (Paid -)</th>
                    <th class="py-3 px-4 text-right">Running Balance (₹)</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                  <tr v-for="row in ledgerData.entries" :key="row.id" class="hover:bg-slate-50 transition-colors">
                    <td class="py-3 px-4 font-mono text-[11px] text-slate-600">{{ formatDate(row.date) }}</td>
                    <td class="py-3 px-4 font-bold text-slate-900">{{ row.particulars }}</td>
                    <td class="py-3 px-4 font-mono text-slate-500 text-[11px]">{{ row.reference_number || '—' }}</td>
                    <td class="py-3 px-4 text-right font-mono font-bold text-red-600">
                      {{ row.debit > 0 ? ('₹' + formatCurrency(row.debit)) : '—' }}
                    </td>
                    <td class="py-3 px-4 text-right font-mono font-bold text-emerald-700">
                      {{ row.credit > 0 ? ('₹' + formatCurrency(row.credit)) : '—' }}
                    </td>
                    <td class="py-3 px-4 text-right font-mono font-black text-slate-900 text-sm">
                      ₹{{ formatCurrency(row.balance) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MAKE PAYMENT MODAL -->
    <div v-if="showPaymentModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-md w-full p-6 space-y-5 text-xs">
        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
          <div class="flex items-center gap-2">
            <span class="text-base">💳</span>
            <h3 class="font-black text-sm text-slate-900">Record Supplier Payment</h3>
          </div>
          <button @click="showPaymentModal = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-800">✕</button>
        </div>

        <form @submit.prevent="submitPayment" class="space-y-4">
          <div class="space-y-1">
            <label class="font-black text-slate-800 block">Payment Amount (₹) <span class="text-red-600">*</span></label>
            <input
              v-model.number="paymentForm.amount"
              type="number"
              step="0.01"
              required
              placeholder="e.g. 10000"
              class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 font-mono font-black text-sm text-slate-900 focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <div class="space-y-1">
            <label class="font-black text-slate-800 block">Payment Date <span class="text-red-600">*</span></label>
            <input
              v-model="paymentForm.payment_date"
              type="date"
              required
              class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 font-bold text-slate-900 focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <div class="space-y-1">
            <label class="font-black text-slate-800 block">Payment Method <span class="text-red-600">*</span></label>
            <select
              v-model="paymentForm.payment_method"
              required
              class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 font-bold text-slate-900 focus:ring-2 focus:ring-red-600 focus:bg-white"
            >
              <option value="cash">Cash</option>
              <option value="upi">UPI / GPay / PhonePe</option>
              <option value="bank_transfer">Bank Transfer (NEFT/RTGS)</option>
              <option value="card">Credit / Debit Card</option>
              <option value="other">Other</option>
            </select>
          </div>

          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">Against Purchase Order (Optional)</label>
            <select
              v-model="paymentForm.purchase_order_id"
              class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 font-bold text-slate-900 focus:ring-2 focus:ring-red-600 focus:bg-white"
            >
              <option :value="null">-- General Payment / Opening Balance --</option>
              <option v-for="po in purchaseHistory" :key="po.id" :value="po.id">
                {{ po.po_number }} (Due: ₹{{ formatCurrency(po.due_amount) }})
              </option>
            </select>
          </div>

          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">Transaction Reference #</label>
            <input
              v-model="paymentForm.transaction_reference"
              type="text"
              placeholder="e.g. UTR / UTR12345678"
              class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 font-mono text-slate-900 focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">Notes / Remarks</label>
            <input
              v-model="paymentForm.notes"
              type="text"
              placeholder="e.g. Part payment against footwear consignment..."
              class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2 text-slate-900 focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2">
            <button
              type="button"
              @click="showPaymentModal = false"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="savingPayment"
              class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black shadow-md shadow-red-600/20"
            >
              {{ savingPayment ? 'Recording...' : 'Save Payment' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- PURCHASE BILL DETAILS MODAL -->
    <div v-if="selectedPo" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
      <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-4xl w-full p-6 sm:p-8 space-y-6 max-h-[90vh] overflow-y-auto text-xs">
        <div class="flex items-center justify-between border-b border-slate-200 pb-4">
          <div>
            <h3 class="text-lg font-black text-slate-900">Purchase Bill Details: {{ selectedPo.po_number }}</h3>
            <p class="text-xs text-slate-500 font-medium mt-0.5">
              Issued on {{ formatDate(selectedPo.order_date) }} • Main Outlet (STR-001)
            </p>
          </div>
          <button @click="selectedPo = null" class="p-2 rounded-xl text-slate-400 hover:text-slate-800 font-black text-lg">✕</button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200/80">
          <div class="space-y-1">
            <span class="font-bold text-slate-400 uppercase text-[10px] tracking-wider block">Vendor Information</span>
            <div class="font-black text-slate-900 text-sm">{{ supplier.company_name || supplier.name }}</div>
            <div class="font-mono text-slate-600 font-bold">GSTIN: {{ supplier.gstin || 'N/A' }}</div>
          </div>

          <div class="space-y-1 text-left sm:text-right">
            <span class="font-bold text-slate-400 uppercase text-[10px] tracking-wider block">Order Meta</span>
            <div class="font-bold text-slate-900">Status: {{ selectedPo.status }}</div>
            <div class="font-mono text-slate-500">Invoice Ref: {{ selectedPo.supplier_invoice_number || 'N/A' }}</div>
          </div>
        </div>

        <!-- Itemized Table -->
        <div class="space-y-2">
          <h4 class="font-black text-slate-900 uppercase tracking-wider text-[11px]">Itemized Footwear Stock Received</h4>
          <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-100 border-b border-slate-200 text-[10px] font-black text-slate-600 uppercase">
                <tr>
                  <th class="py-2.5 px-3">Article #</th>
                  <th class="py-2.5 px-3">Product Name</th>
                  <th class="py-2.5 px-3">Color</th>
                  <th class="py-2.5 px-3">Size (IND)</th>
                  <th class="py-2.5 px-3 text-center">Ordered</th>
                  <th class="py-2.5 px-3 text-center">Received</th>
                  <th class="py-2.5 px-3 text-right">Cost Rate (₹)</th>
                  <th class="py-2.5 px-3 text-right">MRP (₹)</th>
                  <th class="py-2.5 px-3 text-right">Total Cost (₹)</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                <tr v-for="item in (selectedPo.items || [])" :key="item.id">
                  <td class="py-2.5 px-3 font-mono font-bold text-red-600">{{ item.article_number || item.sku || 'N/A' }}</td>
                  <td class="py-2.5 px-3 font-bold text-slate-900">{{ item.product_name || item.name || 'Footwear Item' }}</td>
                  <td class="py-2.5 px-3">{{ item.color_name || 'Standard' }}</td>
                  <td class="py-2.5 px-3 font-bold">
                    <span class="px-1.5 py-0.5 bg-slate-100 border border-slate-200 rounded text-[10px]">
                      IND {{ item.size_number || item.size || 'N/A' }}
                    </span>
                  </td>
                  <td class="py-2.5 px-3 text-center font-bold font-mono">{{ item.quantity_ordered }}</td>
                  <td class="py-2.5 px-3 text-center font-bold font-mono text-emerald-700">{{ item.quantity_received }}</td>
                  <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900">₹{{ formatCurrency(item.cost_price) }}</td>
                  <td class="py-2.5 px-3 text-right font-mono text-slate-500">₹{{ formatCurrency(item.mrp) }}</td>
                  <td class="py-2.5 px-3 text-right font-mono font-black text-slate-900">₹{{ formatCurrency(item.total_cost || item.subtotal) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="flex justify-end pt-4 border-t border-slate-200">
          <button @click="selectedPo = null" class="px-5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useAuth } from '../../composables/useAuth';
import api from '../../services/api';

const route = useRoute();
const { hasPermission } = useAuth();

const id = computed(() => route.params.id);
const supplier = ref({});
const summary = ref({});
const activeTab = ref('overview');

const loadingSupplier = ref(true);
const loadingPurchases = ref(false);
const loadingPayments = ref(false);
const loadingLedger = ref(false);
const supplierError = ref(null);

const purchaseHistory = ref([]);
const paymentsList = ref([]);
const returnsList = ref([]);
const ledgerData = ref({ entries: [], closing_balance: 0 });

const selectedPo = ref(null);
const showPaymentModal = ref(false);
const savingPayment = ref(false);

const paymentForm = reactive({
  amount: '',
  payment_date: new Date().toISOString().substring(0, 10),
  payment_method: 'cash',
  purchase_order_id: null,
  transaction_reference: '',
  notes: '',
});

const purchasesFilter = reactive({
  search: '',
  status: '',
  start_date: '',
  end_date: '',
  page: 1,
});

function formatDate(dateStr) {
  if (!dateStr) return 'N/A';
  try {
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-IN', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  } catch (e) {
    return dateStr;
  }
}

function formatCurrency(val) {
  return Number(val || 0).toLocaleString('en-IN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

async function loadSupplierProfile() {
  loadingSupplier.value = true;
  supplierError.value = null;
  try {
    const [sRes, sumRes] = await Promise.all([
      api.get(`/suppliers/${id.value}`),
      api.get(`/suppliers/${id.value}/purchase-summary`).catch(() => ({ data: {} })),
    ]);

    supplier.value = sRes.data || sRes;
    summary.value = sumRes.data || sumRes;
  } catch (err) {
    console.error('Failed to load supplier profile:', err);
    supplierError.value = err.response?.data?.message || 'Failed to load supplier profile.';
  } finally {
    loadingSupplier.value = false;
  }
}

async function fetchPurchaseHistory(page = 1) {
  loadingPurchases.value = true;
  purchasesFilter.page = page;
  try {
    const params = {
      page: purchasesFilter.page,
    };
    if (purchasesFilter.search) params.search = purchasesFilter.search;
    if (purchasesFilter.status) params.status = purchasesFilter.status;
    if (purchasesFilter.start_date) params.start_date = purchasesFilter.start_date;
    if (purchasesFilter.end_date) params.end_date = purchasesFilter.end_date;

    const res = await api.get(`/suppliers/${id.value}/purchase-history`, { params });
    const payload = res.data || res;
    purchaseHistory.value = payload.items || (Array.isArray(payload) ? payload : []);
  } catch (err) {
    console.error('Failed to fetch supplier purchases:', err);
  } finally {
    loadingPurchases.value = false;
  }
}

async function fetchPayments() {
  loadingPayments.value = true;
  try {
    const res = await api.get(`/suppliers/${id.value}/payments`);
    const payload = res.data || res;
    paymentsList.value = payload.items || (Array.isArray(payload) ? payload : []);
  } catch (err) {
    console.error('Failed to fetch supplier payments:', err);
  } finally {
    loadingPayments.value = false;
  }
}

async function fetchLedger() {
  loadingLedger.value = true;
  try {
    const res = await api.get(`/suppliers/${id.value}/ledger`);
    ledgerData.value = res.data || res || { entries: [], closing_balance: 0 };
  } catch (err) {
    console.error('Failed to fetch supplier ledger:', err);
  } finally {
    loadingLedger.value = false;
  }
}

function openPoModal(po) {
  selectedPo.value = po;
}

async function submitPayment() {
  savingPayment.value = true;
  try {
    await api.post(`/suppliers/${id.value}/payments`, paymentForm);
    showPaymentModal.value = false;
    paymentForm.amount = '';
    paymentForm.transaction_reference = '';
    paymentForm.notes = '';

    await Promise.all([
      loadSupplierProfile(),
      fetchPurchaseHistory(1),
      fetchPayments(),
      fetchLedger(),
    ]);
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to record payment.');
  } finally {
    savingPayment.value = false;
  }
}

onMounted(() => {
  loadSupplierProfile();
  fetchPurchaseHistory(1);
  fetchPayments();
  fetchLedger();
});
</script>
