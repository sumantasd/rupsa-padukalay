<template>
  <div class="space-y-6 antialiased font-sans max-w-7xl mx-auto pb-16">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Footwear Item Exchanges</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Process footwear size & variant exchanges, restore old stock, issue new stock, and reconcile price differences for Main Outlet (STR-001).
        </p>
      </div>
      <button
        @click="openNewExchangeWizard"
        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white font-black text-xs rounded-xl shadow-md shadow-red-600/20 transition-all uppercase tracking-wider shrink-0"
      >
        <span>🔄</span>
        <span>Process New Exchange</span>
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
          placeholder="Search exchange #, original invoice #, customer..."
          class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-4 py-2.5 font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
        />
      </div>

      <div class="flex items-center gap-3 w-full md:w-auto overflow-x-auto">
        <button
          @click="fetchExchanges(1)"
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
      <h3 class="font-black text-sm text-red-900">Unable to load item exchanges</h3>
      <p class="text-xs text-red-700 font-medium max-w-md mx-auto">{{ error }}</p>
      <button @click="fetchExchanges(1)" class="px-4 py-2 bg-red-600 text-white font-bold rounded-xl text-xs shadow-xs">
        Try Again
      </button>
    </div>

    <!-- Empty State -->
    <div v-else-if="exchangesList.length === 0" class="p-12 bg-white rounded-2xl border border-slate-200/90 text-center space-y-4 shadow-xs">
      <div class="h-16 w-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto text-3xl">
        🔄
      </div>
      <div class="space-y-1">
        <h3 class="font-black text-base text-slate-900">No Item Exchanges Logged</h3>
        <p class="text-xs text-slate-500 max-w-sm mx-auto">
          No footwear size or variant exchange transactions have been logged yet.
        </p>
      </div>
    </div>

    <!-- Mobile Cards List View (< md) -->
    <div v-else-if="exchangesList.length > 0" class="space-y-3 md:hidden">
      <MobileListCard
        v-for="exc in exchangesList"
        :key="exc.id"
        :title="exc.exchange_number || exc.return_number || ('EXC-' + exc.id)"
        :subtitle="formatDateTime(exc.created_at) + ' • ' + (exc.customer_name || exc.customer?.name || 'Walk-in Customer')"
        :status="exc.payment_method || exc.refund_mode || 'Exchange'"
        status-type="info"
        :metric="'₹' + formatCurrency(Math.abs(exc.price_difference || 0))"
      >
        <div class="flex items-center justify-between text-xs font-mono">
          <span class="text-slate-500">Invoice #:</span>
          <span class="font-bold text-red-600">{{ exc.original_invoice_number || exc.original_invoice?.invoice_number || 'N/A' }}</span>
        </div>
        <div class="flex items-center justify-between text-xs font-mono">
          <span class="text-slate-500">Difference:</span>
          <span :class="['font-bold', exc.price_difference > 0 ? 'text-red-600' : exc.price_difference < 0 ? 'text-purple-700' : 'text-slate-700']">
            {{ exc.price_difference > 0 ? '+ ₹' : exc.price_difference < 0 ? '- ₹' : '₹' }}{{ formatCurrency(Math.abs(exc.price_difference || 0)) }}
            ({{ exc.price_difference > 0 ? 'Paid' : exc.price_difference < 0 ? 'Refund' : 'Even' }})
          </span>
        </div>

        <template #actions>
          <button
            @click="selectedExchange = exc"
            class="px-3 py-1.5 bg-slate-900 text-white rounded-lg font-bold text-xs cursor-pointer flex items-center gap-1"
          >
            <span>🔍 View</span>
          </button>
          <button
            @click="openExchangeReceipt(exc)"
            class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-300 rounded-lg font-bold text-xs cursor-pointer flex items-center gap-1"
          >
            <span>🖨️ Receipt</span>
          </button>
        </template>
      </MobileListCard>
    </div>

    <!-- Desktop DataTable View (>= md) -->
    <div v-if="exchangesList.length > 0" class="hidden md:block bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
              <th class="py-3.5 px-4">Exchange #</th>
              <th class="py-3.5 px-4">Original Invoice #</th>
              <th class="py-3.5 px-4">Exchange Date</th>
              <th class="py-3.5 px-4">Customer Details</th>
              <th class="py-3.5 px-4">Returned Old Item</th>
              <th class="py-3.5 px-4">Replacement New Item</th>
              <th class="py-3.5 px-4 text-right">Price Difference</th>
              <th class="py-3.5 px-4 text-center">Mode</th>
              <th class="py-3.5 px-4">Staff</th>
              <th class="py-3.5 px-4 text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
            <tr v-for="exc in exchangesList" :key="exc.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="py-3.5 px-4 font-mono font-bold text-blue-600">
                {{ exc.exchange_number || exc.return_number || ('EXC-' + exc.id) }}
              </td>

              <td class="py-3.5 px-4 font-mono font-bold text-red-600">
                {{ exc.original_invoice_number || exc.original_invoice?.invoice_number || 'N/A' }}
              </td>

              <td class="py-3.5 px-4 font-mono text-[11px] text-slate-600">
                {{ formatDateTime(exc.created_at) }}
              </td>

              <td class="py-3.5 px-4">
                <div class="font-bold text-slate-900">{{ exc.customer_name || exc.customer?.name || 'Walk-in Customer' }}</div>
                <div v-if="exc.customer_mobile || exc.customer?.mobile_number" class="text-[10px] font-mono text-slate-400">
                  {{ exc.customer_mobile || exc.customer?.mobile_number }}
                </div>
              </td>

              <td class="py-3.5 px-4">
                <div v-for="item in (exc.returned_items || exc.items || [])" :key="item.id" class="text-[11px]">
                  <span class="font-bold text-slate-800">{{ item.product_name || item.invoice_item?.product_name_snapshot || 'Old Item' }}</span>
                  <span class="font-mono text-slate-500"> (IND {{ item.size || item.size_number_snapshot }})</span>
                </div>
              </td>

              <td class="py-3.5 px-4">
                <div v-for="mv in (exc.replacement_items || exc.replacementMovements || [])" :key="mv.id" class="text-[11px]">
                  <span class="font-bold text-emerald-800">{{ mv.product_name || mv.variant_size?.variant?.product?.name || 'New Item' }}</span>
                  <span class="font-mono text-emerald-600"> (IND {{ mv.size || mv.variant_size?.size?.size_number }})</span>
                </div>
              </td>

              <td class="py-3.5 px-4 text-right font-mono font-black text-slate-900">
                <span :class="exc.price_difference > 0 ? 'text-red-600' : exc.price_difference < 0 ? 'text-purple-700' : 'text-slate-700'">
                  ₹{{ formatCurrency(Math.abs(exc.price_difference || 0)) }}
                </span>
                <span class="text-[9px] block uppercase font-sans text-slate-400">
                  {{ exc.price_difference > 0 ? 'Paid' : exc.price_difference < 0 ? 'Refund' : 'Even' }}
                </span>
              </td>

              <td class="py-3.5 px-4 text-center">
                <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-blue-50 text-blue-800 border border-blue-200">
                  {{ exc.payment_method || exc.refund_mode }}
                </span>
              </td>

              <td class="py-3.5 px-4 text-slate-600">
                {{ exc.processed_by_name || exc.processor?.name || 'Staff' }}
              </td>

              <td class="py-3.5 px-4 text-center">
                <div class="flex items-center justify-center gap-1.5">
                  <button
                    @click="openExchangeReceipt(exc)"
                    class="px-2.5 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 rounded-lg font-bold text-[11px] border border-red-200 transition-colors"
                  >
                    🧾 Receipt
                  </button>
                  <button
                    v-if="hasPermission('exchanges.delete')"
                    @click="confirmDeleteExchange(exc)"
                    class="px-2.5 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg font-bold text-[11px] transition-colors shadow-xs"
                    title="Delete Exchange Record"
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
      <div v-if="pagination.last_page > 1" class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs font-bold text-slate-600">
        <div>Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} Total Exchanges)</div>
        <div class="flex items-center gap-2">
          <button
            @click="fetchExchanges(pagination.current_page - 1)"
            :disabled="pagination.current_page === 1 || loading"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg disabled:opacity-30 hover:bg-slate-100 transition-colors"
          >
            ← Previous
          </button>
          <button
            @click="fetchExchanges(pagination.current_page + 1)"
            :disabled="pagination.current_page === pagination.last_page || loading"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg disabled:opacity-30 hover:bg-slate-100 transition-colors"
          >
            Next →
          </button>
        </div>
      </div>
    </div>

    <!-- PROCESS NEW EXCHANGE WIZARD MODAL -->
    <Teleport to="body">
      <div v-if="showWizard" class="fixed inset-0 z-[500] bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-4xl w-full p-6 sm:p-8 space-y-6 max-h-[90vh] overflow-y-auto text-xs">
          <!-- Wizard Header -->
          <div class="flex items-center justify-between border-b border-slate-200 pb-4">
            <div>
              <h3 class="text-lg font-black text-slate-900">Footwear Exchange Counter</h3>
              <p class="text-xs text-slate-500 font-medium mt-0.5">Step {{ wizardStep }} of 4: {{ getStepTitle(wizardStep) }} • Outlet STR-001</p>
            </div>
            <button @click="showWizard = false" class="p-2 rounded-xl text-slate-400 hover:text-slate-800 font-black text-lg cursor-pointer">✕</button>
          </div>

          <!-- STEP 1: Search & Select Original Bill -->
          <div v-if="wizardStep === 1" class="space-y-4">
            <div class="space-y-1.5">
              <label class="font-black text-slate-800 block">Search Original Sales Invoice # or Customer Mobile / Name</label>
              <div class="flex gap-2">
                <input
                  v-model="invoiceSearchQuery"
                  @keyup.enter="searchOriginalInvoices"
                  type="text"
                  placeholder="e.g. INV-20260903-XXXX or 9830098300..."
                  class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 font-bold text-slate-900 focus:ring-2 focus:ring-red-600 focus:bg-white"
                />
                <button
                  @click="searchOriginalInvoices"
                  :disabled="searchingInvoices"
                  class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold shrink-0 shadow-sm cursor-pointer"
                >
                  {{ searchingInvoices ? 'Searching...' : 'Search Bill' }}
                </button>
              </div>
            </div>

            <div v-if="foundInvoices.length > 0" class="space-y-2 max-h-60 overflow-y-auto border border-slate-200 rounded-2xl p-2 bg-slate-50">
              <div
                v-for="inv in foundInvoices"
                :key="inv.id"
                @click="selectInvoiceForExchange(inv)"
                class="p-3.5 bg-white hover:bg-blue-50/80 border border-slate-200/90 rounded-xl cursor-pointer transition-all flex items-center justify-between shadow-2xs"
              >
                <div class="space-y-0.5">
                  <div class="font-mono font-black text-red-600 text-xs">{{ inv.invoice_number }}</div>
                  <div class="font-bold text-slate-900 text-xs">{{ inv.customer?.name || inv.customer_name || 'Walk-in Customer' }}</div>
                  <div class="text-[10px] text-slate-500 font-mono">Mobile: {{ inv.customer?.mobile_number || inv.customer_mobile || 'N/A' }} • Date: {{ formatDateTime(inv.created_at) }}</div>
                </div>
                <div class="text-right">
                  <div class="font-mono font-black text-slate-900 text-sm">₹{{ formatCurrency(inv.grand_total) }}</div>
                  <span class="px-2 py-0.5 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded text-[9px] font-black uppercase">
                    {{ inv.payment_status || 'Paid' }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- STEP 2: Select Old Product Being Exchanged -->
          <div v-else-if="wizardStep === 2" class="space-y-5">
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 flex justify-between items-center text-xs">
              <div>
                <div class="font-mono font-black text-red-600 text-sm">{{ targetInvoice.invoice_number }}</div>
                <div class="font-bold text-slate-800">{{ targetInvoice.customer?.name || targetInvoice.customer_name || 'Walk-in Customer' }} ({{ targetInvoice.customer?.mobile_number || targetInvoice.customer_mobile || 'N/A' }})</div>
                <div class="text-[10px] text-slate-500 font-mono">Bill Date: {{ formatDateTime(targetInvoice.created_at) }}</div>
              </div>
              <button @click="wizardStep = 1" class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs font-bold text-slate-700 hover:text-red-600 cursor-pointer">
                ← Change Bill
              </button>
            </div>

            <div class="space-y-2">
              <h4 class="font-black text-slate-900 uppercase text-[11px] tracking-wider">Select Purchased Footwear Item to Exchange</h4>
              <div class="overflow-x-auto rounded-2xl border border-slate-200 shadow-2xs">
                <table class="w-full text-left text-xs">
                  <thead class="bg-slate-100 border-b border-slate-200 text-[10px] font-black text-slate-600 uppercase">
                    <tr>
                      <th class="py-3 px-3">Article #</th>
                      <th class="py-3 px-3">Product Name</th>
                      <th class="py-3 px-3">Brand</th>
                      <th class="py-3 px-3">Color / Size (IND)</th>
                      <th class="py-3 px-3 text-center">Purchased</th>
                      <th class="py-3 px-3 text-center">Returned / Exchanged</th>
                      <th class="py-3 px-3 text-center">Exchangeable</th>
                      <th class="py-3 px-3 text-center">Exchange Qty</th>
                      <th class="py-3 px-3 text-right">Selling Price (₹)</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                    <tr v-for="item in exchangeReturnedItems" :key="item.invoice_item_id" class="hover:bg-slate-50">
                      <td class="py-3 px-3 font-mono font-bold text-red-600">{{ item.article_number }}</td>
                      <td class="py-3 px-3 font-bold text-slate-900">{{ item.product_name }}</td>
                      <td class="py-3 px-3 text-slate-600">{{ item.brand_name || 'Footwear' }}</td>
                      <td class="py-3 px-3">
                        <span>{{ item.color_name }}</span>
                        <span class="px-1.5 py-0.5 bg-slate-100 border border-slate-200 rounded text-[10px] font-bold ml-1">
                          IND {{ item.size_number }}
                        </span>
                      </td>
                      <td class="py-3 px-3 text-center font-mono font-bold text-slate-600">{{ item.original_quantity }}</td>
                      <td class="py-3 px-3 text-center font-mono text-slate-500">
                        {{ item.already_returned + item.already_exchanged }}
                      </td>
                      <td class="py-3 px-3 text-center font-mono font-bold text-emerald-700">
                        {{ item.remaining_exchangeable }}
                      </td>
                      <td class="py-3 px-3 text-center">
                        <input
                          v-model.number="item.quantity"
                          type="number"
                          min="0"
                          :max="item.remaining_exchangeable"
                          class="w-16 bg-slate-50 border border-slate-300 rounded-lg px-2 py-1 text-center font-mono font-bold text-slate-900 focus:ring-2 focus:ring-red-600"
                        />
                      </td>
                      <td class="py-3 px-3 text-right font-mono font-black text-slate-900">
                        ₹{{ formatCurrency(item.unit_price) }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="flex items-center justify-between pt-3 border-t border-slate-200">
              <div>
                <span class="text-xs text-slate-500 font-bold block">Selected Old Items Value:</span>
                <span class="font-mono font-black text-amber-700 text-lg">₹{{ formatCurrency(totalReturnedValue) }}</span>
              </div>

              <button
                @click="wizardStep = 3"
                :disabled="totalReturnedValue <= 0"
                class="px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl shadow-xs disabled:opacity-30 cursor-pointer"
              >
                Next: Select Replacement Footwear →
              </button>
            </div>
          </div>

          <!-- STEP 3: Search & Select Replacement Footwear Product (Color & IND Size Matrix) -->
          <div v-else-if="wizardStep === 3" class="space-y-5">
            <div class="space-y-1.5">
              <label class="font-black text-slate-800 block">Search Catalog for Replacement Footwear (Article # / Name / Brand)</label>
              <div class="flex gap-2">
                <input
                  v-model="productSearchQuery"
                  @keyup.enter="searchReplacementProducts"
                  type="text"
                  placeholder="e.g. RP-008 or Leather Loafer..."
                  class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 font-bold text-slate-900 focus:ring-2 focus:ring-red-600 focus:bg-white"
                />
                <button
                  @click="searchReplacementProducts"
                  :disabled="searchingProducts"
                  class="px-5 py-2.5 bg-slate-900 text-white font-bold rounded-xl shrink-0 cursor-pointer"
                >
                  {{ searchingProducts ? 'Searching...' : 'Search Catalog' }}
                </button>
              </div>
            </div>

            <!-- Product Catalog Search Results -->
            <div v-if="foundProducts.length > 0" class="space-y-3 max-h-64 overflow-y-auto border border-slate-200 rounded-2xl p-3 bg-slate-50">
              <div
                v-for="p in foundProducts"
                :key="p.id"
                class="p-3.5 bg-white border border-slate-200/90 rounded-xl space-y-2 shadow-2xs"
              >
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                  <div>
                    <span class="font-mono font-black text-red-600 text-xs mr-2">{{ p.article_number }}</span>
                    <span class="font-black text-slate-900 text-xs">{{ p.name }}</span>
                    <span class="text-[10px] text-slate-500 font-bold ml-2">({{ p.brand?.name || 'Footwear' }})</span>
                  </div>
                  <div class="font-mono font-black text-slate-900 text-xs">
                    MRP: ₹{{ formatCurrency(p.mrp || p.selling_price) }}
                  </div>
                </div>

                <!-- Available Color & IND Size Variant Buttons -->
                <div class="space-y-1.5">
                  <span class="text-[10px] font-bold uppercase text-slate-400 block">Available Colors & IND Sizes (Outlet STR-001):</span>
                  <div class="flex flex-wrap gap-2">
                    <template v-for="v in (p.variants || [])" :key="v.id">
                      <button
                        v-for="sz in (v.sizes || [])"
                        :key="sz.id"
                        @click="addReplacementVariantSize(p, v, sz)"
                        :disabled="sz.stock_quantity <= 0"
                        :class="[
                          'px-2.5 py-1.5 rounded-xl border text-xs font-bold transition-all flex items-center gap-1.5',
                          sz.stock_quantity > 0
                            ? 'bg-emerald-50 hover:bg-emerald-100 text-emerald-900 border-emerald-300 cursor-pointer'
                            : 'bg-slate-100 text-slate-400 border-slate-200 cursor-not-allowed opacity-60'
                        ]"
                      >
                        <span class="w-2.5 h-2.5 rounded-full border border-slate-400 inline-block" :style="{ backgroundColor: v.color?.code || '#999' }"></span>
                        <span>{{ v.color?.name || 'Std' }}</span>
                        <span class="font-mono bg-white px-1.5 py-0.5 rounded border border-slate-200 font-black">
                          IND {{ sz.size_number }}
                        </span>
                        <span class="font-mono text-[10px] font-bold text-slate-600">
                          ({{ sz.stock_quantity > 0 ? sz.stock_quantity + ' in stock' : 'Out of stock' }})
                        </span>
                      </button>
                    </template>
                  </div>
                </div>
              </div>
            </div>

            <!-- Selected Replacement Items List -->
            <div v-if="exchangeReplacementItems.length > 0" class="space-y-2 pt-2 border-t border-slate-200">
              <h4 class="font-black text-slate-900 uppercase text-[11px] tracking-wider">Selected Replacement Footwear</h4>
              <div
                v-for="(rep, idx) in exchangeReplacementItems"
                :key="idx"
                class="p-3.5 bg-emerald-50/70 border border-emerald-200 rounded-2xl flex flex-col sm:flex-row items-center justify-between gap-3 shadow-2xs"
              >
                <div class="space-y-0.5">
                  <div class="font-bold text-slate-900 text-xs">
                    {{ rep.product_name }} (Article: <span class="font-mono text-red-600 font-black">{{ rep.article_number }}</span>)
                  </div>
                  <div class="text-[11px] text-slate-700">
                    Color: <span class="font-bold">{{ rep.color_name }}</span> • Size: <span class="font-mono font-bold bg-white px-1.5 py-0.5 rounded border border-slate-300">IND {{ rep.size_number }}</span> • Available Stock: <span class="font-mono font-bold text-emerald-800">{{ rep.available_stock }} pcs</span>
                  </div>
                </div>

                <div class="flex items-center gap-3">
                  <div class="flex items-center gap-1">
                    <span class="text-[10px] font-bold text-slate-500">Qty:</span>
                    <input
                      v-model.number="rep.quantity"
                      type="number"
                      min="1"
                      :max="rep.available_stock"
                      class="w-16 bg-white border border-slate-300 rounded-lg px-2 py-1 text-center font-mono font-bold text-slate-900 focus:ring-2 focus:ring-emerald-600"
                    />
                  </div>
                  <div class="font-mono font-black text-slate-900 text-sm">₹{{ formatCurrency(rep.quantity * rep.unit_price) }}</div>
                  <button @click="exchangeReplacementItems.splice(idx, 1)" class="p-1 text-red-600 hover:text-red-800 font-black text-sm cursor-pointer">✕</button>
                </div>
              </div>
            </div>

            <!-- Stock Error Banner -->
            <div v-if="stockErrorMessage" class="p-3.5 bg-red-50 border border-red-200 rounded-xl text-red-800 font-bold text-xs flex items-center gap-2">
              <span>⚠️</span>
              <span>{{ stockErrorMessage }}</span>
            </div>

            <div class="flex items-center justify-between pt-3 border-t border-slate-200">
              <button @click="wizardStep = 2" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl cursor-pointer">← Back</button>
              <button
                @click="wizardStep = 4"
                :disabled="exchangeReplacementItems.length === 0 || hasStockErrors"
                class="px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl shadow-xs disabled:opacity-30 cursor-pointer"
              >
                Next: Review & Calculate Difference →
              </button>
            </div>
          </div>

          <!-- STEP 4: Price Difference & Payment / Refund Selection -->
          <div v-else-if="wizardStep === 4" class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-slate-50 p-5 rounded-2xl border border-slate-200/90 text-center font-mono">
              <div class="space-y-0.5">
                <span class="text-[10px] font-bold uppercase text-slate-500 tracking-wider block">Returned Old Total</span>
                <span class="text-xl font-black text-amber-700">₹{{ formatCurrency(totalReturnedValue) }}</span>
              </div>
              <div class="space-y-0.5">
                <span class="text-[10px] font-bold uppercase text-slate-500 tracking-wider block">Replacement New Total</span>
                <span class="text-xl font-black text-emerald-700">₹{{ formatCurrency(totalReplacementValue) }}</span>
              </div>
              <div class="space-y-0.5">
                <span class="text-[10px] font-bold uppercase text-slate-500 tracking-wider block">Price Difference</span>
                <span
                  :class="[
                    'text-xl font-black',
                    priceDifference > 0 ? 'text-red-600' : priceDifference < 0 ? 'text-purple-700' : 'text-slate-900'
                  ]"
                >
                  ₹{{ formatCurrency(Math.abs(priceDifference)) }}
                </span>
                <span class="text-[9px] uppercase font-sans font-bold block text-slate-500">
                  ({{ priceDifference > 0 ? 'Customer Pays Extra' : priceDifference < 0 ? 'Customer Refund Due' : '₹0 Even Size Exchange' }})
                </span>
              </div>
            </div>

            <!-- Additional Payment Options if Price Difference > 0 -->
            <div v-if="priceDifference > 0" class="p-4 bg-red-50/50 border border-red-200 rounded-2xl space-y-2">
              <label class="font-black text-slate-900 block text-xs">
                Select Payment Method for Extra Difference (₹{{ formatCurrency(priceDifference) }}) <span class="text-red-600">*</span>
              </label>
              <select
                v-model="exchangeMeta.payment_method"
                class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 font-bold text-slate-900 focus:ring-2 focus:ring-red-600"
              >
                <option value="cash">Cash Payment</option>
                <option value="upi">UPI / GPay / PhonePe</option>
                <option value="card">Credit / Debit Card</option>
              </select>
            </div>

            <!-- Refund Options if Price Difference < 0 -->
            <div v-else-if="priceDifference < 0" class="p-4 bg-purple-50/50 border border-purple-200 rounded-2xl space-y-2">
              <label class="font-black text-slate-900 block text-xs">
                Select Refund Method for Excess Amount (₹{{ formatCurrency(Math.abs(priceDifference)) }}) <span class="text-red-600">*</span>
              </label>
              <select
                v-model="exchangeMeta.refund_mode"
                class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 font-bold text-slate-900 focus:ring-2 focus:ring-purple-600"
              >
                <option value="cash">Cash Refund</option>
                <option value="upi">UPI Refund</option>
                <option value="card">Card Refund</option>
                <option value="store_credit">Issue Customer Store Credit</option>
              </select>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-slate-200">
              <button @click="wizardStep = 3" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl cursor-pointer">← Back</button>
              <button
                @click="submitExchange"
                :disabled="submittingExchange"
                class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white font-black rounded-xl shadow-md shadow-red-600/20 transition-all uppercase tracking-wider text-xs cursor-pointer"
              >
                {{ submittingExchange ? 'Processing Atomic Exchange...' : 'Confirm Footwear Exchange' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- PRINTABLE EXCHANGE RECEIPT MODAL -->
    <Teleport to="body">
      <div v-if="receiptExchange" class="fixed inset-0 z-[500] bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-sm w-full p-6 space-y-4">
          <!-- Header -->
          <div class="flex items-center justify-between border-b border-slate-200 pb-3">
            <span class="font-black text-xs text-slate-900 uppercase">Footwear Exchange Receipt Preview</span>
            <button @click="receiptExchange = null" class="text-slate-400 hover:text-slate-800 font-bold cursor-pointer">✕</button>
          </div>

          <!-- Receipt Print Container -->
          <div class="p-2 bg-slate-100 rounded-2xl border border-slate-200 overflow-x-auto flex justify-center">
            <ThermalReceipt document-type="exchange" :data="receiptExchange" />
          </div>

          <div class="flex justify-end gap-2 pt-2 border-t border-slate-200">
            <button @click="receiptExchange = null" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl font-bold text-xs cursor-pointer">
              Close
            </button>
            <button @click="printReceipt" class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md cursor-pointer">
              🖨️ Print Receipt
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- EXCHANGE DETAILS MODAL -->
    <Teleport to="body">
      <div v-if="selectedExchange" class="fixed inset-0 z-[500] bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-3xl w-full p-6 sm:p-8 space-y-6 max-h-[90vh] overflow-y-auto text-xs">
          <div class="flex items-center justify-between border-b border-slate-200 pb-4">
            <div>
              <h3 class="text-lg font-black text-slate-900">Exchange Record: {{ selectedExchange.exchange_number || selectedExchange.return_number }}</h3>
              <p class="text-xs text-slate-500 font-medium mt-0.5">Processed on {{ formatDateTime(selectedExchange.created_at) }} • Main Outlet (STR-001)</p>
            </div>
            <button @click="selectedExchange = null" class="p-2 rounded-xl text-slate-400 hover:text-slate-800 font-black text-lg cursor-pointer">✕</button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200">
            <div>
              <div class="font-bold text-slate-400 uppercase text-[10px]">Original Bill</div>
              <div class="font-mono font-black text-red-600 text-sm">{{ selectedExchange.original_invoice_number || selectedExchange.original_invoice?.invoice_number }}</div>
              <div class="font-bold text-slate-800">Customer: {{ selectedExchange.customer_name || selectedExchange.customer?.name || 'Walk-in Customer' }}</div>
            </div>
            <div class="text-left sm:text-right">
              <div class="font-bold text-slate-400 uppercase text-[10px]">Exchange Mode & Diff</div>
              <div class="font-bold text-blue-700 uppercase text-sm">{{ selectedExchange.payment_method || selectedExchange.refund_mode }}</div>
              <div class="font-mono font-black text-slate-900">Price Diff: ₹{{ formatCurrency(Math.abs(selectedExchange.price_difference || 0)) }}</div>
              <div class="text-[11px] text-slate-600 font-medium">Processed by: {{ selectedExchange.processed_by_name || selectedExchange.processor?.name || 'Staff' }}</div>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-4 border-t border-slate-200">
            <button
              @click="openExchangeReceipt(selectedExchange); selectedExchange = null"
              class="px-4 py-2 bg-red-600 text-white hover:bg-red-700 rounded-xl font-bold cursor-pointer"
            >
              🧾 Print Receipt
            </button>
            <button @click="selectedExchange = null" class="px-5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold cursor-pointer">
              Close
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
import { useAuth } from '../../composables/useAuth';

const { hasPermission } = useAuth();
const printerStore = usePrinterStore();
const exchangesList = ref([]);
const loading = ref(false);
const error = ref(null);

const showWizard = ref(false);
const wizardStep = ref(1);
const invoiceSearchQuery = ref('');
const searchingInvoices = ref(false);
const foundInvoices = ref([]);
const targetInvoice = ref(null);

const exchangeReturnedItems = ref([]);
const productSearchQuery = ref('');
const searchingProducts = ref(false);
const foundProducts = ref([]);
const exchangeReplacementItems = ref([]);
const submittingExchange = ref(false);

const selectedExchange = ref(null);
const receiptExchange = ref(null);

const pagination = reactive({
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1,
});

const filters = reactive({
  search: '',
});

const exchangeMeta = reactive({
  payment_method: 'cash',
  refund_mode: 'cash',
  reason: 'Product Exchange',
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

function getStepTitle(step) {
  if (step === 1) return 'Select Original Bill';
  if (step === 2) return 'Select Old Product to Exchange';
  if (step === 3) return 'Select Replacement Footwear Product / Size';
  if (step === 4) return 'Review Price Difference & Reconcile';
  return '';
}

const totalReturnedValue = computed(() => {
  return exchangeReturnedItems.value.reduce((acc, i) => acc + (Number(i.quantity || 0) * Number(i.unit_price || 0)), 0);
});

const totalReplacementValue = computed(() => {
  return exchangeReplacementItems.value.reduce((acc, i) => acc + (Number(i.quantity || 0) * Number(i.unit_price || 0)), 0);
});

const priceDifference = computed(() => {
  return Math.round((totalReplacementValue.value - totalReturnedValue.value + Number.EPSILON) * 100) / 100;
});

const hasStockErrors = computed(() => {
  return exchangeReplacementItems.value.some(i => i.quantity > i.available_stock);
});

const stockErrorMessage = computed(() => {
  const badItem = exchangeReplacementItems.value.find(i => i.quantity > i.available_stock);
  if (badItem) {
    return `Insufficient stock for selected replacement SKU (${badItem.article_number} - IND ${badItem.size_number}). Available: ${badItem.available_stock}, Requested: ${badItem.quantity}.`;
  }
  return '';
});

let debounceTimer = null;
function debouncedSearch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchExchanges(1);
  }, 350);
}

async function fetchExchanges(page = 1) {
  loading.value = true;
  error.value = null;
  try {
    const params = {
      page,
      per_page: pagination.per_page,
    };
    if (filters.search) params.search = filters.search;

    const res = await api.get('/pos/sales/exchanges', { params });
    const payload = res.data || res;

    let items = payload.items || (Array.isArray(payload) ? payload : []);
    exchangesList.value = items;
    if (payload.pagination) Object.assign(pagination, payload.pagination);
  } catch (err) {
    console.error('Failed to fetch exchanges:', err);
    error.value = err.response?.data?.message || 'Unable to connect to exchanges database.';
  } finally {
    loading.value = false;
  }
}

function openNewExchangeWizard() {
  showWizard.value = true;
  wizardStep.value = 1;
  invoiceSearchQuery.value = '';
  foundInvoices.value = [];
  targetInvoice.value = null;
  exchangeReturnedItems.value = [];
  exchangeReplacementItems.value = [];
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

function selectInvoiceForExchange(inv) {
  targetInvoice.value = inv;
  exchangeReturnedItems.value = (inv.items || []).map(i => {
    const origQty = Number(i.quantity || 1);
    const retQty = Number(i.already_returned_quantity || 0);
    const excQty = Number(i.already_exchanged_quantity || 0);
    const remQty = Number(i.remaining_exchangeable_quantity ?? Math.max(0, origQty - (retQty + excQty)));

    return {
      invoice_item_id: i.id,
      product_variant_size_id: i.product_variant_size_id,
      article_number: i.article_number || i.sku || 'N/A',
      product_name: i.product_name || 'Footwear Item',
      brand_name: i.brand_name || 'Footwear',
      color_name: i.color_name || 'Standard',
      size_number: i.size_number || 'N/A',
      original_quantity: origQty,
      already_returned: retQty,
      already_exchanged: excQty,
      remaining_exchangeable: remQty,
      quantity: 0,
      unit_price: Number(i.unit_price || 0),
      restock_condition: 'resellable',
    };
  });
  wizardStep.value = 2;
}

async function searchReplacementProducts() {
  if (!productSearchQuery.value) return;
  searchingProducts.value = true;
  try {
    const res = await api.get('/products', { params: { search: productSearchQuery.value, per_page: 5 } });
    const payload = res.data || res;
    foundProducts.value = payload.items || (Array.isArray(payload) ? payload : []);
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to search catalog.');
  } finally {
    searchingProducts.value = false;
  }
}

function addReplacementVariantSize(product, variant, sizeObj) {
  if (sizeObj.stock_quantity <= 0) {
    alert('Selected footwear size is out of stock.');
    return;
  }

  const existing = exchangeReplacementItems.value.find(i => i.product_variant_size_id === sizeObj.id);
  if (existing) {
    if (existing.quantity < sizeObj.stock_quantity) {
      existing.quantity++;
    } else {
      alert('Cannot add more than available stock quantity.');
    }
  } else {
    exchangeReplacementItems.value.push({
      product_variant_size_id: sizeObj.id,
      sku: sizeObj.sku,
      article_number: product.article_number || 'N/A',
      product_name: product.name,
      color_name: variant.color?.name || 'Standard',
      size_number: sizeObj.size_number || sizeObj.size?.size_number || 'N/A',
      available_stock: Number(sizeObj.stock_quantity || 0),
      quantity: 1,
      unit_price: Number(sizeObj.selling_price || product.selling_price || 0),
    });
  }
}

async function submitExchange() {
  const returnedItems = exchangeReturnedItems.value
    .filter(i => i.quantity > 0)
    .map(i => ({
      invoice_item_id: i.invoice_item_id,
      quantity: i.quantity,
      restock_condition: i.restock_condition,
    }));

  if (returnedItems.length === 0) {
    alert('Please select at least 1 returned item to exchange.');
    return;
  }

  const replacementItems = exchangeReplacementItems.value.map(i => ({
    product_variant_size_id: i.product_variant_size_id,
    quantity: i.quantity,
    unit_price: i.unit_price,
  }));

  if (replacementItems.length === 0) {
    alert('Please select at least 1 replacement footwear product.');
    return;
  }

  submittingExchange.value = true;
  try {
    const payload = {
      returned_items: returnedItems,
      replacement_items: replacementItems,
      reason: exchangeMeta.reason,
    };

    if (priceDifference.value > 0) {
      payload.payment_method = exchangeMeta.payment_method;
    } else if (priceDifference.value < 0) {
      payload.refund_mode = exchangeMeta.refund_mode;
    }

    const res = await api.post(`/pos/sales/${targetInvoice.value.id}/exchange`, payload);
    const resultExchange = res.data?.data || res.data || res;

    showWizard.value = false;
    openExchangeReceipt(resultExchange);
    fetchExchanges(1);
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to process footwear exchange.');
  } finally {
    submittingExchange.value = false;
  }
}

function openExchangeReceipt(exc) {
  receiptExchange.value = exc;
}

function printReceipt() {
  window.print();
}

async function confirmDeleteExchange(exc) {
  if (!confirm(`Are you sure you want to delete exchange record #${exc.return_number || exc.id}?`)) return;
  try {
    await api.delete(`/pos/exchanges/${exc.id}`);
    alert('Exchange record deleted successfully.');
    fetchExchanges(pagination.value.current_page || 1);
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to delete exchange record.');
  }
}

onMounted(() => {
  fetchExchanges(1);
  if (!printerStore.loaded) {
    printerStore.fetchSettings();
  }
});

watch(
  [showWizard, selectedExchange, receiptExchange],
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
