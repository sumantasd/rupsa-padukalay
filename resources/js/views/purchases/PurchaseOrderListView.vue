<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-16 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">📝 Purchase Orders (PO)</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Create, manage and issue size-wise footwear purchase orders to suppliers for STR-001 (Does not alter physical stock until Goods Receive)
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          @click="openCreateModal"
          class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md shadow-red-600/20 transition-all uppercase tracking-wider flex items-center gap-1.5 cursor-pointer"
        >
          <span>➕</span>
          <span>New Purchase Order</span>
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
            placeholder="Search PO #, Supplier, Article..."
            class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-red-500/20"
          />
          <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
        </div>

        <select v-model="filters.status" @change="fetchOrders(1)" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-800 focus:outline-none">
          <option value="">All Statuses</option>
          <option value="draft">Draft</option>
          <option value="sent">Sent</option>
          <option value="ordered">Ordered</option>
          <option value="partially_received">Partially Received</option>
          <option value="fully_received">Fully Received</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
    </div>

    <!-- MOBILE PO CARDS VIEW (< md) -->
    <div v-if="ordersList.length > 0" class="space-y-3 md:hidden">
      <MobileListCard
        v-for="po in ordersList"
        :key="po.id"
        :title="po.po_number"
        :subtitle="po.order_date + ' • ' + po.supplier_name"
        :status="po.status || 'Draft'"
        status-type="info"
        :metric="'₹' + formatCurrency(po.grand_total)"
      >
        <div class="flex items-center justify-between text-xs font-mono">
          <span class="text-slate-500">Items:</span>
          <span class="font-bold text-slate-900">{{ po.items_count || po.items?.length || 0 }} SKUs</span>
        </div>
        <div class="flex items-center justify-between text-xs font-mono">
          <span class="text-slate-500">Supplier Info:</span>
          <span class="font-bold text-slate-700 truncate max-w-[180px]">{{ po.supplier_phone || po.supplier_name }}</span>
        </div>

        <template #actions>
          <button
            @click="openPoDetailModal(po.id)"
            class="px-3 py-1.5 bg-slate-900 text-white rounded-lg font-bold text-xs cursor-pointer flex items-center gap-1"
          >
            <span>🔍 View</span>
          </button>
          <button
            @click="printPoDocument(po.id)"
            class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-300 rounded-lg font-bold text-xs cursor-pointer flex items-center gap-1"
          >
            <span>🖨️ Print</span>
          </button>
        </template>
      </MobileListCard>
    </div>

    <!-- PO TABLE (>= md) -->
    <div class="hidden md:block bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="bg-slate-900 text-white font-extrabold uppercase tracking-wider text-[11px]">
              <th class="py-3.5 px-4">PO Number & Date</th>
              <th class="py-3.5 px-4">Supplier</th>
              <th class="py-3.5 px-4 text-center">Items</th>
              <th class="py-3.5 px-4 text-right">Subtotal</th>
              <th class="py-3.5 px-4 text-right">Tax & Disc</th>
              <th class="py-3.5 px-4 text-right font-mono">Grand Total</th>
              <th class="py-3.5 px-4 text-center">Status</th>
              <th class="py-3.5 px-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium">
            <tr v-if="loading">
              <td colspan="8" class="text-center py-12 text-slate-400 font-bold">
                Loading purchase orders...
              </td>
            </tr>
            <tr v-else-if="ordersList.length === 0">
              <td colspan="8" class="text-center py-12 text-slate-500 font-bold">
                No purchase orders found. Click "+ New Purchase Order" to issue one.
              </td>
            </tr>
            <tr v-for="po in ordersList" :key="po.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="py-3 px-4">
                <div class="font-mono font-black text-red-600 text-xs">{{ po.po_number }}</div>
                <div class="text-[10px] text-slate-400 font-medium">{{ po.order_date }}</div>
              </td>
              <td class="py-3 px-4">
                <div class="font-bold text-slate-900">{{ po.supplier_name }}</div>
                <div class="text-[10px] text-slate-500 font-mono">{{ po.supplier_phone }} | GST: {{ po.supplier_gstin || 'N/A' }}</div>
              </td>
              <td class="py-3 px-4 text-center font-bold text-slate-800">
                {{ po.items_count || po.items?.length || 0 }} SKUs
              </td>
              <td class="py-3 px-4 text-right font-bold text-slate-700">
                ₹{{ formatCurrency(po.subtotal) }}
              </td>
              <td class="py-3 px-4 text-right text-slate-500 text-[11px]">
                +₹{{ formatCurrency(po.tax_amount) }} / -₹{{ formatCurrency(po.discount_amount) }}
              </td>
              <td class="py-3 px-4 text-right font-mono font-black text-slate-900 text-sm">
                ₹{{ formatCurrency(po.grand_total) }}
              </td>
              <td class="py-3 px-4 text-center">
                <span :class="getStatusBadgeClass(po.status)">
                  {{ formatStatusLabel(po.status) }}
                </span>
              </td>
              <td class="py-3 px-4 text-right">
                <button
                  @click="viewPoDetails(po)"
                  type="button"
                  class="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 rounded-lg font-black text-[11px] transition-all cursor-pointer shadow-2xs"
                >
                  👁️ View Details
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between text-xs font-bold text-slate-600">
        <div>Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} orders)</div>
        <div class="flex items-center gap-2">
          <button @click="fetchOrders(pagination.current_page - 1)" :disabled="pagination.current_page <= 1" class="px-3 py-1 bg-white border rounded-lg disabled:opacity-40 cursor-pointer">← Prev</button>
          <button @click="fetchOrders(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page" class="px-3 py-1 bg-white border rounded-lg disabled:opacity-40 cursor-pointer">Next →</button>
        </div>
      </div>
    </div>

    <!-- SIZE-WISE FOOTWEAR PURCHASE ORDER MODAL -->
    <Teleport to="body">
      <div v-if="showCreateModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-[400] flex items-center justify-center p-0 sm:p-4 overflow-hidden font-sans">
        <div class="bg-white rounded-none sm:rounded-3xl max-w-5xl w-full p-0 shadow-2xl border-0 sm:border sm:border-slate-200 flex flex-col h-[100dvh] sm:h-auto sm:max-h-[90dvh] overflow-hidden my-0 sm:my-auto">
          <!-- Header -->
          <div class="flex items-center justify-between border-b border-slate-200 px-4 sm:px-6 py-3.5 sm:py-4 shrink-0 bg-white z-10">
            <div>
              <h2 class="text-base sm:text-lg font-black text-slate-900">➕ Create Size-Wise Purchase Order</h2>
              <p class="text-[11px] sm:text-xs text-slate-500 font-medium">Select supplier & enter size-wise quantities for footwear products</p>
            </div>
            <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-lg cursor-pointer p-1">✕</button>
          </div>

          <!-- Body -->
          <div class="p-4 sm:p-6 overflow-y-auto flex-1 space-y-5 text-xs text-slate-800">
            <!-- 1. MANDATORY SUPPLIER SELECTION CARD -->
            <div class="bg-red-50/50 p-4 rounded-2xl border-2 border-red-200/80 space-y-3">
              <label class="block font-black text-xs text-red-900 uppercase tracking-wider">
                1. Supplier Selection <span class="text-red-600">* Mandatory for every purchase</span>
              </label>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <select v-model="form.supplier_id" @change="onSupplierSelect" class="w-full bg-white border border-red-300 rounded-xl px-3.5 py-2.5 font-bold text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                    <option value="">-- Choose Supplier --</option>
                    <option v-for="sup in suppliersList" :key="sup.id" :value="sup.id">
                      {{ sup.name }} ({{ sup.company_name || 'No Company' }}) — Phone: {{ sup.phone }}
                    </option>
                  </select>
                  <p v-if="supplierError" class="text-[11px] text-red-600 font-bold mt-1">{{ supplierError }}</p>
                </div>

                <div v-if="selectedSupplier" class="p-3 bg-white rounded-xl border border-slate-200 text-xs space-y-1">
                  <div class="font-black text-slate-900">{{ selectedSupplier.name }}</div>
                  <div class="text-[11px] text-slate-500">GSTIN: {{ selectedSupplier.gstin || 'N/A' }} | Phone: {{ selectedSupplier.phone }}</div>
                  <div class="font-bold text-red-600">Current Outstanding Due: ₹{{ formatCurrency(selectedSupplier.current_due_amount || selectedSupplier.current_balance) }}</div>
                </div>
              </div>
            </div>

            <!-- 2. ORDER META INFORMATION -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
              <div>
                <label class="block font-bold text-slate-700 mb-1">Order Date</label>
                <input type="date" v-model="form.order_date" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-900" />
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">Supplier Invoice / Ref #</label>
                <input type="text" v-model="form.supplier_invoice_number" placeholder="Vendor Invoice #" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-900" />
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">Overall Tax (GST ₹)</label>
                <input type="number" step="0.01" v-model.number="form.tax_amount" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-bold text-slate-900" />
              </div>
            </div>

            <!-- 3. FOOTWEAR PRODUCT SEARCH & SIZE MATRIX ENTRY -->
            <div class="space-y-4 relative" @click.stop>
              <div class="flex items-center justify-between">
                <h3 class="font-black text-xs text-slate-900 uppercase tracking-wider">
                  2. Footwear Product Search & Size Matrix
                </h3>
              </div>

              <!-- PRODUCT SEARCH AUTOCOMPLETE CONTAINER -->
              <div class="relative">
                <div class="relative">
                  <input
                    v-model="productSearchQuery"
                    @input="onProductSearchInput"
                    @focus="onSearchFocus"
                    type="text"
                    placeholder="🔍 Search footwear by Name, Article # (e.g. RP-805), SKU, or Brand (e.g. RUPSA)..."
                    class="w-full pl-10 pr-10 py-3 bg-slate-50 border-2 border-slate-200 rounded-2xl font-bold text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-red-500 focus:bg-white transition-all shadow-xs"
                  />
                  <span class="absolute left-3.5 top-3.5 text-slate-400 text-sm">👟</span>
                  <span v-if="searchLoading" class="absolute right-3.5 top-3.5 text-red-600 animate-spin text-sm">⌛</span>
                </div>

                <!-- Search Results Dropdown -->
                <div
                  v-if="showSearchDropdown"
                  class="absolute left-0 right-0 top-full mt-2 bg-white rounded-2xl border border-slate-200 shadow-2xl z-40 overflow-hidden max-h-72 overflow-y-auto divide-y divide-slate-100"
                >
                  <div v-if="searchLoading" class="p-4 text-center text-xs text-slate-500 font-bold">
                    🔍 Searching product matrix...
                  </div>
                  <div v-else-if="searchError" class="p-4 text-center text-xs text-red-600 font-bold">
                    {{ searchError }}
                  </div>
                  <div v-else-if="searchResultProducts.length === 0" class="p-4 text-center text-xs text-slate-500 font-bold">
                    No footwear products found matching "{{ productSearchQuery }}".
                  </div>
                  <div
                    v-else
                    v-for="p in searchResultProducts"
                    :key="p.id"
                    @click="addFootwearProductToMatrix(p)"
                    class="p-3 hover:bg-red-50/70 cursor-pointer transition-colors flex items-center justify-between text-xs"
                  >
                    <div class="flex items-center gap-3">
                      <div class="w-9 h-9 shrink-0 bg-slate-100 rounded-lg border border-slate-200 overflow-hidden flex items-center justify-center relative">
                        <img
                          v-if="p.image_url || p.product_image || p.primary_image_url"
                          :src="p.image_url || p.product_image || p.primary_image_url"
                          @error="$event.target.style.display='none'"
                          class="w-9 h-9 object-cover"
                        />
                        <span v-else class="text-xs">👟</span>
                      </div>
                      <div>
                        <div class="font-black text-slate-900">
                          <span class="font-mono text-red-600">[{{ p.article_number }}]</span> {{ p.product_name }}
                        </div>
                        <div class="text-[11px] text-slate-500 font-medium mt-0.5">
                          Brand: <strong class="text-slate-700">{{ p.brand_name }}</strong> | Category: {{ p.category_name }}
                        </div>
                      </div>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="px-2 py-1 bg-slate-100 text-slate-700 rounded-lg text-[10px] font-bold">
                        {{ countProductSizes(p) }} Size Option(s)
                      </span>
                      <span class="px-2.5 py-1 bg-red-600 hover:bg-red-700 text-white rounded-lg font-black text-[10px] uppercase shadow-xs">
                        + Add Matrix
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- ADDED FOOTWEAR PRODUCTS MATRIX GRID -->
              <div v-if="selectedFootwearProducts.length === 0" class="p-8 text-center bg-slate-50 rounded-2xl border border-dashed border-slate-300 text-xs text-slate-500 font-bold">
                No footwear product added yet. Use the search bar above to select a product by Article # or Brand.
              </div>

              <div v-for="(prod, pIdx) in selectedFootwearProducts" :key="pIdx" class="bg-white border-2 border-slate-200 rounded-2xl overflow-hidden shadow-xs space-y-3">
                <!-- Product Header Bar -->
                <div class="bg-slate-900 text-white p-3 sm:p-4 flex flex-wrap items-center justify-between gap-3">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 shrink-0 bg-slate-800 rounded-xl border border-slate-700 overflow-hidden flex items-center justify-center relative">
                      <img
                        v-if="prod.image_url || prod.product_image || prod.primary_image_url"
                        :src="prod.image_url || prod.product_image || prod.primary_image_url"
                        @error="$event.target.style.display='none'"
                        class="w-10 h-10 object-cover"
                      />
                      <span v-else class="text-sm">👟</span>
                    </div>
                    <div>
                      <span class="px-2 py-0.5 bg-red-600 text-white font-mono font-black rounded text-[11px]">
                        ARTICLE: {{ prod.article_number }}
                      </span>
                      <span class="font-black text-sm ml-2">{{ prod.product_name }}</span>
                    </div>
                  </div>

                  <div class="flex items-center gap-2">
                    <button @click="promptBulkFillRate(prod)" type="button" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-amber-400 text-[10px] font-black rounded-lg border border-slate-700 transition-colors cursor-pointer">
                      ⚡ Fill Rate
                    </button>
                    <button @click="promptBulkFillQty(prod)" type="button" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-emerald-400 text-[10px] font-black rounded-lg border border-slate-700 transition-colors cursor-pointer">
                      ⚡ Fill Qty
                    </button>
                    <button @click="removeFootwearProduct(pIdx)" type="button" class="px-2.5 py-1 bg-red-600/80 hover:bg-red-600 text-white text-[11px] font-bold rounded-lg transition-colors cursor-pointer">
                      🗑️ Remove
                    </button>
                  </div>
                </div>

                <!-- Size-Wise Matrix Table -->
                <div class="px-3 pb-3 overflow-x-auto">
                  <table class="w-full text-left text-xs border-collapse min-w-[550px]">
                    <thead>
                      <tr class="bg-slate-100 text-slate-700 font-black uppercase text-[10px] border-b border-slate-200">
                        <th class="p-2.5">Footwear Size</th>
                        <th class="p-2.5 text-center">SKU Code</th>
                        <th class="p-2.5 text-center">Current Stock</th>
                        <th class="p-2.5 text-center">Purchase Qty</th>
                        <th class="p-2.5 text-center">Purchase Rate (₹)</th>
                        <th class="p-2.5 text-right font-mono">Line Total</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                      <template v-for="v in prod.variants" :key="v.variant_id">
                        <tr v-for="s in v.sizes" :key="s.product_variant_size_id" class="hover:bg-slate-50/80 transition-colors">
                          <td class="p-2 font-black text-slate-900">
                            <span class="px-2.5 py-1 bg-slate-900 text-white font-mono rounded-lg text-[11px]">
                              {{ s.size_display }}
                            </span>
                            <span class="text-[10px] text-slate-500 ml-1.5 font-normal">({{ v.color_name }})</span>
                          </td>
                          <td class="p-2 text-center font-mono text-[11px] font-bold text-slate-600">
                            {{ s.sku }}
                          </td>
                          <td class="p-2 text-center font-bold text-slate-700">
                            <span :class="s.current_stock <= 0 ? 'text-red-600 font-black' : 'text-slate-800'">
                              {{ s.current_stock }} pcs
                            </span>
                          </td>
                          <td class="p-2 text-center">
                            <input
                              type="number"
                              v-model.number="s.quantity_ordered"
                              min="0"
                              placeholder="Qty"
                              class="w-20 bg-slate-50 border border-slate-300 rounded-xl px-2 py-1 text-center font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-red-500/20"
                            />
                          </td>
                          <td class="p-2 text-center">
                            <input
                              type="number"
                              step="0.01"
                              v-model.number="s.cost_price"
                              min="0"
                              placeholder="Rate ₹"
                              class="w-24 bg-slate-50 border border-slate-300 rounded-xl px-2 py-1 text-center font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-red-500/20"
                            />
                          </td>
                          <td class="p-2 text-right font-mono font-black text-slate-900">
                            ₹{{ formatCurrency((s.quantity_ordered || 0) * (s.cost_price || 0)) }}
                          </td>
                        </tr>
                      </template>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- TOTALS & SUMMARY BAR -->
            <div class="p-4 bg-slate-900 text-white rounded-2xl flex flex-wrap items-center justify-between text-xs font-mono font-bold gap-3">
              <div>
                TOTAL ORDER QUANTITY: <span class="text-amber-400 font-black text-sm">{{ totalSizeQuantity }}</span> pairs
              </div>
              <div class="text-right">
                <div>COMPUTED SUBTOTAL: ₹{{ formatCurrency(computedSubtotal) }}</div>
                <div class="text-sm text-red-400 font-black">GRAND TOTAL: ₹{{ formatCurrency(computedGrandTotal) }}</div>
              </div>
            </div>
          </div>

          <!-- ACTIONS FOOTER -->
          <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-4 sm:px-6 py-3 sm:py-4 shrink-0 bg-slate-50 sm:rounded-b-3xl z-10">
            <button @click="showCreateModal = false" type="button" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 rounded-xl font-bold text-xs cursor-pointer">Cancel</button>
            <button
              @click="submitCreatePo"
              :disabled="saving"
              type="button"
              class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md shadow-red-600/20 uppercase tracking-wider cursor-pointer"
            >
              {{ saving ? 'Issuing Purchase Order...' : 'Issue Purchase Order' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ONSCREEN PURCHASE ORDER DETAILS MODAL -->
    <Teleport to="body">
      <div v-if="showDetailModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-[400] flex items-center justify-center p-0 sm:p-4 overflow-hidden font-sans print:hidden">
        <div class="bg-white rounded-none sm:rounded-3xl max-w-5xl w-full p-0 shadow-2xl border-0 sm:border sm:border-slate-200 flex flex-col h-[100dvh] sm:h-auto sm:max-h-[90dvh] overflow-hidden my-0 sm:my-auto">
          <!-- Modal Header -->
          <div class="flex items-center justify-between border-b border-slate-200 px-4 sm:px-6 py-3.5 sm:py-4 shrink-0 bg-white z-10">
            <div class="flex items-center gap-3">
              <span class="p-2 bg-red-100 text-red-600 rounded-xl text-lg">📝</span>
              <div>
                <div class="flex items-center gap-2">
                  <h2 class="text-base sm:text-lg font-black text-slate-900">Purchase Order Details</h2>
                  <span v-if="selectedPoDetail" class="font-mono font-black text-red-600 text-sm">({{ selectedPoDetail.po_number }})</span>
                </div>
                <p class="text-[11px] sm:text-xs text-slate-500 font-medium">Size-wise footwear procurement document</p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <button @click="printPo" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl font-bold text-xs flex items-center gap-1.5 transition-colors cursor-pointer">
                <span>🖨️</span>
                <span class="hidden sm:inline">Print A4</span>
              </button>
              <button @click="showDetailModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-lg p-1 cursor-pointer">✕</button>
            </div>
          </div>

          <!-- Modal Body -->
          <div class="p-4 sm:p-6 overflow-y-auto flex-1 space-y-6 text-xs text-slate-800">
            <!-- Loading State -->
            <div v-if="loadingDetail" class="py-16 text-center space-y-3">
              <div class="inline-block animate-spin text-3xl text-red-600">⌛</div>
              <div class="font-bold text-slate-600">Loading Purchase Order details...</div>
            </div>

            <!-- Error State -->
            <div v-else-if="detailError" class="p-4 bg-red-50 border border-red-200 rounded-2xl space-y-2">
              <div class="font-black text-red-900">⚠️ {{ detailError }}</div>
              <button @click="retryDetailLoad" class="px-4 py-2 bg-red-600 text-white rounded-xl font-bold text-xs shadow-xs">Retry Loading</button>
            </div>

            <!-- Details Content -->
            <div v-else-if="selectedPoDetail" class="space-y-6">
              <!-- TOP METADATA CARDS -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Supplier Card -->
                <div class="bg-red-50/50 p-4 rounded-2xl border border-red-200/80 space-y-2">
                  <div class="flex items-center justify-between border-b border-red-200/60 pb-2">
                    <span class="font-black text-xs text-red-900 uppercase tracking-wider">🏢 Supplier Details</span>
                    <span v-if="selectedPoDetail.supplier_code" class="font-mono font-bold text-[10px] text-red-700 bg-red-100 px-2 py-0.5 rounded-full">{{ selectedPoDetail.supplier_code }}</span>
                  </div>
                  <div class="font-black text-sm text-slate-900">{{ selectedPoDetail.supplier_name }}</div>
                  <div v-if="selectedPoDetail.supplier_company" class="font-bold text-slate-700">Company: {{ selectedPoDetail.supplier_company }}</div>
                  <div class="grid grid-cols-2 gap-2 text-[11px] text-slate-600 pt-1">
                    <div>Phone: <strong class="text-slate-900 font-mono">{{ selectedPoDetail.supplier_phone || 'N/A' }}</strong></div>
                    <div>GSTIN: <strong class="text-slate-900 font-mono">{{ selectedPoDetail.supplier_gstin || 'N/A' }}</strong></div>
                  </div>
                  <div v-if="selectedPoDetail.supplier_address" class="text-[11px] text-slate-600 border-t border-red-200/50 pt-1">
                    Address: {{ selectedPoDetail.supplier_address }}<template v-if="selectedPoDetail.supplier_city">, {{ selectedPoDetail.supplier_city }}</template><template v-if="selectedPoDetail.supplier_state">, {{ selectedPoDetail.supplier_state }}</template>
                  </div>
                </div>

                <!-- PO Summary Card -->
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2">
                  <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                    <span class="font-black text-xs text-slate-800 uppercase tracking-wider">📋 Order Status & Dates</span>
                    <span :class="getStatusBadgeClass(selectedPoDetail.status)">
                      {{ formatStatusLabel(selectedPoDetail.status) }}
                    </span>
                  </div>
                  <div class="grid grid-cols-2 gap-2 text-xs">
                    <div>
                      <span class="text-slate-500 font-medium">Order Date:</span>
                      <div class="font-mono font-bold text-slate-900">{{ selectedPoDetail.order_date || 'N/A' }}</div>
                    </div>
                    <div>
                      <span class="text-slate-500 font-medium">Received Date:</span>
                      <div class="font-mono font-bold text-slate-900">{{ selectedPoDetail.received_date || 'Pending Confirmation' }}</div>
                    </div>
                    <div>
                      <span class="text-slate-500 font-medium">Vendor Invoice / Ref #:</span>
                      <div class="font-mono font-bold text-slate-900">{{ selectedPoDetail.supplier_invoice_number || 'N/A' }}</div>
                    </div>
                    <div>
                      <span class="text-slate-500 font-medium">Issued Store:</span>
                      <div class="font-bold text-slate-900">{{ selectedPoDetail.store_name || 'Main Outlet (STR-001)' }}</div>
                    </div>
                  </div>
                  <div v-if="selectedPoDetail.creator_name" class="text-[11px] text-slate-500 border-t border-slate-200 pt-1">
                    Created By: <strong class="text-slate-800">{{ selectedPoDetail.creator_name }}</strong>
                  </div>
                </div>
              </div>

              <!-- ITEMS TABLE WITH PRODUCT IMAGES -->
              <div class="space-y-3">
                <div class="flex items-center justify-between">
                  <h3 class="font-black text-xs text-slate-900 uppercase tracking-wider">
                    Footwear Items & Size-Wise Breakdown ({{ selectedPoDetail.items?.length || 0 }} Line Items)
                  </h3>
                  <span class="font-mono font-bold text-xs text-slate-600">Total Units: {{ totalPoDetailUnits }} pcs</span>
                </div>

                <div class="overflow-x-auto border border-slate-200 rounded-2xl overflow-hidden shadow-xs">
                  <table class="w-full text-left text-xs min-w-[650px]">
                    <thead>
                      <tr class="bg-slate-900 text-white font-extrabold uppercase text-[10px]">
                        <th class="py-3 px-3 text-center w-12">Image</th>
                        <th class="py-3 px-3">Product Name & Article</th>
                        <th class="py-3 px-3">Brand & Color</th>
                        <th class="py-3 px-3 text-center">Footwear Size</th>
                        <th class="py-3 px-3 text-center font-mono">SKU</th>
                        <th class="py-3 px-3 text-center">Ordered</th>
                        <th class="py-3 px-3 text-center">Received</th>
                        <th class="py-3 px-3 text-right">Purchase Rate</th>
                        <th class="py-3 px-3 text-right font-mono">Line Total</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                      <tr v-for="item in selectedPoDetail.items" :key="item.id" class="hover:bg-slate-50">
                        <td class="py-2.5 px-3 text-center">
                          <div class="w-10 h-10 mx-auto bg-slate-100 rounded-xl border border-slate-200 overflow-hidden flex items-center justify-center relative">
                            <img
                              v-if="item.image_url || item.product_image || item.primary_image_url"
                              :src="item.image_url || item.product_image || item.primary_image_url"
                              @error="$event.target.style.display='none'"
                              class="w-10 h-10 object-cover"
                            />
                            <span v-else class="text-sm">👟</span>
                          </div>
                        </td>
                        <td class="py-2.5 px-3">
                          <div class="font-black text-slate-900">{{ item.product_name }}</div>
                          <div class="font-mono text-[11px] text-red-600 font-bold">Article: {{ item.article_number }}</div>
                        </td>
                        <td class="py-2.5 px-3">
                          <div class="font-bold text-slate-800">{{ item.brand_name || 'Generic' }}</div>
                          <div class="text-[10px] text-slate-500">Color: {{ item.color_name || 'Std' }}</div>
                        </td>
                        <td class="py-2.5 px-3 text-center">
                          <span class="px-2.5 py-1 bg-slate-900 text-white font-mono rounded-lg text-[11px] font-black">
                            {{ item.size_display || item.size_number }}
                          </span>
                        </td>
                        <td class="py-2.5 px-3 text-center font-mono text-[11px] font-bold text-slate-600">
                          {{ item.sku }}
                        </td>
                        <td class="py-2.5 px-3 text-center font-black text-slate-900">
                          {{ item.quantity_ordered }} pcs
                        </td>
                        <td class="py-2.5 px-3 text-center font-bold text-emerald-700">
                          {{ item.quantity_received || 0 }} pcs
                        </td>
                        <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-800">
                          ₹{{ formatCurrency(item.cost_price) }}
                        </td>
                        <td class="py-2.5 px-3 text-right font-mono font-black text-slate-900">
                          ₹{{ formatCurrency(item.total_cost || (item.quantity_ordered * item.cost_price)) }}
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- FINANCIAL BREAKDOWN & NOTES -->
              <div class="bg-slate-900 text-white p-5 rounded-2xl flex flex-wrap items-center justify-between gap-4 text-xs font-mono">
                <div class="space-y-1.5 max-w-md">
                  <div class="text-red-400 font-black uppercase text-[11px] tracking-wider font-sans">Special Notes & Legal Disclaimer</div>
                  <p class="text-[11px] font-sans text-slate-300 leading-relaxed">
                    {{ selectedPoDetail.notes || 'This Purchase Order does not alter physical stock until Goods Receive Note (GRN) is confirmed.' }}
                  </p>
                </div>
                <div class="text-right space-y-1 min-w-[200px]">
                  <div class="flex justify-between gap-4 text-slate-300">
                    <span>SUBTOTAL:</span>
                    <span class="font-bold">₹{{ formatCurrency(selectedPoDetail.subtotal) }}</span>
                  </div>
                  <div class="flex justify-between gap-4 text-slate-300">
                    <span>TAX (GST):</span>
                    <span class="font-bold">+₹{{ formatCurrency(selectedPoDetail.tax_amount) }}</span>
                  </div>
                  <div class="flex justify-between gap-4 text-slate-300">
                    <span>DISCOUNT:</span>
                    <span class="font-bold">-₹{{ formatCurrency(selectedPoDetail.discount_amount) }}</span>
                  </div>
                  <div class="flex justify-between gap-4 text-base font-black text-amber-400 border-t border-slate-800 pt-1.5">
                    <span>GRAND TOTAL:</span>
                    <span>₹{{ formatCurrency(selectedPoDetail.grand_total) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="flex items-center justify-between border-t border-slate-200 px-4 sm:px-6 py-3 sm:py-4 shrink-0 bg-slate-50 sm:rounded-b-3xl z-10">
            <div class="flex items-center gap-2">
              <button
                v-if="selectedPoDetail && selectedPoDetail.status !== 'fully_received' && selectedPoDetail.status !== 'received' && selectedPoDetail.status !== 'cancelled'"
                @click="navigateToReceiveGoods"
                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-black text-xs shadow-md shadow-emerald-600/20 flex items-center gap-1.5 cursor-pointer"
              >
                <span>📥</span>
                <span>Receive Goods Against PO</span>
              </button>
            </div>
            <div class="flex items-center gap-3">
              <button @click="printPo" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 rounded-xl font-bold text-xs cursor-pointer flex items-center gap-1.5">
                <span>🖨️</span>
                <span>Print PO</span>
              </button>
              <button @click="showDetailModal = false" class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold text-xs cursor-pointer">
                Close
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- DEDICATED PRINTABLE PURCHASE ORDER CONTAINER (Teleported to Body for A4 Print) -->
    <Teleport to="body">
      <div v-if="selectedPoDetail" id="po-print-root" class="hidden print:block font-sans text-black bg-white p-6 leading-tight">
        <!-- Header -->
        <div class="border-b-2 border-black pb-4 mb-4 flex justify-between items-start">
          <div>
            <div class="text-2xl font-black uppercase tracking-tight">RUPSA PADUKALAYA</div>
            <div class="text-xs font-bold italic text-slate-700">Quality Footwear for Every Step</div>
            <div class="text-xs font-medium mt-1">
              Store: <strong>{{ selectedPoDetail.store_name || 'RUPSA PADUKALAYA - Main Outlet' }} (STR-001)</strong>
            </div>
          </div>
          <div class="text-right">
            <div class="text-xl font-black text-slate-900 uppercase tracking-wider">PURCHASE ORDER</div>
            <div class="text-sm font-mono font-black text-red-700">PO #: {{ selectedPoDetail.po_number }}</div>
            <div class="text-xs font-bold mt-0.5">Order Date: {{ selectedPoDetail.order_date || 'N/A' }}</div>
            <div class="text-xs font-bold uppercase">Status: {{ selectedPoDetail.status }}</div>
          </div>
        </div>

        <!-- Supplier & Order Metadata Grid -->
        <div class="grid grid-cols-2 gap-4 border border-black p-3 text-xs mb-4">
          <div>
            <div class="font-black uppercase border-b border-black pb-1 mb-1 tracking-wider text-[11px]">
              🏢 SUPPLIER DETAILS
            </div>
            <div class="font-black text-sm text-slate-900">{{ selectedPoDetail.supplier_name }}</div>
            <div v-if="selectedPoDetail.supplier_company" class="font-bold text-slate-800">
              Company: {{ selectedPoDetail.supplier_company }}
            </div>
            <div>Phone: <strong>{{ selectedPoDetail.supplier_phone || 'N/A' }}</strong></div>
            <div>GSTIN: <strong>{{ selectedPoDetail.supplier_gstin || 'N/A' }}</strong></div>
            <div v-if="selectedPoDetail.supplier_address" class="text-slate-800">
              Address: {{ selectedPoDetail.supplier_address }}, {{ selectedPoDetail.supplier_city || '' }} {{ selectedPoDetail.supplier_state || '' }}
            </div>
          </div>
          <div>
            <div class="font-black uppercase border-b border-black pb-1 mb-1 tracking-wider text-[11px]">
              📋 ORDER SUMMARY
            </div>
            <div>Vendor Ref #: <strong>{{ selectedPoDetail.supplier_invoice_number || 'N/A' }}</strong></div>
            <div>Issued Store: <strong>{{ selectedPoDetail.store_name || 'STR-001 Main Outlet' }}</strong></div>
            <div>Expected Receive: <strong>{{ selectedPoDetail.received_date || 'Pending Confirmation' }}</strong></div>
            <div>Created By: <strong>{{ selectedPoDetail.creator_name || 'Super Admin' }}</strong></div>
            <div>Printed On: <strong>{{ new Date().toLocaleString() }}</strong></div>
          </div>
        </div>

        <!-- Size-Wise Footwear Matrix Table -->
        <table class="w-full text-left text-xs border-collapse border border-black mb-4">
          <thead>
            <tr class="bg-slate-200 text-black font-black uppercase border-b border-black text-[10px]">
              <th class="p-2 border-r border-black text-center w-8">SL</th>
              <th class="p-2 border-r border-black">Product Name</th>
              <th class="p-2 border-r border-black">Article #</th>
              <th class="p-2 border-r border-black">Brand</th>
              <th class="p-2 border-r border-black">Color</th>
              <th class="p-2 border-r border-black text-center">Footwear Size</th>
              <th class="p-2 border-r border-black font-mono">SKU</th>
              <th class="p-2 border-r border-black text-center">Ordered Qty</th>
              <th class="p-2 border-r border-black text-right">Purchase Rate (₹)</th>
              <th class="p-2 text-right font-mono">Line Total (₹)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, idx) in selectedPoDetail.items" :key="item.id" class="border-b border-black">
              <td class="p-2 border-r border-black text-center font-bold">{{ idx + 1 }}</td>
              <td class="p-2 border-r border-black font-bold">{{ item.product_name }}</td>
              <td class="p-2 border-r border-black font-mono font-bold">{{ item.article_number }}</td>
              <td class="p-2 border-r border-black">{{ item.brand_name || 'RUPSA' }}</td>
              <td class="p-2 border-r border-black">{{ item.color_name || 'Std' }}</td>
              <td class="p-2 border-r border-black text-center font-mono font-black">
                {{ item.size_display || ('IND ' + item.size_number) }}
              </td>
              <td class="p-2 border-r border-black font-mono text-[10px]">{{ item.sku }}</td>
              <td class="p-2 border-r border-black text-center font-black">{{ item.quantity_ordered }} pcs</td>
              <td class="p-2 border-r border-black text-right font-mono">₹{{ formatCurrency(item.cost_price) }}</td>
              <td class="p-2 text-right font-mono font-black">₹{{ formatCurrency(item.total_cost || (item.quantity_ordered * item.cost_price)) }}</td>
            </tr>
          </tbody>
        </table>

        <!-- Financial Summary Bar & Mandatory Legal Notice -->
        <div class="flex justify-between items-start text-xs border border-black p-3 mb-6">
          <div class="max-w-md space-y-1">
            <div class="font-black uppercase tracking-wider text-[11px]">Notice & Terms:</div>
            <div class="text-[10px] font-bold italic text-slate-900 border-l-2 border-black pl-2 py-0.5">
              "This Purchase Order does not increase physical stock. Stock is updated only after Goods Receive confirmation."
            </div>
            <div v-if="selectedPoDetail.notes" class="text-[10px] mt-2">
              <strong>Notes:</strong> {{ selectedPoDetail.notes }}
            </div>
          </div>
          <div class="w-64 text-right space-y-1 font-mono">
            <div>TOTAL QUANTITY: <strong class="text-sm font-black">{{ totalPoDetailUnits }} pcs</strong></div>
            <div>SUBTOTAL: ₹{{ formatCurrency(selectedPoDetail.subtotal) }}</div>
            <div>TAX (GST): +₹{{ formatCurrency(selectedPoDetail.tax_amount) }}</div>
            <div>DISCOUNT: -₹{{ formatCurrency(selectedPoDetail.discount_amount) }}</div>
            <div class="text-sm font-black border-t border-black pt-1">
              GRAND TOTAL: ₹{{ formatCurrency(selectedPoDetail.grand_total) }}
            </div>
          </div>
        </div>

        <!-- Signature Areas -->
        <div class="grid grid-cols-2 gap-8 pt-10 text-xs font-bold text-center">
          <div>
            <div class="border-t border-black pt-1">Supplier Acknowledgement & Signature</div>
          </div>
          <div>
            <div class="border-t border-black pt-1">Authorized Signature / RUPSA PADUKALAYA</div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../services/api';
import MobileListCard from '../../components/ui/MobileListCard.vue';

const router = useRouter();

const loading = ref(false);
const saving = ref(false);
const showCreateModal = ref(false);

const showDetailModal = ref(false);
const loadingDetail = ref(false);
const detailError = ref('');
const selectedPoDetail = ref(null);
const currentViewingPoNumber = ref('');

watch([showCreateModal, showDetailModal], ([createOpen, detailOpen]) => {
  if (createOpen || detailOpen) {
    document.body.style.overflow = 'hidden';
  } else {
    document.body.style.overflow = '';
  }
});

const ordersList = ref([]);
const suppliersList = ref([]);
const selectedSupplier = ref(null);
const supplierError = ref('');

const productSearchQuery = ref('');
const searchLoading = ref(false);
const searchError = ref('');
const showSearchDropdown = ref(false);
const searchResultProducts = ref([]);
const selectedFootwearProducts = ref([]);

const filters = reactive({
  search: '',
  status: '',
});

const pagination = reactive({
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1,
});

const form = reactive({
  supplier_id: '',
  supplier_invoice_number: '',
  order_date: new Date().toISOString().substring(0, 10),
  tax_amount: 0,
  discount_amount: 0,
  notes: '',
});

const totalSizeQuantity = computed(() => {
  let count = 0;
  selectedFootwearProducts.value.forEach(p => {
    p.variants?.forEach(v => {
      v.sizes?.forEach(s => {
        count += Number(s.quantity_ordered || 0);
      });
    });
  });
  return count;
});

const totalPoDetailUnits = computed(() => {
  if (!selectedPoDetail.value || !selectedPoDetail.value.items) return 0;
  return selectedPoDetail.value.items.reduce((sum, item) => sum + Number(item.quantity_ordered || 0), 0);
});

const computedSubtotal = computed(() => {
  let sub = 0;
  selectedFootwearProducts.value.forEach(p => {
    p.variants?.forEach(v => {
      v.sizes?.forEach(s => {
        sub += Number(s.quantity_ordered || 0) * Number(s.cost_price || 0);
      });
    });
  });
  return sub;
});

const computedGrandTotal = computed(() => {
  return Math.max(0, computedSubtotal.value + (form.tax_amount || 0) - (form.discount_amount || 0));
});

let debounceTimer = null;
function debouncedFetch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => fetchOrders(1), 300);
}

let searchDebounce = null;
function onProductSearchInput() {
  clearTimeout(searchDebounce);
  const q = productSearchQuery.value.trim();
  if (!q) {
    searchResultProducts.value = [];
    showSearchDropdown.value = false;
    searchError.value = '';
    return;
  }

  showSearchDropdown.value = true;
  searchLoading.value = true;
  searchError.value = '';

  searchDebounce = setTimeout(async () => {
    try {
      const res = await api.get('/products/size-matrix-search', {
        params: { q },
      });

      const list = Array.isArray(res)
        ? res
        : (Array.isArray(res?.data) ? res.data : (Array.isArray(res?.data?.items) ? res.data.items : []));

      searchResultProducts.value = list;
    } catch (e) {
      console.error('Failed to search product matrix:', e);
      searchError.value = e.message || 'Failed to search footwear products. Please try again.';
      searchResultProducts.value = [];
    } finally {
      searchLoading.value = false;
    }
  }, 300);
}

function onSearchFocus() {
  if (productSearchQuery.value.trim()) {
    showSearchDropdown.value = true;
  }
}

function countProductSizes(p) {
  let count = 0;
  p.variants?.forEach(v => {
    count += v.sizes?.length || 0;
  });
  return count;
}

function addFootwearProductToMatrix(prod) {
  if (selectedFootwearProducts.value.some(p => p.id === prod.id)) {
    alert(`"${prod.product_name}" is already added to the size matrix below.`);
    searchResultProducts.value = [];
    showSearchDropdown.value = false;
    productSearchQuery.value = '';
    return;
  }

  const cloned = JSON.parse(JSON.stringify(prod));
  cloned.variants?.forEach(v => {
    v.sizes?.forEach(s => {
      s.quantity_ordered = 0;
      s.cost_price = s.cost_price || 0;
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
  const input = prompt(`Enter Purchase Rate (₹) to apply to all sizes of "${prod.product_name}":`, '500');
  if (input !== null && !isNaN(Number(input))) {
    const rate = Math.max(0, Number(input));
    prod.variants?.forEach(v => {
      v.sizes?.forEach(s => {
        s.cost_price = rate;
      });
    });
  }
}

function promptBulkFillQty(prod) {
  const input = prompt(`Enter Purchase Quantity to apply to all sizes of "${prod.product_name}":`, '10');
  if (input !== null && !isNaN(Number(input))) {
    const qty = Math.max(0, parseInt(input, 10));
    prod.variants?.forEach(v => {
      v.sizes?.forEach(s => {
        s.quantity_ordered = qty;
      });
    });
  }
}

async function fetchOrders(page = 1) {
  loading.value = true;
  try {
    const res = await api.get('/purchases/orders', {
      params: {
        page,
        per_page: pagination.per_page,
        search: filters.search || undefined,
        status: filters.status || undefined,
      },
    });
    const payload = res?.data || res;
    ordersList.value = payload.items || [];
    if (payload.pagination) {
      Object.assign(pagination, payload.pagination);
    }
  } catch (err) {
    console.error('Failed to load purchase orders:', err);
  } finally {
    loading.value = false;
  }
}

async function viewPoDetails(po) {
  if (!po || !po.id) return;

  currentViewingPoNumber.value = po.po_number || '';
  showDetailModal.value = true;
  loadingDetail.value = true;
  detailError.value = '';
  selectedPoDetail.value = null;

  try {
    const res = await api.get(`/purchases/orders/${po.id}`);
    selectedPoDetail.value = res?.data || res;
  } catch (err) {
    console.error('Failed to retrieve PO details:', err);
    detailError.value = err.message || err.response?.data?.message || 'Purchase Order details could not be retrieved.';
  } finally {
    loadingDetail.value = false;
  }
}

async function retryDetailLoad() {
  if (!selectedPoDetail.value && currentViewingPoNumber.value) {
    const po = ordersList.value.find(p => p.po_number === currentViewingPoNumber.value);
    if (po) {
      await viewPoDetails(po);
    }
  }
}

function navigateToReceiveGoods() {
  if (!selectedPoDetail.value) return;
  showDetailModal.value = false;
  router.push({
    path: '/admin/grn',
    query: { po_id: selectedPoDetail.value.id },
  });
}

function printPo() {
  window.print();
}

async function loadMasterData() {
  try {
    const [sRes] = await Promise.allSettled([
      api.get('/suppliers', { params: { per_page: 100 } }),
    ]);

    if (sRes.status === 'fulfilled') {
      const payload = sRes.value?.data || sRes.value;
      suppliersList.value = payload.items || payload.data?.items || payload.data || [];
      supplierError.value = '';
    } else {
      console.warn('Unable to load supplier directory:', sRes.reason);
      supplierError.value = 'Unable to load supplier directory. Please check user permissions.';
    }
  } catch (e) {
    console.error('Failed to load supplier master list:', e);
  }
}

function openCreateModal() {
  form.supplier_id = '';
  form.supplier_invoice_number = '';
  form.order_date = new Date().toISOString().substring(0, 10);
  form.tax_amount = 0;
  form.discount_amount = 0;
  form.notes = '';
  selectedSupplier.value = null;
  selectedFootwearProducts.value = [];
  productSearchQuery.value = '';
  searchResultProducts.value = [];
  showSearchDropdown.value = false;
  searchError.value = '';
  showCreateModal.value = true;
}

function onSupplierSelect() {
  selectedSupplier.value = suppliersList.value.find(s => s.id === form.supplier_id) || null;
}

async function submitCreatePo() {
  if (!form.supplier_id) {
    alert('Supplier is required for every purchase transaction.');
    return;
  }

  const items = [];
  selectedFootwearProducts.value.forEach(p => {
    p.variants?.forEach(v => {
      v.sizes?.forEach(s => {
        if (Number(s.quantity_ordered) > 0) {
          items.push({
            product_variant_size_id: s.product_variant_size_id,
            quantity_ordered: Number(s.quantity_ordered),
            cost_price: Number(s.cost_price || 0),
          });
        }
      });
    });
  });

  if (items.length === 0) {
    alert('Please enter at least one size-wise purchase quantity greater than 0.');
    return;
  }

  saving.value = true;
  try {
    await api.post('/purchases/orders', {
      supplier_id: form.supplier_id,
      supplier_invoice_number: form.supplier_invoice_number,
      order_date: form.order_date,
      tax_amount: form.tax_amount,
      discount_amount: form.discount_amount,
      notes: form.notes,
      items,
    });
    showCreateModal.value = false;
    alert('Purchase Order created successfully with size-wise breakdown!');
    fetchOrders(1);
  } catch (err) {
    alert(err.message || err.response?.data?.message || 'Failed to create purchase order.');
  } finally {
    saving.value = false;
  }
}

function formatStatusLabel(st) {
  if (!st) return 'Draft';
  const map = {
    draft: 'Draft',
    sent: 'Sent',
    ordered: 'Ordered',
    partial: 'Partially Received',
    partially_received: 'Partially Received',
    received: 'Fully Received',
    fully_received: 'Fully Received',
    cancelled: 'Cancelled',
  };
  return map[st] || st;
}

function getStatusBadgeClass(st) {
  if (st === 'fully_received' || st === 'received') return 'px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-black';
  if (st === 'partially_received' || st === 'partial') return 'px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-black';
  if (st === 'cancelled') return 'px-2 py-0.5 rounded-full bg-red-100 text-red-800 text-[10px] font-black';
  return 'px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 text-[10px] font-black';
}

function formatCurrency(val) {
  return Number(val || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function handleGlobalClick(e) {
  showSearchDropdown.value = false;
}

onMounted(() => {
  fetchOrders(1);
  loadMasterData();
  document.addEventListener('click', handleGlobalClick);
});

onUnmounted(() => {
  document.removeEventListener('click', handleGlobalClick);
});
</script>
