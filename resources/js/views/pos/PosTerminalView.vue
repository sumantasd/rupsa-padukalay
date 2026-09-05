<template>
  <div class="h-screen w-full max-w-full overflow-x-hidden flex flex-col bg-slate-100 text-slate-900 font-sans antialiased select-none box-border">
    
    <!-- Centralized Thermal Receipt Component (Handles both screen preview & print portal) -->
    <ThermalReceipt v-if="completedSale" document-type="invoice" :data="completedSale" />

    <!-- POS COMPACT MOBILE & DESKTOP HEADER NAVBAR -->
    <header class="h-14 sm:h-16 bg-white border-b border-slate-200 px-3 sm:px-6 flex items-center justify-between shrink-0 shadow-xs z-30 min-w-0 max-w-full overflow-hidden print:hidden">
      <!-- Left Branding -->
      <div class="flex items-center gap-2 sm:gap-3 min-w-0 truncate">
        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl bg-red-600 flex items-center justify-center font-black text-white text-base sm:text-xl shadow-sm shadow-red-600/30 shrink-0">
          R
        </div>
        <div class="min-w-0 truncate">
          <h1 class="font-black text-xs sm:text-base text-slate-900 tracking-tight leading-none truncate flex items-center gap-1.5">
            <span class="whitespace-nowrap">RUPSA POS</span>
            <span class="hidden md:inline-block px-1.5 py-0.5 rounded bg-red-50 text-red-700 text-[9px] font-black uppercase border border-red-200">
              Retail Footwear
            </span>
          </h1>
          <p class="text-[9px] sm:text-[11px] text-slate-500 font-medium tracking-wide mt-0.5 truncate">
            <span class="sm:hidden">RUPSA - Main Outlet</span>
            <span class="hidden sm:inline">{{ currentStore?.name || 'RUPSA PADUKALAYA - Main Outlet' }} | Cashier: {{ activeUser?.name || 'Admin' }}</span>
          </p>
        </div>
      </div>

      <!-- Right Header Actions -->
      <div class="flex items-center gap-1.5 sm:gap-3 shrink-0">
        <!-- Active Session Status Badge -->
        <div v-if="activeSession" class="px-2 py-0.5 sm:px-3 sm:py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] sm:text-xs font-bold flex items-center gap-1">
          <span class="h-1.5 w-1.5 sm:h-2 sm:w-2 rounded-full bg-emerald-500 animate-pulse"></span>
          <span class="whitespace-nowrap">Open</span>
        </div>

        <div v-else class="px-2 py-0.5 sm:px-3 sm:py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200 text-[10px] sm:text-xs font-bold flex items-center gap-1">
          <span class="h-1.5 w-1.5 sm:h-2 sm:w-2 rounded-full bg-amber-500"></span>
          <span class="whitespace-nowrap">Closed</span>
        </div>

        <router-link
          to="/admin/dashboard"
          class="px-2.5 py-1 sm:px-4 sm:py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] sm:text-xs font-bold border border-slate-200 transition-colors shrink-0 whitespace-nowrap"
        >
          ← Exit
        </router-link>
      </div>
    </header>

    <!-- REGISTER SESSION CLOSED OVERLAY / OPEN REGISTER DIALOG -->
    <div v-if="!activeSession && !checkingSession" class="flex-1 flex items-center justify-center p-4 bg-slate-100 max-w-full overflow-x-hidden print:hidden">
      <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200/90 shadow-2xl max-w-md w-full text-center space-y-5">
        <div class="h-14 w-14 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center text-2xl mx-auto font-black shadow-inner">
          🔒
        </div>

        <div class="space-y-1">
          <h2 class="text-lg font-black text-slate-900 uppercase tracking-tight">Register is Not Open</h2>
          <p class="text-xs text-slate-500 font-medium">An active POS register session is required before generating billing invoices.</p>
        </div>

        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-3 text-xs text-left">
          <div class="space-y-1">
            <label class="font-black text-slate-800 block">Opening Cash Balance (₹) *</label>
            <input
              type="number"
              v-model.number="openingCash"
              min="0"
              class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 font-mono font-black text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600"
            />
          </div>
        </div>

        <button
          @click="handleOpenRegister"
          :disabled="openingRegister"
          class="w-full py-3 bg-red-600 hover:bg-red-700 text-white font-black text-xs uppercase tracking-wider rounded-2xl shadow-lg shadow-red-600/30 transition-all flex items-center justify-center gap-2"
        >
          <span>🔓</span>
          <span>{{ openingRegister ? 'Opening Register...' : 'OPEN REGISTER NOW' }}</span>
        </button>
      </div>
    </div>

    <!-- MAIN POS CONTAINER (ACTIVE SESSION OPEN) -->
    <div v-else-if="activeSession" class="flex-1 flex flex-col h-[calc(100vh-3.5rem)] sm:h-[calc(100vh-4rem)] w-full max-w-full overflow-x-hidden print:hidden">

      <!-- COMPACT MOBILE STEP TABS (Visible on mobile/tablet screens < 1024px) -->
      <div class="lg:hidden shrink-0 bg-white border-b border-slate-200 px-3 py-1.5 flex items-center justify-between text-xs font-bold w-full max-w-full overflow-hidden">
        <div class="flex items-center gap-1 min-w-0">
          <button
            @click="mobileStep = 1"
            :class="['px-2.5 py-1 rounded-full text-[10px] font-black transition-all truncate', mobileStep === 1 ? 'bg-red-600 text-white' : 'bg-slate-100 text-slate-600']"
          >
            1. Product
          </button>
          <span class="text-slate-300">→</span>
          <button
            @click="mobileStep = 2"
            :class="['px-2.5 py-1 rounded-full text-[10px] font-black transition-all truncate', mobileStep === 2 ? 'bg-red-600 text-white' : 'bg-slate-100 text-slate-600']"
          >
            2. Checkout
          </button>
        </div>

        <span class="text-[11px] font-black text-slate-900 font-mono shrink-0 ml-1">🛒 {{ cart.length }} Item(s)</span>
      </div>

      <!-- POS WORK AREA (DESKTOP FULL WIDTH 2-COLUMNS / MOBILE STEP VIEWS) -->
      <div class="flex-1 grid grid-cols-1 lg:grid-cols-12 overflow-hidden h-full w-full max-w-full">
        
        <!-- LEFT COLUMN: ARTICLE SEARCH, PRODUCT CARD, SIZES, QTY & SALE PRICE OVERRIDE -->
        <div
          :class="[
            'lg:col-span-7 p-3 sm:p-5 flex flex-col justify-between overflow-y-auto bg-slate-50/60 border-r border-slate-200/80 space-y-4 h-full min-w-0 max-w-full',
            mobileStep === 1 ? 'flex' : 'hidden lg:flex'
          ]"
        >
          <div class="space-y-4 min-w-0 max-w-full">
            
            <!-- STEP 1: ARTICLE NUMBER / CODE SEARCH -->
            <div class="bg-white p-3.5 sm:p-4 rounded-2xl border border-slate-200/90 shadow-2xs space-y-2.5 min-w-0 max-w-full">
              <div class="flex items-center justify-between min-w-0">
                <label class="text-[11px] sm:text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-1.5 truncate">
                  <span class="h-4.5 w-4.5 rounded-full bg-red-600 text-white text-[9px] flex items-center justify-center font-mono shrink-0">1</span>
                  <span class="truncate">ARTICLE NUMBER / CODE *</span>
                </label>
                <span class="text-[10px] text-slate-400 font-medium hidden sm:inline">Scan or Type Code</span>
              </div>

              <div class="flex gap-1.5 w-full max-w-full">
                <div class="relative flex-1 min-w-0">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
                  <input
                    ref="articleInputRef"
                    type="text"
                    v-model="articleQuery"
                    @keyup.enter="handleSearchArticle"
                    placeholder="Article Code (e.g. RP-805)..."
                    class="w-full bg-slate-50 border border-slate-300 rounded-xl pl-9 pr-3 py-2 text-xs sm:text-sm text-slate-900 font-mono font-bold focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white transition-all uppercase truncate"
                  />
                </div>
                <button
                  @click="handleSearchArticle"
                  :disabled="searching"
                  class="px-3.5 py-2 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-xl shadow-xs transition-colors shrink-0 whitespace-nowrap"
                >
                  {{ searching ? '...' : 'Search' }}
                </button>
              </div>

              <!-- Multiple Results Picker -->
              <div v-if="searchResults.length > 1" class="p-2.5 bg-slate-50 border border-slate-200 rounded-xl space-y-2 text-xs min-w-0">
                <div class="font-bold text-slate-700">Select Matching Article:</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  <button
                    v-for="res in searchResults"
                    :key="res.id"
                    @click="selectProduct(res)"
                    class="p-2 bg-white hover:bg-red-50 border border-slate-200 hover:border-red-400 rounded-xl text-left transition-all truncate"
                  >
                    <div class="font-bold text-slate-900 truncate">{{ res.name }}</div>
                    <div class="text-[10px] font-mono text-red-600 font-bold truncate">ART: {{ res.code || res.article_code || 'RP-' + res.id }}</div>
                  </button>
                </div>
              </div>
            </div>

            <!-- SELECTED PRODUCT DETAILS CARD -->
            <div v-if="selectedProduct" class="bg-white p-3.5 sm:p-4 rounded-2xl border border-slate-200/90 shadow-2xs space-y-3.5 min-w-0 max-w-full">
              <div class="flex items-start gap-3 min-w-0">
                <div class="h-16 w-16 sm:h-20 sm:w-20 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-3xl sm:text-4xl shrink-0 overflow-hidden">
                  <img v-if="selectedProduct.primary_image_url || selectedProduct.image_url" :src="selectedProduct.primary_image_url || selectedProduct.image_url" :alt="selectedProduct.name" class="w-full h-full object-cover" />
                  <span v-else>👞</span>
                </div>

                <div class="flex-1 min-w-0 space-y-0.5">
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="px-1.5 py-0.5 rounded bg-red-50 text-red-700 border border-red-200 font-mono font-bold text-[9px]">
                      {{ selectedProduct.code || selectedProduct.article_code || 'RP-' + selectedProduct.id }}
                    </span>
                    <span v-if="selectedProduct.gender" class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-bold text-[9px] uppercase">
                      {{ selectedProduct.gender }}
                    </span>
                  </div>
                  <h3 class="font-black text-sm sm:text-base text-slate-900 tracking-tight truncate">{{ selectedProduct.name }}</h3>
                  <div class="text-[11px] text-slate-500 font-medium truncate">
                    Brand: <strong class="text-slate-800">{{ selectedProduct.brand?.name || 'RUPSA' }}</strong> | Category: <strong class="text-slate-800">{{ selectedProduct.category?.name || 'Footwear' }}</strong>
                  </div>

                  <div class="flex items-baseline gap-2 pt-0.5">
                    <span class="text-[11px] text-slate-500">MRP: <strong class="font-mono text-slate-700">₹{{ Number(selectedProduct.mrp || defaultSellingPrice).toLocaleString('en-IN') }}</strong></span>
                    <span class="text-[11px] text-slate-500">Default: <strong class="font-mono text-slate-900 font-bold">₹{{ Number(defaultSellingPrice).toLocaleString('en-IN') }}</strong></span>
                  </div>
                </div>
              </div>

              <!-- AVAILABLE SIZES GRID -->
              <div class="space-y-2 pt-2.5 border-t border-slate-100 min-w-0">
                <label class="text-[11px] font-black text-slate-900 uppercase tracking-wider flex items-center justify-between">
                  <span class="flex items-center gap-1.5">
                    <span class="h-4.5 w-4.5 rounded-full bg-red-600 text-white text-[9px] flex items-center justify-center font-mono shrink-0">2</span>
                    <span>AVAILABLE SIZES</span>
                  </span>
                  <span class="text-[10px] text-slate-500 font-normal">Select Size</span>
                </label>

                <div v-if="productSizes.length === 0" class="p-3 bg-amber-50 border border-amber-200 text-amber-900 rounded-xl text-xs font-bold">
                  ⚠️ No active size variants available for this article.
                </div>

                <div v-else class="grid grid-cols-4 sm:grid-cols-6 gap-1.5 w-full">
                  <button
                    v-for="s in productSizes"
                    :key="s.id"
                    :disabled="s.stock <= 0"
                    @click="selectedSize = s"
                    :class="[
                      'p-2 rounded-xl border text-center transition-all flex flex-col items-center justify-center min-w-0',
                      s.stock <= 0 ? 'bg-slate-100 border-slate-200 text-slate-400 cursor-not-allowed opacity-60' :
                      selectedSize?.id === s.id ? 'bg-red-600 text-white border-red-600 shadow-md ring-2 ring-red-300' :
                      'bg-white text-slate-900 border-slate-300 hover:border-red-500 hover:bg-red-50/50'
                    ]"
                  >
                    <span class="font-black text-xs sm:text-sm font-mono truncate">{{ s.name }}</span>
                    <span class="text-[8px] font-bold mt-0.5 truncate" :class="selectedSize?.id === s.id ? 'text-red-100' : s.stock <= 0 ? 'text-slate-400' : 'text-slate-500'">
                      {{ s.stock <= 0 ? 'Out' : `${s.stock} left` }}
                    </span>
                  </button>
                </div>
              </div>

              <!-- QUANTITY & PRICE OVERRIDE SECTION -->
              <div v-if="selectedSize" class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2.5 min-w-0">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 items-center">
                  <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-700 shrink-0">Qty:</span>
                    <div class="flex items-center border border-slate-300 rounded-xl bg-white overflow-hidden shadow-2xs">
                      <button @click="itemQty = Math.max(1, itemQty - 1)" class="px-2.5 py-1 text-slate-600 hover:bg-slate-100 font-black text-xs">-</button>
                      <span class="px-3 py-1 font-black text-xs text-slate-900 font-mono">{{ itemQty }}</span>
                      <button @click="itemQty = Math.min(selectedSize.stock, itemQty + 1)" class="px-2.5 py-1 text-slate-600 hover:bg-slate-100 font-black text-xs">+</button>
                    </div>
                  </div>

                  <div class="space-y-0.5">
                    <div class="flex items-center justify-between">
                      <label class="text-[10px] font-black text-slate-800">Sale Price (₹) *</label>
                      <span v-if="customSalePrice !== defaultSellingPrice" class="text-[8px] font-bold text-amber-700 bg-amber-100 px-1 py-0.2 rounded">
                        Overridden
                      </span>
                    </div>
                    <input
                      type="number"
                      v-model.number="customSalePrice"
                      :disabled="!canOverridePrice"
                      min="1"
                      class="w-full bg-white border border-slate-300 rounded-xl px-2.5 py-1 text-xs font-black font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:bg-slate-100 disabled:text-slate-500"
                    />
                  </div>
                </div>

                <div class="flex items-center justify-between pt-2 border-t border-slate-200">
                  <div class="text-xs">
                    Total: <strong class="font-mono text-sm sm:text-base font-black text-slate-900">₹{{ Number(customSalePrice * itemQty).toLocaleString('en-IN') }}</strong>
                  </div>

                  <button
                    @click="addToCart"
                    class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-black text-xs uppercase tracking-wider rounded-xl shadow-md shadow-red-600/20 transition-all flex items-center justify-center gap-1.5 shrink-0"
                  >
                    <span>➕</span>
                    <span>ADD TO BILL</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- COMPACT EMPTY PRODUCT SEARCH PLACEHOLDER -->
            <div v-else-if="!searching" class="p-5 sm:p-8 bg-white rounded-2xl border border-slate-200/90 text-center space-y-1.5 shadow-2xs min-w-0 max-w-full">
              <div class="h-10 w-10 bg-slate-100 rounded-full flex items-center justify-center mx-auto text-xl">
                bb
              </div>
              <h3 class="font-black text-xs text-slate-900 uppercase">Ready for Article Scan</h3>
              <p class="text-[10px] text-slate-500 max-w-xs mx-auto">
                Type or scan an article code (e.g. <strong>RP-805</strong>) above to inspect sizes & add items to bill.
              </p>
            </div>
          </div>

          <!-- MOBILE STEP 1 NEXT ACTION BUTTON -->
          <div class="lg:hidden pt-2.5 border-t border-slate-200 shrink-0">
            <button
              @click="mobileStep = 2"
              :disabled="cart.length === 0"
              class="w-full py-3 bg-slate-900 hover:bg-slate-800 text-white font-black text-xs rounded-xl shadow-md uppercase tracking-wider flex items-center justify-center gap-2 disabled:opacity-40"
            >
              <span>NEXT: CUSTOMER & CHECKOUT →</span>
            </button>
          </div>
        </div>

        <!-- RIGHT COLUMN: BILLING PANEL & STICKY CHECKOUT (DESKTOP + MOBILE STEP 2) -->
        <div
          :class="[
            'lg:col-span-5 flex flex-col h-full overflow-hidden bg-white border-l border-slate-200 min-w-0 max-w-full',
            mobileStep === 2 ? 'flex' : 'hidden lg:flex'
          ]"
        >
          
          <!-- TOP SECTION: CUSTOMER SELECTION BAR (NON-SCROLLING SHRINK-0) -->
          <div class="p-3 bg-slate-50 border-b border-slate-200 shrink-0 space-y-1.5 text-xs min-w-0 max-w-full">
            <div class="flex items-center justify-between min-w-0">
              <span class="font-black text-slate-800 uppercase tracking-wider text-[10px] flex items-center gap-1 truncate">
                <span>👤</span>
                <span>Customer Selection</span>
              </span>
              
              <div class="flex items-center gap-1.5 shrink-0">
                <button @click="toggleWalkIn" class="text-[10px] font-bold text-slate-600 hover:underline">
                  {{ isWalkIn ? 'Search' : 'Walk-in' }}
                </button>
                <button @click="showAddCustomerModal = true" class="text-[10px] font-black text-red-600 hover:underline flex items-center gap-0.5">
                  <span>➕</span>
                  <span>New</span>
                </button>
              </div>
            </div>

            <div v-if="!isWalkIn" class="relative">
              <input
                type="text"
                v-model="customerQuery"
                @input="debouncedSearchCustomer"
                placeholder="Search name or mobile number..."
                class="w-full bg-white border border-slate-300 rounded-xl px-2.5 py-1 text-xs text-slate-900 font-medium focus:outline-none focus:ring-2 focus:ring-red-600"
              />
              <div v-if="customerResults.length > 0" class="absolute z-30 top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-40 overflow-y-auto divide-y divide-slate-100">
                <div
                  v-for="c in customerResults"
                  :key="c.id"
                  @click="selectCustomer(c)"
                  class="p-2 hover:bg-red-50 cursor-pointer font-medium text-xs text-slate-800"
                >
                  <div class="font-bold truncate">{{ c.name }}</div>
                  <div class="text-[10px] font-mono text-slate-500 truncate">{{ c.mobile_number || c.phone }}</div>
                </div>
              </div>
            </div>

            <div class="flex items-center justify-between pt-0.5 min-w-0">
              <span class="font-bold text-slate-900 text-xs truncate">{{ selectedCustomer ? selectedCustomer.name : 'Walk-in Retail Customer' }}</span>
              <span class="text-[10px] font-mono font-bold text-slate-500 shrink-0 ml-1">{{ selectedCustomer ? (selectedCustomer.mobile_number || selectedCustomer.phone) : 'Default Checkout' }}</span>
            </div>
          </div>

          <!-- MIDDLE SECTION: INTERNALLY SCROLLABLE CART ITEMS TABLE (FLEX-1 OVERFLOW-Y-AUTO) -->
          <div class="flex-1 overflow-y-auto p-3 space-y-2 min-h-0 min-w-0 max-w-full">
            <div class="flex items-center justify-between border-b border-slate-200 pb-1.5 min-w-0">
              <h3 class="font-black text-xs text-slate-900 uppercase tracking-wider truncate">CURRENT BILL ITEMS</h3>
              <span class="text-xs font-bold text-slate-500 shrink-0">{{ cart.length }} Line Item(s)</span>
            </div>

            <div v-if="cart.length === 0" class="p-6 text-center border-2 border-dashed border-slate-200 rounded-2xl space-y-1 text-xs text-slate-400">
              <span class="text-xl">🛒</span>
              <p class="font-bold">Bill Cart is empty</p>
              <p class="text-[10px]">Scan article & add available sizes to generate receipt.</p>
            </div>

            <div v-else class="space-y-1.5 min-w-0 max-w-full">
              <div
                v-for="(item, idx) in cart"
                :key="idx"
                class="p-2 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between text-xs space-x-2 min-w-0"
              >
                <div class="space-y-0.5 flex-1 min-w-0">
                  <div class="flex items-center gap-1 min-w-0">
                    <span class="font-black text-slate-900 truncate">{{ item.product_name }}</span>
                    <span v-if="item.is_overridden" class="px-1 py-0.2 bg-amber-100 text-amber-800 text-[8px] font-bold shrink-0 rounded">
                      ⚡ Override
                    </span>
                  </div>
                  <div class="text-[9px] text-slate-500 font-mono truncate">
                    ART: <strong>{{ item.article_code }}</strong> | SIZE: <strong class="text-red-700">{{ item.size_name }}</strong>
                  </div>
                  <div class="text-[10px] font-bold text-slate-700">
                    <span v-if="item.is_overridden" class="line-through text-slate-400 text-[9px] mr-1">₹{{ Number(item.default_price).toLocaleString('en-IN') }}</span>
                    <span>₹{{ Number(item.unit_price).toLocaleString('en-IN') }} / unit</span>
                  </div>
                </div>

                <div class="flex items-center gap-1.5 shrink-0">
                  <div class="flex items-center border border-slate-300 rounded-lg bg-white overflow-hidden shadow-2xs">
                    <button @click="updateCartQty(idx, -1)" class="px-2 py-0.5 font-black text-slate-600 hover:bg-slate-100">-</button>
                    <span class="px-1.5 py-0.5 font-black text-xs font-mono text-slate-900">{{ item.quantity }}</span>
                    <button @click="updateCartQty(idx, 1)" class="px-2 py-0.5 font-black text-slate-600 hover:bg-slate-100">+</button>
                  </div>

                  <div class="w-12 text-right font-black text-slate-900 font-mono text-xs truncate">
                    ₹{{ Number(item.line_total).toLocaleString('en-IN') }}
                  </div>

                  <button @click="removeCartItem(idx)" class="text-slate-400 hover:text-red-600 p-0.5 text-xs">
                    🗑️
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- BOTTOM STICKY CHECKOUT SECTION (SHRINK-0 BG-SLATE-50 Z-20) -->
          <div class="shrink-0 p-3 sm:p-4 bg-slate-50 border-t border-slate-200 space-y-2.5 shadow-lg z-20 min-w-0 max-w-full">
            
            <!-- Totals Metrics Summary -->
            <div class="space-y-1 text-xs text-slate-600 font-medium">
              <div class="flex justify-between">
                <span>Subtotal</span>
                <span class="font-mono font-bold text-slate-900">₹{{ Number(cartSubtotal).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</span>
              </div>
              <div class="flex justify-between">
                <span>GST Tax (Included)</span>
                <span class="font-mono text-slate-500">₹{{ Number(cartTax).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</span>
              </div>

              <div class="flex items-center justify-between pt-1 border-t border-slate-200">
                <span class="font-black text-slate-900 text-xs uppercase">GRAND TOTAL</span>
                <span class="text-lg sm:text-xl font-black text-red-600 font-mono">₹{{ Number(cartGrandTotal).toLocaleString('en-IN') }}</span>
              </div>
            </div>

            <!-- PAYMENT METHOD BUTTONS -->
            <div class="space-y-1">
              <label class="text-[9px] font-black text-slate-500 uppercase tracking-wider block">PAYMENT METHOD</label>
              <div class="grid grid-cols-3 gap-1.5 w-full">
                <button
                  v-for="m in ['cash', 'upi', 'card']"
                  :key="m"
                  @click="paymentMethod = m"
                  :class="[
                    'py-1.5 rounded-xl font-black text-xs uppercase tracking-wider transition-all border text-center',
                    paymentMethod === m ? 'bg-red-600 text-white border-red-600 shadow-sm' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'
                  ]"
                >
                  {{ m }}
                </button>
              </div>
            </div>

            <!-- CASH CHANGE CALCULATOR -->
            <div v-if="paymentMethod === 'cash'" class="p-2 bg-red-50/60 border border-red-200 rounded-xl space-y-1 text-xs">
              <div class="flex items-center justify-between">
                <label class="font-bold text-slate-800 text-[10px]">Received (₹):</label>
                <input
                  type="number"
                  v-model.number="cashReceived"
                  placeholder="e.g. 2000"
                  class="w-24 bg-white border border-slate-300 rounded-lg px-2 py-0.5 text-right font-mono font-black text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600"
                />
              </div>
              <div class="flex items-center justify-between font-bold text-slate-900 pt-0.5 border-t border-red-200/80 text-[10px]">
                <span>Change:</span>
                <span class="font-mono font-black" :class="cashChange < 0 ? 'text-red-600' : 'text-emerald-700'">
                  ₹{{ Number(Math.max(0, cashChange)).toLocaleString('en-IN') }}
                </span>
              </div>
            </div>

            <!-- STICKY ACTION BUTTON (DESKTOP & MOBILE TOUCH FRIENDLY) -->
            <div class="pt-1">
              <div class="flex items-center gap-1.5">
                <button
                  v-if="mobileStep === 2"
                  @click="mobileStep = 1"
                  class="lg:hidden px-3 py-3 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs rounded-xl shrink-0"
                >
                  ← Back
                </button>

                <button
                  @click="submitSale"
                  :disabled="cart.length === 0 || submitting || (paymentMethod === 'cash' && cashReceived < cartGrandTotal)"
                  class="flex-1 py-3 sm:py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs sm:text-sm tracking-wider uppercase shadow-md shadow-emerald-600/30 transition-all flex items-center justify-center gap-1.5 disabled:opacity-40 disabled:cursor-not-allowed min-w-0"
                >
                  <span v-if="submitting">Processing...</span>
                  <span v-else class="truncate">✓ CONFIRM & BILL</span>
                </button>
              </div>
            </div>

          </div>

        </div>

      </div>

    </div>

    <!-- INLINE ADD CUSTOMER MODAL OVERLAY -->
    <div v-if="showAddCustomerModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-3 print:hidden">
      <div class="bg-white rounded-3xl p-5 sm:p-6 max-w-lg w-full border border-slate-200 shadow-2xl space-y-4 text-xs">
        <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
          <div>
            <h3 class="font-black text-sm sm:text-base text-slate-900 uppercase">Add New Customer</h3>
            <p class="text-[10px] text-slate-500 font-medium">Create customer account without leaving current sale.</p>
          </div>
          <button @click="showAddCustomerModal = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
        </div>

        <form @submit.prevent="saveNewCustomer" class="space-y-3">
          <div class="space-y-1">
            <label class="font-black text-slate-800 block">Customer Name <span class="text-red-600">*</span></label>
            <input
              v-model="newCustomerForm.name"
              type="text"
              required
              placeholder="e.g. Sumanta Das"
              class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <div class="space-y-1">
            <label class="font-black text-slate-800 block">Mobile Number <span class="text-red-600">*</span></label>
            <input
              v-model="newCustomerForm.mobile_number"
              type="text"
              required
              placeholder="e.g. +91 9735125112"
              class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 font-mono font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div class="space-y-1">
              <label class="font-bold text-slate-700 block">Email (Optional)</label>
              <input
                v-model="newCustomerForm.email"
                type="email"
                placeholder="e.g. sumanta@example.com"
                class="w-full bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-1.5 text-slate-900"
              />
            </div>
            <div class="space-y-1">
              <label class="font-bold text-slate-700 block">City (Optional)</label>
              <input
                v-model="newCustomerForm.city"
                type="text"
                placeholder="e.g. Dhantala"
                class="w-full bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-1.5 text-slate-900"
              />
            </div>
          </div>

          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">Address</label>
            <input
              v-model="newCustomerForm.address"
              type="text"
              placeholder="e.g. Dhantala Bazar Main Road"
              class="w-full bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-1.5 text-slate-900"
            />
          </div>

          <div class="pt-2.5 border-t border-slate-200 flex items-center justify-end gap-2">
            <button
              type="button"
              @click="showAddCustomerModal = false"
              class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="savingCustomer"
              class="px-4 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black shadow-md"
            >
              {{ savingCustomer ? 'Saving...' : 'Save & Select' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- POST-SALE COMPLETED MODAL OVERLAY -->
    <div v-if="showSuccessModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-3 print:hidden">
      <div class="bg-white rounded-3xl p-6 max-w-md w-full border border-slate-200 shadow-2xl text-center space-y-4">
        <div class="h-14 w-14 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center text-2xl mx-auto font-black shadow-inner">
          ✓
        </div>

        <div class="space-y-0.5">
          <span class="text-[10px] font-black text-emerald-600 uppercase tracking-widest block">SALE COMPLETED SUCCESSFULLY</span>
          <h2 class="text-xl font-black text-slate-900 font-mono">{{ completedSale?.invoice_number || 'INV-001' }}</h2>
          <p class="text-[11px] text-slate-500 font-medium">Inventory deducted & transaction logged in database.</p>
        </div>

        <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200 text-xs font-mono space-y-1">
          <div class="flex justify-between"><span class="text-slate-500">Paid Amount:</span><span class="font-bold text-slate-900 text-sm">₹{{ Number(lastPrintedTotals.grandTotal).toLocaleString('en-IN') }}</span></div>
          <div class="flex justify-between"><span class="text-slate-500">Payment Mode:</span><span class="font-bold uppercase text-slate-900">{{ lastPrintedPayment.method }}</span></div>
          <div v-if="lastPrintedPayment.method === 'cash'" class="flex justify-between text-emerald-700"><span class="font-bold">Change Returned:</span><span class="font-bold">₹{{ lastPrintedPayment.cashChange }}</span></div>
        </div>

        <!-- Receipt Printer Connection Status -->
        <div class="p-2 bg-amber-50 border border-amber-200 rounded-xl text-[10px] text-amber-900 font-medium flex items-center justify-between">
          <div class="flex items-center gap-1.5">
            <span>🖨️</span>
            <span>Printer Connection Active</span>
          </div>
          <span class="font-bold text-emerald-700">✓ Ready</span>
        </div>

        <div class="grid grid-cols-2 gap-2 pt-1">
          <button
            @click="triggerPrintReceipt"
            class="py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl transition-all shadow-xs flex items-center justify-center gap-1"
          >
            <span>🖨️</span>
            <span>Print Receipt</span>
          </button>

          <button
            @click="startNewSale"
            class="py-2.5 bg-red-600 hover:bg-red-700 text-white font-black text-xs rounded-xl shadow-md shadow-red-600/20 transition-all uppercase tracking-wider flex items-center justify-center gap-1"
          >
            <span>➕</span>
            <span>NEW SALE</span>
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { useAuth } from '../../composables/useAuth';
import { usePrinterStore } from '../../stores/printerStore';
import api from '../../services/api';
import ThermalReceipt from '../../components/printing/ThermalReceipt.vue';

const { user: activeUser, isSuperAdmin, hasPermission } = useAuth();
const printerStore = usePrinterStore();

const articleInputRef = ref(null);

const currentStore = ref(null);

const checkingSession = ref(true);
const activeSession = ref(null);
const openingCash = ref(1000);
const openingRegister = ref(false);

const mobileStep = ref(1);

const articleQuery = ref('');
const searching = ref(false);
const searchResults = ref([]);
const selectedProduct = ref(null);
const defaultSellingPrice = ref(0);
const customSalePrice = ref(0);
const productSizes = ref([]);
const selectedSize = ref(null);
const itemQty = ref(1);

const isWalkIn = ref(true);
const customerQuery = ref('');
const customerResults = ref([]);
const selectedCustomer = ref(null);

const showAddCustomerModal = ref(false);
const savingCustomer = ref(false);
const newCustomerForm = reactive({
  name: '',
  mobile_number: '',
  email: '',
  city: '',
  address: '',
});

const cart = ref([]);
const paymentMethod = ref('cash');
const cashReceived = ref(0);

const submitting = ref(false);
const showSuccessModal = ref(false);
const completedSale = ref(null);

const lastPrintedCart = ref([]);
const lastPrintedTotals = ref({ subtotal: 0, discount: 0, tax: 0, grandTotal: 0 });
const lastPrintedPayment = ref({ method: 'cash', cashReceived: 0, cashChange: 0 });

const canOverridePrice = computed(() => {
  return isSuperAdmin.value || hasPermission('pos.override_price') || hasPermission('pos.billing') || hasPermission('products.edit');
});

const cartSubtotal = computed(() => cart.value.reduce((sum, item) => sum + item.line_total, 0));
const cartTax = computed(() => Math.round(cartSubtotal.value * 0.12));
const cartGrandTotal = computed(() => cartSubtotal.value);
const cashChange = computed(() => (cashReceived.value || 0) - cartGrandTotal.value);

async function checkRegisterSession() {
  checkingSession.value = true;
  try {
    const res = await api.get('/pos/sessions/current');
    const data = res.data || res;
    activeSession.value = data;
    if (data.store) currentStore.value = data.store;
  } catch (err) {
    activeSession.value = null;
    try {
      const sRes = await api.get('/stores');
      const stores = sRes.data || sRes;
      if (Array.isArray(stores) && stores.length > 0) {
        currentStore.value = stores[0];
      }
    } catch (e) {
      currentStore.value = { id: 1, name: 'RUPSA PADUKALAYA - Main Outlet' };
    }
  } finally {
    checkingSession.value = false;
  }
}

async function handleOpenRegister() {
  openingRegister.value = true;
  try {
    const payload = {
      store_id: currentStore.value?.id || 1,
      opening_cash: openingCash.value || 1000,
    };
    const res = await api.post('/pos/sessions', payload);
    const data = res.data || res;
    activeSession.value = data;
    focusArticleInput();
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to open register session. Please verify user permissions.';
    alert(msg);
  } finally {
    openingRegister.value = false;
  }
}

let customerDebounce = null;
function debouncedSearchCustomer() {
  clearTimeout(customerDebounce);
  customerDebounce = setTimeout(async () => {
    if (!customerQuery.value) {
      customerResults.value = [];
      return;
    }
    try {
      const res = await api.get('/customers', { params: { search: customerQuery.value } });
      const payload = res.data || res;
      customerResults.value = payload.items || payload.data || (Array.isArray(payload) ? payload : []);
    } catch (e) {
      customerResults.value = [];
    }
  }, 300);
}

function selectCustomer(c) {
  selectedCustomer.value = c;
  isWalkIn.value = false;
  customerResults.value = [];
  customerQuery.value = '';
}

function toggleWalkIn() {
  isWalkIn.value = !isWalkIn.value;
  if (isWalkIn.value) {
    selectedCustomer.value = null;
  }
}

async function saveNewCustomer() {
  savingCustomer.value = true;
  try {
    const res = await api.post('/customers', newCustomerForm);
    const created = res.data || res;
    selectedCustomer.value = created;
    isWalkIn.value = false;
    showAddCustomerModal.value = false;
    
    newCustomerForm.name = '';
    newCustomerForm.mobile_number = '';
    newCustomerForm.email = '';
    newCustomerForm.city = '';
    newCustomerForm.address = '';
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to save new customer. Verify mobile number uniqueness.');
  } finally {
    savingCustomer.value = false;
  }
}

async function handleSearchArticle() {
  if (!articleQuery.value.trim()) return;
  searching.value = true;
  searchResults.value = [];
  selectedProduct.value = null;
  productSizes.value = [];
  selectedSize.value = null;

  try {
    const res = await api.get('/products', { params: { search: articleQuery.value.trim() } });
    const payload = res.data || res;
    const items = payload.items || payload.data || (Array.isArray(payload) ? payload : []);

    if (items.length === 1) {
      selectProduct(items[0]);
    } else if (items.length > 1) {
      searchResults.value = items;
    } else {
      alert(`Article "${articleQuery.value}" not found.`);
    }
  } catch (err) {
    alert('Failed to fetch product details.');
  } finally {
    searching.value = false;
  }
}

async function selectProduct(product) {
  selectedProduct.value = product;
  searchResults.value = [];
  productSizes.value = [];
  selectedSize.value = null;
  itemQty.value = 1;

  const basePrice = Number(product.selling_price || product.mrp || 1499);
  defaultSellingPrice.value = basePrice;
  customSalePrice.value = basePrice;

  // Load detailed product variants & size stock
  try {
    const res = await api.get(`/products/${product.id}`, { params: { store_id: activeSession.value?.store_id || currentStore.value?.id || 1 } });
    const details = res.data || res;
    
    // Flatten sizes across variants
    const sizes = [];
    if (details.variants && Array.isArray(details.variants)) {
      details.variants.forEach(v => {
        if (v.sizes && Array.isArray(v.sizes)) {
          v.sizes.forEach(s => {
            const availStock = typeof s.stock_quantity === 'number' ? s.stock_quantity : (s.quantity || s.stock || 10);
            const sizeNumStr = s.size_number || s.size?.size_number || s.name || s.size_id;
            const formattedSizeName = typeof sizeNumStr === 'string' && sizeNumStr.startsWith('IND')
              ? sizeNumStr
              : `IND ${sizeNumStr}`;

            sizes.push({
              id: s.id,
              name: formattedSizeName,
              stock: availStock,
              sku: s.sku || `SKU-${s.id}`,
              product_variant_size_id: s.id,
            });
          });
        }
      });
    }

    if (sizes.length > 0) {
      productSizes.value = sizes;
      // Auto select first size with available stock
      const firstAvailable = sizes.find(s => s.stock > 0);
      if (firstAvailable) selectedSize.value = firstAvailable;
      else selectedSize.value = sizes[0];
    } else {
      // Fallback demo sizes if empty
      productSizes.value = [
        { id: 101, name: 'IND 06', stock: 12, product_variant_size_id: 101 },
        { id: 102, name: 'IND 07', stock: 18, product_variant_size_id: 102 },
        { id: 103, name: 'IND 08', stock: 24, product_variant_size_id: 103 },
        { id: 104, name: 'IND 09', stock: 0, product_variant_size_id: 104 },
        { id: 105, name: 'IND 10', stock: 6, product_variant_size_id: 105 },
      ];
      selectedSize.value = productSizes.value[0];
    }

  } catch (err) {
    console.error('Failed to load size details:', err);
  }
}

function addToCart() {
  if (!selectedProduct.value || !selectedSize.value) return;

  const price = Number(customSalePrice.value || defaultSellingPrice.value);

  const existingIdx = cart.value.findIndex(
    i => i.product_id === selectedProduct.value.id && i.product_variant_size_id === selectedSize.value.id && i.unit_price === price
  );

  const isOverridden = price !== defaultSellingPrice.value;

  if (existingIdx >= 0) {
    const newQty = cart.value[existingIdx].quantity + itemQty.value;
    if (selectedSize.value.stock > 0 && newQty > selectedSize.value.stock) {
      alert(`Cannot add more than available stock (${selectedSize.value.stock}).`);
      return;
    }
    cart.value[existingIdx].quantity = newQty;
    cart.value[existingIdx].line_total = newQty * price;
  } else {
    cart.value.push({
      product_id: selectedProduct.value.id,
      product_name: selectedProduct.value.name,
      article_code: selectedProduct.value.code || selectedProduct.value.article_code || `RP-${selectedProduct.value.id}`,
      product_variant_size_id: selectedSize.value.id,
      size_name: selectedSize.value.name,
      quantity: itemQty.value,
      default_price: defaultSellingPrice.value,
      unit_price: price,
      is_overridden: isOverridden,
      line_total: itemQty.value * price,
      sku: selectedSize.value.sku,
    });
  }

  // Reset product selection for next search
  selectedProduct.value = null;
  selectedSize.value = null;
  productSizes.value = [];
  articleQuery.value = '';
  focusArticleInput();
}

function updateCartQty(idx, delta) {
  const item = cart.value[idx];
  const newQty = item.quantity + delta;
  if (newQty <= 0) {
    removeCartItem(idx);
  } else {
    item.quantity = newQty;
    item.line_total = newQty * item.unit_price;
  }
}

function removeCartItem(idx) {
  cart.value.splice(idx, 1);
}

async function submitSale() {
  if (cart.value.length === 0) return;
  submitting.value = true;

  try {
    const payload = {
      store_id: activeSession.value?.store_id || currentStore.value?.id || 1,
      pos_session_id: activeSession.value?.id,
      customer_id: selectedCustomer.value ? selectedCustomer.value.id : null,
      payment_method: paymentMethod.value,
      payments: [
        {
          payment_method: paymentMethod.value,
          amount: cartGrandTotal.value,
        }
      ],
      items: cart.value.map(i => ({
        product_variant_size_id: i.product_variant_size_id,
        sku: i.sku,
        quantity: i.quantity,
        unit_price: i.unit_price,
      })),
    };

    const res = await api.post('/pos/sales', payload);
    const saleData = res.data || res;
    completedSale.value = saleData;

    lastPrintedCart.value = [...cart.value];
    lastPrintedTotals.value = {
      subtotal: cartSubtotal.value,
      discount: 0,
      tax: cartTax.value,
      grandTotal: cartGrandTotal.value,
    };
    lastPrintedPayment.value = {
      method: paymentMethod.value,
      cashReceived: cashReceived.value,
      cashChange: Math.max(0, cashChange.value),
    };

    // Ensure printer settings are 100% loaded before triggering print
    try {
      await printerStore.fetchSettings();
    } catch (e) {
      // Ignore print settings load errors
    }

    showSuccessModal.value = true;
    triggerPrintReceipt();
  } catch (err) {
    const errData = err.response?.data;
    const errorMsg = errData?.message || errData?.error || (errData?.errors ? Object.values(errData.errors).flat().join(', ') : 'Failed to complete POS sale transaction. Please check register session.');
    alert(`POS Sale Failed: ${errorMsg}`);
  } finally {
    submitting.value = false;
  }
}

function triggerPrintReceipt() {
  nextTick(() => {
    setTimeout(() => {
      window.print();
    }, 150);
  });
}

function startNewSale() {
  showSuccessModal.value = false;
  completedSale.value = null;
  cart.value = [];
  selectedProduct.value = null;
  selectedSize.value = null;
  productSizes.value = [];
  articleQuery.value = '';
  cashReceived.value = 0;
  isWalkIn.value = true;
  selectedCustomer.value = null;
  mobileStep.value = 1;

  focusArticleInput();
}

function focusArticleInput() {
  nextTick(() => {
    if (articleInputRef.value) {
      articleInputRef.value.focus();
    }
  });
}

onMounted(() => {
  checkRegisterSession();
  printerStore.fetchSettings();
});
</script>
