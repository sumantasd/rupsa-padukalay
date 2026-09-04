<template>
  <div class="space-y-6 antialiased font-sans max-w-7xl mx-auto pb-16">
    <!-- Top Header & Navigation -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
      <div>
        <div class="flex items-center gap-2 text-xs font-bold text-slate-500 mb-1">
          <router-link to="/admin/customers" class="hover:text-red-600 transition-colors">Customers</router-link>
          <span>/</span>
          <span class="text-slate-900">Customer Purchase History</span>
        </div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
          <span>{{ customer.name || 'Registered Customer' }}</span>
          <span class="text-xs font-mono px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 font-black border border-slate-200">
            CUST-{{ String(customer.id || id).padStart(4, '0') }}
          </span>
        </h1>
      </div>

      <div class="flex items-center gap-3">
        <router-link
          to="/admin/customers"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-colors"
        >
          ← Back to Directory
        </router-link>

        <router-link
          v-if="hasPermission('customers.edit')"
          :to="'/admin/customers/' + id + '/edit'"
          class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-black text-xs rounded-xl shadow-xs transition-all flex items-center gap-1.5"
        >
          <span>✏️</span>
          <span>Edit Profile</span>
        </router-link>
      </div>
    </div>

    <!-- Skeleton Loading -->
    <div v-if="loadingCustomer" class="p-8 bg-white rounded-3xl border border-slate-200 animate-pulse space-y-4">
      <div class="h-6 bg-slate-200 rounded w-1/3"></div>
      <div class="h-4 bg-slate-200 rounded w-1/2"></div>
      <div class="h-20 bg-slate-100 rounded-2xl"></div>
    </div>

    <div v-else-if="customerError" class="p-6 bg-red-50 border border-red-200 rounded-2xl text-center space-y-2">
      <span class="text-2xl">⚠️</span>
      <p class="text-xs text-red-700 font-bold">{{ customerError }}</p>
    </div>

    <div v-else class="space-y-6">
      <!-- 1. CUSTOMER PROFILE CARD & KPI SUMMARY MATRIX -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <!-- Customer Profile Details -->
        <div class="p-6 bg-white rounded-3xl border border-slate-200/90 shadow-xs space-y-5 text-xs">
          <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
            <h2 class="font-black text-sm text-slate-900 uppercase tracking-tight flex items-center gap-2">
              <span>👤</span>
              <span>Customer Details</span>
            </h2>
            <span class="px-2 py-0.5 bg-emerald-50 text-emerald-800 border border-emerald-200 font-bold rounded text-[10px] uppercase">
              Active Member
            </span>
          </div>

          <div class="space-y-4 text-slate-700">
            <div class="flex items-start gap-3">
              <span class="text-base">📞</span>
              <div>
                <div class="font-bold text-slate-400 text-[10px] uppercase tracking-wider">Phone / Mobile</div>
                <a :href="'tel:' + (customer.mobile_number || '').replace(/\s+/g, '')" class="font-mono font-black text-slate-900 hover:text-red-600 text-sm">
                  {{ customer.mobile_number || 'N/A' }}
                </a>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <span class="text-base">✉️</span>
              <div>
                <div class="font-bold text-slate-400 text-[10px] uppercase tracking-wider">Email Address</div>
                <div class="font-bold text-slate-900">{{ customer.email || 'None registered' }}</div>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <span class="text-base">📍</span>
              <div>
                <div class="font-bold text-slate-400 text-[10px] uppercase tracking-wider">City & Location</div>
                <div class="font-bold text-slate-900">{{ customer.city || 'Dhantala, Nadia' }}</div>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <span class="text-base">🏢</span>
              <div>
                <div class="font-bold text-slate-400 text-[10px] uppercase tracking-wider">Shipping / Billing Address</div>
                <div class="font-medium text-slate-700 leading-relaxed">{{ customer.address || 'DHANTALA BAZAR, DHANTALA, NADIA - 741202' }}</div>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <span class="text-base">📅</span>
              <div>
                <div class="font-bold text-slate-400 text-[10px] uppercase tracking-wider">Registration Date</div>
                <div class="font-bold text-slate-900">{{ customer.created_at ? formatDate(customer.created_at) : 'N/A' }}</div>
              </div>
            </div>

            <div v-if="customer.notes" class="pt-2 border-t border-slate-100 space-y-1">
              <div class="font-bold text-slate-400 text-[10px] uppercase tracking-wider">Footwear Size Notes</div>
              <p class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 font-medium">
                {{ customer.notes }}
              </p>
            </div>
          </div>
        </div>

        <!-- 7 Key Financial KPI Metric Cards (2x4 Grid on LG) -->
        <div class="lg:col-span-2 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 gap-4 text-xs">
          <!-- Total Orders -->
          <div class="p-5 bg-white rounded-2xl border border-slate-200/90 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-slate-500 font-bold uppercase text-[10px]">
              <span>Total Orders</span>
              <span class="text-base">🧾</span>
            </div>
            <div class="text-2xl font-black text-slate-900 font-mono">
              {{ summary.total_orders || customer.total_purchases_count || 0 }}
            </div>
            <span class="text-[10px] text-slate-400 font-medium">Completed POS Invoices</span>
          </div>

          <!-- Total Purchase Value -->
          <div class="p-5 bg-white rounded-2xl border border-slate-200/90 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-slate-500 font-bold uppercase text-[10px]">
              <span>Total Purchase</span>
              <span class="text-base">💰</span>
            </div>
            <div class="text-xl sm:text-2xl font-black text-slate-900 font-mono">
              ₹{{ formatCurrency(summary.total_net_purchase_value || customer.total_spent_amount || 0) }}
            </div>
            <span class="text-[10px] text-slate-400 font-medium">Net Sales Value</span>
          </div>

          <!-- Items Bought -->
          <div class="p-5 bg-white rounded-2xl border border-slate-200/90 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-slate-500 font-bold uppercase text-[10px]">
              <span>Items Purchased</span>
              <span class="text-base">👟</span>
            </div>
            <div class="text-2xl font-black text-slate-900 font-mono">
              {{ summary.total_items_purchased || 0 }}
            </div>
            <span class="text-[10px] text-slate-400 font-medium">Total Pair / Units</span>
          </div>

          <!-- Total Paid -->
          <div class="p-5 bg-white rounded-2xl border border-slate-200/90 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-slate-500 font-bold uppercase text-[10px]">
              <span>Total Paid</span>
              <span class="text-base">💳</span>
            </div>
            <div class="text-xl sm:text-2xl font-black text-emerald-700 font-mono">
              ₹{{ formatCurrency(summary.total_paid_amount || 0) }}
            </div>
            <span class="text-[10px] text-slate-400 font-medium">Verified Payments</span>
          </div>

          <!-- Total Refunds -->
          <div class="p-5 bg-white rounded-2xl border border-slate-200/90 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-slate-500 font-bold uppercase text-[10px]">
              <span>Total Refunds</span>
              <span class="text-base">🔄</span>
            </div>
            <div class="text-xl sm:text-2xl font-black text-amber-700 font-mono">
              ₹{{ formatCurrency(summary.total_returned_amount || 0) }}
            </div>
            <span class="text-[10px] text-slate-400 font-medium">{{ summary.number_of_returns || 0 }} Return Sales</span>
          </div>

          <!-- Store Credit Balance -->
          <div class="p-5 bg-white rounded-2xl border border-slate-200/90 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-slate-500 font-bold uppercase text-[10px]">
              <span>Store Credit Balance</span>
              <span class="text-base">🏦</span>
            </div>
            <div class="text-xl sm:text-2xl font-black text-purple-700 font-mono">
              ₹{{ formatCurrency(storeCreditBalance) }}
            </div>
            <span class="text-[10px] text-slate-400 font-medium">Available Return Ledger</span>
          </div>

          <!-- Loyalty Points -->
          <div class="p-5 bg-white rounded-2xl border border-slate-200/90 shadow-xs space-y-1">
            <div class="flex items-center justify-between text-slate-500 font-bold uppercase text-[10px]">
              <span>Loyalty Points</span>
              <span class="text-base">⭐</span>
            </div>
            <div class="text-2xl font-black text-purple-600 font-mono">
              {{ customer.reward_points || 0 }} pts
            </div>
            <span class="text-[10px] text-slate-400 font-medium">Accrued Customer Points</span>
          </div>

          <!-- Last Purchase Date -->
          <div class="p-5 bg-white rounded-2xl border border-slate-200/90 shadow-xs space-y-1 col-span-2 sm:col-span-1">
            <div class="flex items-center justify-between text-slate-500 font-bold uppercase text-[10px]">
              <span>Last Purchase Date</span>
              <span class="text-base">🕒</span>
            </div>
            <div class="text-sm font-black text-slate-900 font-mono">
              {{ summary.last_purchase_date ? formatDate(summary.last_purchase_date) : 'No purchases' }}
            </div>
            <span class="text-[10px] text-slate-400 font-medium">Most Recent Sale</span>
          </div>
        </div>
      </div>

      <!-- 2. PURCHASE HISTORY SECTION & SERVER-SIDE FILTER BAR -->
      <div class="bg-white rounded-3xl border border-slate-200/90 shadow-xs overflow-hidden p-6 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
          <div>
            <h2 class="font-black text-base text-slate-900 tracking-tight flex items-center gap-2">
              <span>🧾</span>
              <span>Complete Purchase History</span>
            </h2>
            <p class="text-xs text-slate-500 font-medium mt-0.5">
              Itemized list of all completed sales, invoices, tender payments & sales returns for this customer.
            </p>
          </div>

          <div class="flex items-center gap-2 text-xs font-bold text-slate-500">
            <span class="px-2.5 py-1 bg-slate-100 border border-slate-200 rounded-lg text-slate-800 font-mono font-bold">
              Total {{ pagination.total || historyItems.length }} Invoice(s)
            </span>
          </div>
        </div>

        <!-- Filters Bar -->
        <div class="bg-slate-50 border border-slate-200/90 rounded-2xl p-4 space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 text-xs">
            <!-- Search Input -->
            <div class="space-y-1 sm:col-span-2 lg:col-span-1">
              <label class="font-bold text-slate-700 block">Search Invoice / Item / SKU</label>
              <div class="relative">
                <input
                  v-model="filters.search"
                  @input="debouncedFetch"
                  type="text"
                  placeholder="e.g. INV-000123 or SKU..."
                  class="w-full bg-white border border-slate-200 rounded-xl pl-8 pr-3 py-2 text-xs text-slate-900 font-bold focus:ring-2 focus:ring-red-600 focus:outline-none"
                />
                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
              </div>
            </div>

            <!-- Date From -->
            <div class="space-y-1">
              <label class="font-bold text-slate-700 block">Date From</label>
              <input
                v-model="filters.start_date"
                @change="fetchPurchaseHistory(1)"
                type="date"
                class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 font-bold focus:ring-2 focus:ring-red-600 focus:outline-none"
              />
            </div>

            <!-- Date To -->
            <div class="space-y-1">
              <label class="font-bold text-slate-700 block">Date To</label>
              <input
                v-model="filters.end_date"
                @change="fetchPurchaseHistory(1)"
                type="date"
                class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 font-bold focus:ring-2 focus:ring-red-600 focus:outline-none"
              />
            </div>

            <!-- Payment Method Filter -->
            <div class="space-y-1">
              <label class="font-bold text-slate-700 block">Payment Method</label>
              <select
                v-model="filters.payment_method"
                @change="fetchPurchaseHistory(1)"
                class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 font-bold focus:ring-2 focus:ring-red-600 focus:outline-none"
              >
                <option value="">All Methods</option>
                <option value="cash">Cash</option>
                <option value="upi">UPI / GPay / PhonePe</option>
                <option value="card">Credit / Debit Card</option>
                <option value="store_credit">Store Credit</option>
                <option value="other">Other</option>
              </select>
            </div>

            <!-- Invoice Status Filter -->
            <div class="space-y-1">
              <label class="font-bold text-slate-700 block">Invoice Status</label>
              <select
                v-model="filters.status"
                @change="fetchPurchaseHistory(1)"
                class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 font-bold focus:ring-2 focus:ring-red-600 focus:outline-none"
              >
                <option value="">All Statuses</option>
                <option value="paid">Paid</option>
                <option value="partially_paid">Partially Paid</option>
                <option value="unpaid">Unpaid</option>
                <option value="completed">Completed</option>
                <option value="refunded">Refunded / Returned</option>
              </select>
            </div>
          </div>

          <div v-if="hasActiveFilters" class="flex items-center justify-end gap-2 pt-1">
            <button
              type="button"
              @click="clearFilters"
              class="px-3 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg text-xs font-bold transition-colors flex items-center gap-1"
            >
              <span>✕</span>
              <span>Clear Filters</span>
            </button>
          </div>
        </div>

        <!-- History Loading Indicator -->
        <div v-if="loadingHistory" class="py-12 text-center text-slate-400 font-bold text-xs">
          Loading customer invoices from API...
        </div>

        <!-- Empty History State -->
        <div v-else-if="historyItems.length === 0" class="py-12 text-center text-xs text-slate-500 font-medium space-y-2 bg-slate-50 rounded-2xl border border-slate-200/80">
          <div class="text-3xl">🧾</div>
          <p class="font-black text-slate-800">No matching purchase invoices found</p>
          <p class="text-[11px] text-slate-400">Try adjusting your date range or search filter.</p>
        </div>

        <!-- Paginated Invoices Table (Mobile Scrollable Box) -->
        <div v-else class="overflow-x-auto rounded-2xl border border-slate-200">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-100 text-slate-600 font-black uppercase text-[10px] tracking-wider border-b border-slate-200">
              <tr>
                <th class="py-3 px-4">Invoice #</th>
                <th class="py-3 px-4">Date</th>
                <th class="py-3 px-4">Store Outlet</th>
                <th class="py-3 px-4 text-center">Items (Qty)</th>
                <th class="py-3 px-4 text-right">Subtotal</th>
                <th class="py-3 px-4 text-right">Discount</th>
                <th class="py-3 px-4 text-right">GST</th>
                <th class="py-3 px-4 text-right">Grand Total</th>
                <th class="py-3 px-4 text-center">Payment</th>
                <th class="py-3 px-4 text-center">Status</th>
                <th class="py-3 px-4 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
              <tr v-for="inv in historyItems" :key="inv.id" class="hover:bg-slate-50/90 transition-colors">
                <!-- Invoice # -->
                <td class="py-3 px-4">
                  <button
                    type="button"
                    @click="openInvoiceModal(inv)"
                    class="font-mono font-black text-red-600 hover:underline text-xs"
                  >
                    {{ inv.invoice_number }}
                  </button>
                  <div v-if="inv.items && inv.items.length > 0" class="text-[10px] text-slate-500 font-sans font-medium mt-0.5 truncate max-w-[220px]">
                    <span v-for="(it, i) in inv.items.slice(0, 2)" :key="it.id">
                      {{ it.product_name_snapshot }} (IND {{ it.size_number_snapshot }}){{ i < Math.min(inv.items.length, 2) - 1 ? ', ' : '' }}
                    </span>
                    <span v-if="inv.items.length > 2" class="text-slate-400"> +{{ inv.items.length - 2 }} more</span>
                  </div>
                </td>


                <!-- Date -->
                <td class="py-3 px-4 text-slate-600 font-mono text-[11px]">
                  {{ formatDate(inv.created_at) }}
                </td>

                <!-- Store -->
                <td class="py-3 px-4 font-bold text-slate-700 text-[11px]">
                  {{ inv.store_name || 'RUPSA PADUKALAYA - Main Outlet' }}
                </td>

                <!-- Items Count & Qty -->
                <td class="py-3 px-4 text-center">
                  <span class="px-2 py-0.5 bg-slate-100 border border-slate-200 rounded font-mono font-bold text-slate-800 text-[11px]">
                    {{ inv.items_count || inv.items?.length || 0 }} ({{ inv.total_items_quantity || 0 }} pcs)
                  </span>
                </td>

                <!-- Subtotal -->
                <td class="py-3 px-4 text-right font-mono text-slate-600">
                  ₹{{ formatCurrency(inv.subtotal) }}
                </td>

                <!-- Discount -->
                <td class="py-3 px-4 text-right font-mono text-emerald-600">
                  -₹{{ formatCurrency(inv.discount_amount) }}
                </td>

                <!-- GST -->
                <td class="py-3 px-4 text-right font-mono text-slate-600">
                  ₹{{ formatCurrency(inv.total_tax) }}
                </td>

                <!-- Grand Total -->
                <td class="py-3 px-4 text-right font-mono font-black text-slate-900 text-sm">
                  ₹{{ formatCurrency(inv.grand_total) }}
                </td>

                <!-- Payment Method Badges -->
                <td class="py-3 px-4 text-center">
                  <div class="flex flex-wrap items-center justify-center gap-1">
                    <span
                      v-for="pm in getPaymentMethodBadges(inv)"
                      :key="pm"
                      class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-slate-100 text-slate-800 border border-slate-200"
                    >
                      {{ pm }}
                    </span>
                  </div>
                </td>

                <!-- Status Badges -->
                <td class="py-3 px-4 text-center">
                  <div class="flex flex-col items-center gap-1">
                    <span
                      :class="[
                        'px-2 py-0.5 rounded text-[9px] font-extrabold uppercase border',
                        inv.payment_status === 'paid' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-amber-50 text-amber-800 border-amber-200'
                      ]"
                    >
                      {{ inv.payment_status || 'Paid' }}
                    </span>
                    <span v-if="inv.returns && inv.returns.length > 0" class="px-2 py-0.5 bg-amber-100 text-amber-900 text-[9px] font-black rounded uppercase">
                      Returned
                    </span>
                  </div>
                </td>

                <!-- Actions -->
                <td class="py-3 px-4 text-center">
                  <div class="flex items-center justify-center gap-1.5">
                    <button
                      type="button"
                      @click="openInvoiceModal(inv)"
                      class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg text-[11px] font-bold transition-colors"
                      title="View Full Invoice Details"
                    >
                      👁️ Details
                    </button>

                    <button
                      type="button"
                      @click="openThermalReceiptModal(inv)"
                      class="px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 rounded-lg text-[11px] font-black transition-colors"
                      title="View POS Thermal Bill"
                    >
                      🧾 Bill
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- 3. SERVER-SIDE PAGINATION CONTROLS -->
        <div v-if="pagination.total > 0" class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-2 border-t border-slate-100 text-xs">
          <div class="flex items-center gap-3">
            <span class="text-slate-500 font-bold">Rows per page:</span>
            <select
              v-model="filters.per_page"
              @change="fetchPurchaseHistory(1)"
              class="bg-slate-100 border border-slate-200 rounded-lg px-2.5 py-1 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-red-600 focus:outline-none"
            >
              <option :value="10">10 per page</option>
              <option :value="20">20 per page</option>
              <option :value="50">50 per page</option>
            </select>

            <span class="text-slate-400 font-medium">
              Showing page <strong class="text-slate-900">{{ pagination.current_page }}</strong> of <strong class="text-slate-900">{{ pagination.last_page }}</strong>
            </span>
          </div>

          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="fetchPurchaseHistory(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1 || loadingHistory"
              class="px-3 py-1.5 rounded-xl border border-slate-200 font-bold text-xs bg-white hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
              ← Previous
            </button>
            <span class="px-3 py-1.5 font-mono font-bold bg-slate-100 rounded-xl border border-slate-200 text-slate-900">
              {{ pagination.current_page }} / {{ pagination.last_page }}
            </span>
            <button
              type="button"
              @click="fetchPurchaseHistory(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page || loadingHistory"
              class="px-3 py-1.5 rounded-xl border border-slate-200 font-bold text-xs bg-white hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
              Next →
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ================================================== -->
    <!-- 4. INVOICE ITEM DETAILS MODAL -->
    <!-- ================================================== -->
    <div v-if="selectedInvoice" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
      <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-4xl w-full p-6 sm:p-8 space-y-6 max-h-[90vh] overflow-y-auto">
        <!-- Modal Header -->
        <div class="flex items-center justify-between border-b border-slate-200 pb-4">
          <div>
            <div class="flex items-center gap-2">
              <span class="text-base">🧾</span>
              <h3 class="text-lg font-black text-slate-900">Invoice Details: {{ selectedInvoice.invoice_number }}</h3>
            </div>
            <p class="text-xs text-slate-500 font-medium mt-0.5">
              Issued on {{ formatDate(selectedInvoice.created_at) }} • {{ selectedInvoice.store_name || 'RUPSA PADUKALAYA - Main Outlet' }}
            </p>
          </div>
          <button
            type="button"
            @click="selectedInvoice = null"
            class="p-2 rounded-xl text-slate-400 hover:text-slate-800 hover:bg-slate-100 font-black text-lg transition-colors"
          >
            ✕
          </button>
        </div>

        <!-- Customer & Outlet Meta Row -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200/80 text-xs">
          <div class="space-y-1">
            <span class="font-bold text-slate-400 uppercase text-[10px] tracking-wider block">Billed Customer</span>
            <div class="font-black text-slate-900 text-sm">{{ customer.name || 'Walk-in Customer' }}</div>
            <div class="font-mono text-slate-600 font-bold">Phone: {{ customer.mobile_number || 'N/A' }}</div>
          </div>

          <div class="space-y-1 text-left sm:text-right">
            <span class="font-bold text-slate-400 uppercase text-[10px] tracking-wider block">Billing Context</span>
            <div class="font-bold text-slate-900">Cashier: {{ selectedInvoice.created_by_name || 'POS Counter Staff' }}</div>
            <div class="font-mono text-slate-500">Store Code: STR-001</div>
          </div>
        </div>

        <!-- Itemized Products Table -->
        <div class="space-y-3">
          <h4 class="font-black text-xs text-slate-900 uppercase tracking-wider">Itemized Purchased Footwear Products</h4>
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
                  <th class="py-2.5 px-3 text-right">Discount (₹)</th>
                  <th class="py-2.5 px-3 text-right">GST</th>
                  <th class="py-2.5 px-3 text-right">Line Total (₹)</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                <tr v-for="item in (selectedInvoice.items || [])" :key="item.id">
                  <td class="py-2.5 px-3 font-mono font-bold text-red-600">{{ item.article_number_snapshot || 'N/A' }}</td>
                  <td class="py-2.5 px-3 font-bold text-slate-900">{{ item.product_name_snapshot }}</td>
                  <td class="py-2.5 px-3">{{ item.color_name_snapshot || 'Standard' }}</td>
                  <td class="py-2.5 px-3 font-bold">
                    <span class="px-1.5 py-0.5 bg-slate-100 border border-slate-200 rounded text-[10px]">
                      IND {{ item.size_number_snapshot }}
                    </span>
                  </td>

                  <td class="py-2.5 px-3 text-center font-bold font-mono">{{ item.quantity }}</td>
                  <td class="py-2.5 px-3 text-right font-mono text-slate-500">₹{{ formatCurrency(item.mrp) }}</td>
                  <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900">₹{{ formatCurrency(item.unit_price) }}</td>
                  <td class="py-2.5 px-3 text-right font-mono text-emerald-600">-₹{{ formatCurrency(item.discount_amount) }}</td>
                  <td class="py-2.5 px-3 text-right font-mono text-slate-500">{{ item.tax_rate_percentage || 0 }}%</td>
                  <td class="py-2.5 px-3 text-right font-mono font-black text-slate-900">₹{{ formatCurrency(item.subtotal) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Bill Financial Summary -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-2">
          <!-- Tender Payments Info -->
          <div class="space-y-3 bg-slate-50 p-4 rounded-2xl border border-slate-200/80 text-xs">
            <h5 class="font-black text-slate-900 uppercase text-[10px] tracking-wider">Payment Tender Method(s)</h5>
            <div v-for="p in (selectedInvoice.payments || [])" :key="p.id" class="flex items-center justify-between border-b border-slate-200/60 pb-2 last:border-0 last:pb-0">
              <span class="font-bold uppercase text-slate-700">{{ p.payment_method }}</span>
              <span class="font-mono font-black text-slate-900">₹{{ formatCurrency(p.amount) }}</span>
            </div>

            <div v-if="selectedInvoice.returns && selectedInvoice.returns.length > 0" class="pt-2 border-t border-slate-200">
              <div class="font-bold text-amber-800 text-[10px] uppercase">Sales Return Log</div>
              <div v-for="ret in selectedInvoice.returns" :key="ret.id" class="text-[11px] text-amber-900 font-medium">
                Refunded ₹{{ formatCurrency(ret.total_refund_amount) }} on {{ formatDate(ret.created_at) }}
              </div>
            </div>
          </div>

          <!-- Total Calculation Breakdown -->
          <div class="space-y-2 text-xs font-medium text-slate-700 bg-slate-50 p-4 rounded-2xl border border-slate-200/80">
            <div class="flex items-center justify-between">
              <span>Subtotal Gross</span>
              <span class="font-mono font-bold text-slate-900">₹{{ formatCurrency(selectedInvoice.subtotal) }}</span>
            </div>
            <div class="flex items-center justify-between text-emerald-600 font-bold">
              <span>Bill Discount</span>
              <span class="font-mono">-₹{{ formatCurrency(selectedInvoice.discount_amount) }}</span>
            </div>
            <div v-if="selectedInvoice.is_gst_enabled" class="space-y-1 pt-1 border-t border-slate-200 text-[11px]">
              <div class="flex items-center justify-between text-slate-500">
                <span>Taxable Amount</span>
                <span class="font-mono">₹{{ formatCurrency(selectedInvoice.taxable_amount) }}</span>
              </div>
              <div class="flex items-center justify-between text-slate-500">
                <span>CGST</span>
                <span class="font-mono">₹{{ formatCurrency(selectedInvoice.total_cgst) }}</span>
              </div>
              <div class="flex items-center justify-between text-slate-500">
                <span>SGST</span>
                <span class="font-mono">₹{{ formatCurrency(selectedInvoice.total_sgst) }}</span>
              </div>
            </div>
            <div class="flex items-center justify-between font-black text-slate-900 text-sm pt-2 border-t border-slate-300">
              <span>Grand Total</span>
              <span class="font-mono text-red-600">₹{{ formatCurrency(selectedInvoice.grand_total) }}</span>
            </div>
          </div>
        </div>

        <!-- Modal Actions Footer -->
        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
          <button
            type="button"
            @click="selectedInvoice = null"
            class="px-5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs transition-colors"
          >
            Close
          </button>
          <button
            type="button"
            @click="openThermalReceiptModal(selectedInvoice)"
            class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs transition-all shadow-md shadow-red-600/20 flex items-center gap-1.5"
          >
            <span>🧾</span>
            <span>View & Print Thermal Bill</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ================================================== -->
    <!-- 5. POS THERMAL RECEIPT VIEW & PRINT MODAL -->
    <!-- ================================================== -->
    <div v-if="receiptInvoice" class="fixed inset-0 z-50 bg-slate-900/70 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
      <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-md w-full p-6 space-y-6">
        <div class="flex items-center justify-between border-b border-slate-200 pb-3 no-print">
          <h3 class="font-black text-sm text-slate-900 flex items-center gap-2">
            <span>🧾</span>
            <span>POS Thermal Receipt</span>
          </h3>
          <button
            type="button"
            @click="receiptInvoice = null"
            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-800 font-bold transition-colors"
          >
            ✕
          </button>
        </div>

        <!-- Thermal Receipt Container (80mm Width format) -->
        <div id="receipt-print-container" class="bg-white p-4 font-mono text-xs text-slate-900 border border-slate-200 shadow-inner rounded-xl space-y-3 leading-tight">
          <!-- Outlet Header -->
          <div class="text-center space-y-1 border-b border-dashed border-slate-400 pb-3">
            <h2 class="font-black text-base uppercase tracking-tight text-slate-900">RUPSA PADUKALAYA</h2>
            <p class="text-[10px] font-bold text-slate-700 uppercase">Main Outlet</p>
            <p class="text-[9px] text-slate-600 leading-tight">
              DHANTALA BAZAR, DHANTALA, NADIA - 741202<br />
              WEST BENGAL, INDIA
            </p>
            <p class="text-[10px] font-bold text-slate-800">Phone: +91 9735125112</p>
            <p class="text-[9px] font-bold text-slate-600">GSTIN: 19ABCDE1234F1Z5</p>
          </div>

          <!-- Bill Meta -->
          <div class="text-[10px] space-y-0.5 border-b border-dashed border-slate-400 pb-2">
            <div class="flex justify-between font-bold">
              <span>Invoice #:</span>
              <span>{{ receiptInvoice.invoice_number }}</span>
            </div>
            <div class="flex justify-between">
              <span>Date:</span>
              <span>{{ formatDate(receiptInvoice.created_at) }}</span>
            </div>
            <div class="flex justify-between">
              <span>Customer:</span>
              <span>{{ customer.name || 'Walk-in Customer' }}</span>
            </div>
            <div v-if="customer.mobile_number" class="flex justify-between">
              <span>Phone:</span>
              <span>{{ customer.mobile_number }}</span>
            </div>
          </div>

          <!-- Items Table -->
          <div class="space-y-1 border-b border-dashed border-slate-400 pb-3 text-[10px]">
            <div class="grid grid-cols-12 font-black border-b border-slate-300 pb-1">
              <span class="col-span-6">Item</span>
              <span class="col-span-2 text-center">Qty</span>
              <span class="col-span-4 text-right">Total</span>
            </div>

            <div v-for="item in (receiptInvoice.items || [])" :key="item.id" class="grid grid-cols-12 py-0.5">
              <div class="col-span-6 space-y-0.5">
                <div class="font-bold uppercase truncate">{{ item.product_name_snapshot }}</div>
                <div class="text-[8px] text-slate-600">ART: {{ item.article_number_snapshot }} | Size: IND {{ item.size_number_snapshot }}</div>

              </div>
              <div class="col-span-2 text-center font-bold">{{ item.quantity }}</div>
              <div class="col-span-4 text-right font-bold">₹{{ formatCurrency(item.subtotal) }}</div>
            </div>
          </div>

          <!-- Receipt Totals -->
          <div class="space-y-1 text-[10px] border-b border-dashed border-slate-400 pb-3">
            <div class="flex justify-between">
              <span>Subtotal Gross:</span>
              <span>₹{{ formatCurrency(receiptInvoice.subtotal) }}</span>
            </div>
            <div v-if="receiptInvoice.discount_amount > 0" class="flex justify-between font-bold text-emerald-700">
              <span>Discount:</span>
              <span>-₹{{ formatCurrency(receiptInvoice.discount_amount) }}</span>
            </div>
            <div v-if="receiptInvoice.is_gst_enabled" class="flex justify-between text-slate-600 text-[9px]">
              <span>Tax (GST):</span>
              <span>₹{{ formatCurrency(receiptInvoice.total_tax) }}</span>
            </div>
            <div class="flex justify-between text-xs font-black pt-1 border-t border-slate-300">
              <span>GRAND TOTAL:</span>
              <span>₹{{ formatCurrency(receiptInvoice.grand_total) }}</span>
            </div>
          </div>

          <!-- Payment Breakdown -->
          <div class="text-[9px] space-y-0.5 border-b border-dashed border-slate-400 pb-2">
            <div v-for="p in (receiptInvoice.payments || [])" :key="p.id" class="flex justify-between font-bold">
              <span class="uppercase">Paid via {{ p.payment_method }}:</span>
              <span>₹{{ formatCurrency(p.amount) }}</span>
            </div>
          </div>

          <!-- Receipt Footer -->
          <div class="text-center text-[9px] space-y-1 pt-1">
            <p class="font-bold">*** THANK YOU FOR SHOPPING ***</p>
            <p class="text-[8px] text-slate-500">Footwear goods once sold can be exchanged within 7 days with original receipt.</p>
            <div class="pt-2 border-t border-slate-200 text-[8px] font-bold text-slate-500">
              <span>Developed By </span>
              <a href="https://techgoogly.com" target="_blank" class="underline text-slate-800">Tech Googly</a>
            </div>
          </div>
        </div>

        <!-- Print Action Buttons -->
        <div class="flex items-center justify-end gap-3 pt-2 no-print">
          <button
            type="button"
            @click="receiptInvoice = null"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs transition-colors"
          >
            Close
          </button>
          <button
            type="button"
            @click="triggerPrint"
            class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-black text-xs transition-all shadow-md flex items-center gap-1.5"
          >
            <span>🖨️</span>
            <span>Print Thermal Receipt</span>
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
const customer = ref({});
const historyItems = ref([]);
const summary = ref({});
const storeCreditBalance = ref(0);

const loadingCustomer = ref(true);
const loadingHistory = ref(false);
const customerError = ref(null);

const selectedInvoice = ref(null);
const receiptInvoice = ref(null);

// Filters State
const filters = reactive({
  search: '',
  start_date: '',
  end_date: '',
  payment_method: '',
  status: '',
  per_page: 10,
});

// Pagination State
const pagination = reactive({
  current_page: 1,
  last_page: 1,
  total: 0,
});

const hasActiveFilters = computed(() => {
  return Boolean(
    filters.search ||
    filters.start_date ||
    filters.end_date ||
    filters.payment_method ||
    filters.status
  );
});

let debounceTimer = null;
function debouncedFetch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchPurchaseHistory(1);
  }, 350);
}

function clearFilters() {
  filters.search = '';
  filters.start_date = '';
  filters.end_date = '';
  filters.payment_method = '';
  filters.status = '';
  fetchPurchaseHistory(1);
}

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
  const num = Number(val || 0);
  return num.toLocaleString('en-IN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function getPaymentMethodBadges(inv) {
  if (inv.payment_methods && inv.payment_methods.length > 0) {
    return inv.payment_methods;
  }
  return [inv.payment_method || 'cash'];
}

async function loadCustomerProfile() {
  loadingCustomer.value = true;
  customerError.value = null;
  try {
    const [cRes, sRes, crRes] = await Promise.all([
      api.get(`/customers/${id.value}`),
      api.get(`/customers/${id.value}/purchase-summary`),
      api.get(`/customers/${id.value}/store-credit`).catch(() => ({ data: { balance: 0 } })),
    ]);

    customer.value = cRes.data || cRes;
    summary.value = sRes.data || sRes;

    const creditAcc = crRes.data || crRes;
    storeCreditBalance.value = creditAcc?.balance || creditAcc?.data?.balance || 0;
  } catch (err) {
    console.error('Failed to load customer profile:', err);
    customerError.value = err.response?.data?.message || 'Failed to load customer profile from database.';
  } finally {
    loadingCustomer.value = false;
  }
}

async function fetchPurchaseHistory(page = 1) {
  loadingHistory.value = true;
  try {
    const params = {
      page: page,
      per_page: filters.per_page,
    };

    if (filters.search) params.search = filters.search;
    if (filters.start_date) params.start_date = filters.start_date;
    if (filters.end_date) params.end_date = filters.end_date;
    if (filters.payment_method) params.payment_method = filters.payment_method;
    if (filters.status) params.status = filters.status;

    const res = await api.get(`/customers/${id.value}/purchase-history`, { params });
    const responsePayload = res.data || res;

    if (responsePayload && responsePayload.items) {
      historyItems.value = responsePayload.items;
      if (responsePayload.pagination) {
        pagination.current_page = responsePayload.pagination.current_page;
        pagination.last_page = responsePayload.pagination.last_page;
        pagination.total = responsePayload.pagination.total;
      }
    } else if (Array.isArray(responsePayload)) {
      historyItems.value = responsePayload;
      pagination.total = responsePayload.length;
      pagination.current_page = 1;
      pagination.last_page = 1;
    } else {
      historyItems.value = [];
    }
  } catch (err) {
    console.error('Failed to load customer purchase history:', err);
  } finally {
    loadingHistory.value = false;
  }
}

function openInvoiceModal(inv) {
  selectedInvoice.value = inv;
}

function openThermalReceiptModal(inv) {
  receiptInvoice.value = inv;
}

function triggerPrint() {
  window.print();
}

onMounted(() => {
  loadCustomerProfile();
  fetchPurchaseHistory(1);
});
</script>

<style>
@media print {
  body * {
    visibility: hidden;
  }
  #receipt-print-container, #receipt-print-container * {
    visibility: visible;
  }
  #receipt-print-container {
    position: absolute;
    left: 0;
    top: 0;
    width: 80mm !important;
    padding: 0 !important;
    border: none !important;
    box-shadow: none !important;
  }
  .no-print {
    display: none !important;
  }
}
</style>
