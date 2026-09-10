<template>
  <div class="max-w-3xl mx-auto space-y-4 font-sans pb-60 lg:pb-36 text-slate-900 dark:text-slate-100">
    <!-- Top Header & Store Selector -->
    <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <button
          v-if="selectedProduct"
          @click="clearSelectedProduct"
          class="p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 transition-colors"
          title="Back to search"
        >
          <span class="text-base font-black">←</span>
        </button>
        <div>
          <h1 class="font-black text-lg text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
            <span>Stock Add</span>
            <span class="px-2 py-0.5 rounded-full bg-red-50 dark:bg-red-950/50 text-red-600 dark:text-red-400 font-mono font-bold text-[10px] border border-red-200 dark:border-red-800">
              DIRECT ENTRY
            </span>
          </h1>
          <p class="text-xs text-slate-500 dark:text-slate-400">Search product and add stock size-wise in one bulk operation.</p>
        </div>
      </div>

      <!-- Store Location Dropdown -->
      <div class="flex items-center gap-2 w-full sm:w-auto shrink-0 bg-slate-50 dark:bg-slate-800/80 p-1.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700">
        <span class="text-xs text-slate-400">🏬</span>
        <select
          v-model="selectedStoreId"
          @change="onStoreChange"
          class="w-full sm:w-auto bg-transparent border-0 text-xs font-bold text-slate-900 dark:text-white focus:outline-none cursor-pointer"
        >
          <option v-for="store in accessibleStores" :key="store.id" :value="store.id">
            {{ store.name }} ({{ store.code }})
          </option>
        </select>
      </div>
    </div>

    <!-- 1. PRODUCT SEARCH FIELD -->
    <div class="relative bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xs space-y-2">
      <div class="relative" ref="searchContainerRef">
        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-base">🔍</span>
        <input
          ref="searchInputRef"
          type="text"
          v-model="searchQuery"
          @input="onSearchInput"
          @keydown.down.prevent="onKeyDown"
          @keydown.up.prevent="onKeyUp"
          @keydown.enter.prevent="onKeyEnter"
          @keydown.esc.prevent="onKeyEsc"
          @focus="onInputFocus"
          placeholder="Search product, item no, SKU, barcode, brand..."
          class="w-full bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl pl-10 pr-10 py-3 text-xs sm:text-sm font-bold text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white dark:focus:bg-slate-900 transition-all"
        />
        <button
          v-if="searchQuery"
          @click="clearSearch"
          type="button"
          class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1 text-xs bg-slate-200 dark:bg-slate-700 rounded-full w-5 h-5 flex items-center justify-center"
        >
          ✕
        </button>

        <!-- LIVE SEARCH SUGGESTIONS DROPDOWN (CARD LIST AS IN STEP 1 OF IMAGE) -->
        <div
          v-if="showDropdown"
          class="absolute z-50 top-full left-0 right-0 mt-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl overflow-hidden max-h-96 flex flex-col text-xs"
        >
          <div v-if="isSearchingSuggestions && searchSuggestions.length === 0" class="p-4 text-center text-slate-500 font-medium flex items-center justify-center gap-2">
            <span class="animate-spin text-red-600">⏳</span>
            <span>Searching products...</span>
          </div>

          <div v-else-if="!isSearchingSuggestions && searchSuggestions.length === 0" class="p-4 text-center text-slate-400 font-medium">
            <span>🚫 No matching products found for "{{ searchQuery }}"</span>
          </div>

          <div v-else class="overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/80 flex-1 p-1">
            <div
              v-for="(item, idx) in searchSuggestions"
              :key="item.id || idx"
              :ref="el => { if (el) suggestionRefs[idx] = el }"
              @click="selectProductFromSearch(item)"
              @mouseenter="highlightedIndex = idx"
              :class="[
                'p-3 rounded-xl flex items-center justify-between cursor-pointer transition-all gap-3 select-none',
                highlightedIndex === idx ? 'bg-red-50 dark:bg-slate-800 ring-1 ring-red-200 dark:ring-slate-700' : 'hover:bg-slate-50 dark:hover:bg-slate-800/50'
              ]"
            >
              <div class="flex items-center gap-3 min-w-0 flex-1">
                <!-- Thumbnail -->
                <div class="h-12 w-12 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center shrink-0 overflow-hidden text-xl shadow-2xs">
                  <img v-if="item.primary_image_url" :src="item.primary_image_url" :alt="item.product_name" class="w-full h-full object-cover" />
                  <span v-else>👞</span>
                </div>

                <!-- Product Details -->
                <div class="min-w-0 flex-1 space-y-0.5">
                  <h4 class="font-black text-slate-900 dark:text-white text-xs sm:text-sm truncate">{{ item.product_name }}</h4>
                  <div class="text-[11px] text-slate-500 dark:text-slate-400 font-medium flex items-center gap-2 flex-wrap">
                    <span>Item No: <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ item.article_code }}</strong></span>
                    <span>•</span>
                    <span>Brand: <strong class="text-slate-800 dark:text-slate-200">{{ item.brand_name }}</strong></span>
                  </div>
                </div>
              </div>

              <!-- Chevron Right -->
              <div class="text-slate-400 text-base font-bold shrink-0">
                ›
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- LOADING STATE -->
    <div v-if="loadingProduct" class="p-10 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
      <span class="text-3xl animate-spin inline-block text-red-600">⏳</span>
      <p class="text-xs font-bold text-slate-600 dark:text-slate-400">Loading product sizes & current stock...</p>
    </div>

    <!-- SELECTED PRODUCT & SIZE-WISE LIST (EXACT MATCH FOR STEP 2 & STEP 3 OF REFERENCE IMAGE) -->
    <div v-else-if="selectedProduct" class="space-y-4">
      <!-- SELECTED PRODUCT HEADER CARD -->
      <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xs flex items-center justify-between gap-3">
        <div class="flex items-center gap-3.5 min-w-0">
          <div class="h-16 w-16 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center shrink-0 overflow-hidden text-2xl shadow-2xs">
            <img v-if="selectedProduct.primary_image_url" :src="selectedProduct.primary_image_url" :alt="selectedProduct.name" class="w-full h-full object-cover" />
            <span v-else>👞</span>
          </div>

          <div class="min-w-0 space-y-0.5">
            <h2 class="font-black text-base sm:text-lg text-slate-900 dark:text-white truncate">{{ selectedProduct.name }}</h2>
            <div class="text-xs text-slate-500 dark:text-slate-400 font-medium space-y-0.5">
              <div>Item No: <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ selectedProduct.article_number || 'RP-' + selectedProduct.id }}</strong></div>
              <div class="flex items-center gap-2">
                <span>Brand: <strong class="text-slate-800 dark:text-slate-200">{{ selectedProduct.brand?.name || 'Bata' }}</strong></span>
                <span>•</span>
                <span>Category: <strong class="text-slate-800 dark:text-slate-200">{{ selectedProduct.category?.name || 'Footwear' }}</strong></span>
              </div>
            </div>
          </div>
        </div>

        <button
          @click="clearSelectedProduct"
          class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 font-bold text-xs rounded-xl shrink-0 transition-colors"
        >
          Change
        </button>
      </div>

      <!-- SECTION TITLE -->
      <div class="flex items-center justify-between px-1">
        <h3 class="font-black text-sm text-slate-900 dark:text-white uppercase tracking-wider">Size Wise Stock</h3>
        <span class="text-xs text-slate-500 font-bold font-mono">{{ totalAvailableSizesCount }} Sizes Available</span>
      </div>

      <!-- SIZE-WISE CARDS LIST (MOBILE & TABLET / DESKTOP COMPACT LIST MATCHING THE IMAGE) -->
      <div v-for="variant in productVariants" :key="variant.id" class="space-y-2.5">
        <!-- Variant sub-header if product has multiple colors -->
        <div v-if="productVariants.length > 1" class="flex items-center gap-2 px-1 pt-1">
          <span class="h-2.5 w-2.5 rounded-full border border-slate-300" :style="{ backgroundColor: variant.color?.code || '#000000' }"></span>
          <span class="font-bold text-xs text-slate-700 dark:text-slate-300 uppercase font-mono">Color: {{ variant.color?.name || 'Standard' }}</span>
        </div>

        <!-- LIST OF SIZE ROWS/CARDS -->
        <div class="space-y-2">
          <div
            v-for="s in variant.sizes"
            :key="s.id"
            :class="[
              'p-3.5 rounded-2xl border transition-all flex items-center justify-between gap-3 shadow-2xs',
              (addQuantities[s.id] || 0) > 0
                ? 'bg-red-50/60 dark:bg-red-950/30 border-red-200 dark:border-red-900 ring-1 ring-red-300/50'
                : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-slate-300'
            ]"
          >
            <!-- Left: Size Badge Pill -->
            <div class="flex items-center gap-3.5 min-w-0">
              <div
                :class="[
                  'w-11 h-11 rounded-xl flex items-center justify-center font-black font-mono text-base border shrink-0 shadow-2xs transition-colors',
                  (addQuantities[s.id] || 0) > 0
                    ? 'bg-red-600 text-white border-red-600'
                    : 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
                ]"
              >
                {{ s.size_number }}
              </div>

              <!-- Center: Stock & Selling Price Info (Unselected vs Selected) -->
              <div v-if="!(addQuantities[s.id] > 0)" class="min-w-0">
                <div class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                  <span>Stock:</span>
                  <span :class="s.current_stock > 0 ? 'text-slate-900 dark:text-white font-black font-mono' : 'text-red-600 dark:text-red-400 font-black font-mono'">
                    {{ s.current_stock }} PCS
                  </span>
                </div>
                <div class="text-[11px] font-medium text-slate-400 font-mono">
                  ₹{{ Number(s.selling_price || 0).toLocaleString('en-IN') }}/PCS
                </div>
              </div>

              <!-- Selected Active State Info: Stock -> Add -> New Stock -->
              <div v-else class="min-w-0 space-y-0.5">
                <div class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                  <span class="text-slate-500">Stock:</span>
                  <span class="font-black font-mono text-slate-900 dark:text-white">{{ s.current_stock }} PCS</span>
                </div>
                <div class="text-xs font-bold text-red-600 dark:text-red-400 flex items-center gap-1.5">
                  <span>Add:</span>
                  <span class="font-black font-mono">+{{ addQuantities[s.id] }} PCS</span>
                </div>
                <div class="text-xs font-black text-emerald-700 dark:text-emerald-300 flex items-center gap-1.5 bg-emerald-100/70 dark:bg-emerald-950/60 px-2 py-0.5 rounded-lg border border-emerald-200 dark:border-emerald-800/80">
                  <span>New Stock:</span>
                  <span class="font-mono text-xs sm:text-sm font-black">{{ s.current_stock + addQuantities[s.id] }} PCS</span>
                </div>
              </div>
            </div>

            <!-- Right: Action Button or Stepper Quantity Controls -->
            <div class="shrink-0">
              <!-- INITIAL STATE: ADD + BUTTON -->
              <button
                v-if="!(addQuantities[s.id] > 0)"
                @click="updateQty(s.id, 1)"
                type="button"
                class="px-4 py-2 rounded-full border-2 border-blue-500/80 hover:border-blue-600 bg-blue-50/50 hover:bg-blue-100/80 dark:bg-blue-950/40 dark:hover:bg-blue-900/60 text-blue-600 dark:text-blue-400 font-black text-xs transition-all shadow-2xs flex items-center gap-1 active:scale-95"
              >
                <span>ADD</span>
                <span class="text-sm">+</span>
              </button>

              <!-- ACTIVE STATE: STEPPER [ - ] QTY [ + ] -->
              <div
                v-else
                class="flex items-center gap-1 bg-white dark:bg-slate-800 p-1 rounded-full border border-red-300 dark:border-red-800 shadow-2xs"
              >
                <!-- Minus Button -->
                <button
                  @click="updateQty(s.id, -1)"
                  type="button"
                  class="w-8 h-8 rounded-full bg-red-100 dark:bg-red-900/60 hover:bg-red-200 dark:hover:bg-red-800 text-red-700 dark:text-red-300 font-black text-sm flex items-center justify-center transition-all active:scale-90"
                >
                  −
                </button>

                <!-- Quantity Display / Input -->
                <input
                  type="number"
                  v-model.number="addQuantities[s.id]"
                  min="0"
                  class="w-10 text-center font-black font-mono text-sm text-slate-900 dark:text-white bg-transparent focus:outline-none"
                />

                <!-- Plus Button -->
                <button
                  @click="updateQty(s.id, 1)"
                  type="button"
                  class="w-8 h-8 rounded-full bg-red-600 hover:bg-red-700 text-white font-black text-sm flex items-center justify-center transition-all shadow-2xs active:scale-90"
                >
                  +
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- INITIAL UNSELECTED PLACEHOLDER -->
    <div v-else class="p-10 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 text-center space-y-3 shadow-2xs">
      <div class="h-14 w-14 bg-red-50 dark:bg-red-950/50 text-red-600 dark:text-red-400 rounded-full flex items-center justify-center mx-auto text-2xl border border-red-200 dark:border-red-900">
        👞
      </div>
      <h3 class="font-black text-base text-slate-900 dark:text-white">Search & Select Product</h3>
      <p class="text-xs text-slate-500 dark:text-slate-400 max-w-xs mx-auto">
        Type a product name, item number, SKU or barcode above to load size-wise inventory stock.
      </p>
    </div>

    <!-- 7. STICKY BOTTOM SUMMARY ACTION BAR (ONLY WHEN NOT IN MODALS & POSITIONED ABOVE MOBILE NAV) -->
    <div
      v-if="selectedProduct && !showConfirmModal && !showSuccessModal"
      class="fixed bottom-[calc(4rem+env(safe-area-inset-bottom,0px))] lg:bottom-0 left-0 right-0 z-[90] bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-t border-slate-200 dark:border-slate-800 p-3.5 sm:px-6 shadow-2xl transition-all"
    >
      <div class="max-w-3xl mx-auto flex items-center justify-between gap-4">
        <!-- Left: Summary Info -->
        <div>
          <div class="text-xs sm:text-sm font-black text-slate-900 dark:text-white">
            {{ totalSizesSelected }} {{ totalSizesSelected === 1 ? 'Size' : 'Sizes' }} Selected
          </div>
          <div class="text-xs font-bold text-slate-500 dark:text-slate-400 font-mono">
            Total Units to Add: <strong class="text-red-600 font-black">+{{ totalUnitsToAdd }} PCS</strong>
          </div>
        </div>

        <!-- Right: Single Update Stock Button -->
        <button
          @click="openConfirmationModal"
          :disabled="totalUnitsToAdd <= 0 || submitting"
          class="px-5 sm:px-8 py-3 rounded-full bg-red-600 hover:bg-red-700 active:scale-98 text-white font-black text-xs sm:text-sm uppercase tracking-wider shadow-lg shadow-red-600/30 transition-all flex items-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed disabled:shadow-none"
        >
          <span>UPDATE STOCK</span>
          <span class="text-base">→</span>
        </button>
      </div>
    </div>

    <!-- CONFIRMATION MODAL OVERLAY -->
    <div v-if="showConfirmModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-3">
      <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 sm:p-6 max-w-md w-full border border-slate-200 dark:border-slate-800 shadow-2xl space-y-4 text-xs">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
          <div>
            <h3 class="font-black text-base text-slate-900 dark:text-white uppercase tracking-tight">Confirm Stock Update</h3>
            <p class="text-[10px] text-slate-500">Review size additions before submitting database transaction.</p>
          </div>
          <button @click="showConfirmModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">✕</button>
        </div>

        <div class="space-y-2 p-3 bg-slate-50 dark:bg-slate-800/60 rounded-2xl border border-slate-200 dark:border-slate-700">
          <div class="flex justify-between"><span class="text-slate-500">Product:</span><strong class="text-slate-900 dark:text-white font-black">{{ selectedProduct?.name }}</strong></div>
          <div class="flex justify-between"><span class="text-slate-500">Store:</span><strong class="text-slate-900 dark:text-white font-bold">{{ selectedStoreName }}</strong></div>
        </div>

        <div class="space-y-1">
          <label class="font-black text-slate-800 dark:text-slate-200 uppercase text-[10px] block">Size Additions Summary:</label>
          <div class="max-h-48 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800 border border-slate-200 dark:border-slate-800 rounded-xl p-2 bg-white dark:bg-slate-900">
            <div v-for="item in confirmItems" :key="item.pvsId" class="py-2 px-1 flex items-center justify-between">
              <span class="font-mono font-black text-slate-900 dark:text-white text-xs">Size {{ item.sizeName }}</span>
              <span class="font-mono font-bold text-red-600 dark:text-red-400 text-xs">+{{ item.quantity }} PCS</span>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-between p-3 bg-emerald-50 dark:bg-emerald-950/50 rounded-xl border border-emerald-200 dark:border-emerald-800 font-bold text-emerald-900 dark:text-emerald-300">
          <span>Total Units Added:</span>
          <span class="font-mono font-black text-sm">+{{ totalUnitsToAdd }} PCS</span>
        </div>

        <!-- Inline Error Banner if Submission Fails -->
        <div v-if="submitError" class="p-3 bg-red-50 dark:bg-red-950/60 border border-red-200 dark:border-red-800 rounded-xl text-red-600 dark:text-red-300 font-bold text-xs flex items-center gap-2">
          <span class="text-base">⚠</span>
          <span>{{ submitError }}</span>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
          <button
            @click="showConfirmModal = false"
            :disabled="submitting"
            class="px-4 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 rounded-xl font-bold disabled:opacity-50"
          >
            Cancel
          </button>
          <button
            @click="submitStockAdd"
            :disabled="submitting"
            class="px-6 py-2.5 bg-red-600 hover:bg-red-700 active:scale-95 text-white rounded-xl font-black shadow-md uppercase tracking-wider flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span v-if="submitting" class="animate-spin text-sm">⏳</span>
            <span>{{ submitting ? 'Updating Stock...' : 'Confirm & Update' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- 16. SUCCESS STATE MODAL (EXACT MATCH FOR STEP 4 OF REFERENCE IMAGE) -->
    <div v-if="showSuccessModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-3">
      <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 max-w-sm w-full border border-slate-200 dark:border-slate-800 shadow-2xl text-center space-y-4">
        <!-- Green Checkmark Circle Icon -->
        <div class="h-16 w-16 bg-emerald-500 text-white rounded-full flex items-center justify-center text-3xl mx-auto font-black shadow-lg shadow-emerald-500/30">
          ✓
        </div>

        <div class="space-y-1">
          <h2 class="text-xl font-black text-slate-900 dark:text-white">Stock Updated Successfully!</h2>
          <div class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ successData?.product_name }}</div>
          <div class="text-[11px] text-slate-400 font-mono">Item No: {{ selectedProduct?.article_number || 'BS-001' }}</div>
        </div>

        <!-- Card Breakdown of Added Sizes -->
        <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-2xl border border-slate-200 dark:border-slate-700 text-xs font-mono space-y-2 text-left">
          <div v-for="item in successData?.updated_items" :key="item.product_variant_size_id" class="flex items-center justify-between py-1 border-b border-slate-200/60 dark:border-slate-700/60 last:border-0 last:pb-0">
            <span class="font-bold text-slate-800 dark:text-slate-200">Size {{ item.size_name }}</span>
            <div class="text-right">
              <span class="text-slate-400 font-mono text-[11px]">{{ item.previous_stock }} → </span>
              <span class="font-black text-slate-900 dark:text-white font-mono text-xs">{{ item.new_stock }}</span>
              <span class="text-emerald-600 dark:text-emerald-400 font-bold ml-1 font-mono text-xs">(+{{ item.added_quantity }})</span>
            </div>
          </div>

          <div class="pt-2 border-t border-slate-300 dark:border-slate-600 flex items-center justify-between font-bold text-slate-900 dark:text-white">
            <span>Total Added</span>
            <span class="font-black text-emerald-600 dark:text-emerald-400 text-sm">+{{ successData?.total_quantity_added }} PCS</span>
          </div>
        </div>

        <!-- Done Action Button -->
        <button
          @click="startSearchNextProduct"
          class="w-full py-3.5 bg-red-600 hover:bg-red-700 active:scale-98 text-white font-black text-xs sm:text-sm rounded-2xl shadow-lg shadow-red-600/30 uppercase tracking-wider transition-all flex items-center justify-center gap-2"
        >
          <span>✓</span>
          <span>DONE (SEARCH NEXT PRODUCT)</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { useAuth } from '../../composables/useAuth';
import api from '../../services/api';

const { isSuperAdmin, user: activeUser } = useAuth();

const searchInputRef = ref(null);
const searchContainerRef = ref(null);

const accessibleStores = ref([]);
const selectedStoreId = ref(1);

const searchQuery = ref('');
const searchSuggestions = ref([]);
const isSearchingSuggestions = ref(false);
const showDropdown = ref(false);
const highlightedIndex = ref(0);
const suggestionRefs = ref({});
let searchDebounceTimer = null;

const loadingProduct = ref(false);
const selectedProduct = ref(null);
const productVariants = ref([]);
const addQuantities = reactive({});
const stockNotes = ref('');

const showConfirmModal = ref(false);
const submitting = ref(false);
const submitError = ref('');
const showSuccessModal = ref(false);
const successData = ref(null);

const selectedStoreName = computed(() => {
  const store = accessibleStores.value.find(s => s.id === selectedStoreId.value);
  return store ? `${store.name} (${store.code})` : 'Main Store';
});

const totalSizesSelected = computed(() => {
  return Object.values(addQuantities).filter(q => typeof q === 'number' && q > 0).length;
});

const totalUnitsToAdd = computed(() => {
  return Object.values(addQuantities).reduce((sum, q) => sum + (typeof q === 'number' && q > 0 ? q : 0), 0);
});

const totalAvailableSizesCount = computed(() => {
  return productVariants.value.reduce((acc, v) => acc + (v.sizes?.length || 0), 0);
});

const confirmItems = computed(() => {
  const items = [];
  productVariants.value.forEach(v => {
    if (v.sizes) {
      v.sizes.forEach(s => {
        const qty = addQuantities[s.id];
        if (typeof qty === 'number' && qty > 0) {
          items.push({
            pvsId: s.id,
            sizeName: s.size_number,
            sku: s.sku || `SKU-${s.id}`,
            quantity: qty,
          });
        }
      });
    }
  });
  return items;
});

async function fetchStores() {
  try {
    const res = await api.get('/stores');
    const payload = res.data || res;
    const items = payload.items || payload.data || (Array.isArray(payload) ? payload : []);
    if (items.length > 0) {
      accessibleStores.value = items;
      selectedStoreId.value = items[0].id;
    } else {
      accessibleStores.value = [{ id: 1, name: 'Main Store', code: 'STR-001' }];
      selectedStoreId.value = 1;
    }
  } catch (e) {
    accessibleStores.value = [{ id: 1, name: 'Main Store', code: 'STR-001' }];
    selectedStoreId.value = 1;
  }
}

function onStoreChange() {
  if (selectedProduct.value) {
    loadProductDetails(selectedProduct.value.id);
  }
}

function onSearchInput() {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
  const q = searchQuery.value.trim();
  if (q.length < 2) {
    searchSuggestions.value = [];
    showDropdown.value = false;
    highlightedIndex.value = 0;
    return;
  }
  searchDebounceTimer = setTimeout(fetchSuggestions, 250);
}

async function fetchSuggestions() {
  const q = searchQuery.value.trim();
  if (q.length < 2) return;
  isSearchingSuggestions.value = true;

  try {
    const res = await api.get('/products/autocomplete', {
      params: { q, store_id: selectedStoreId.value },
    });
    const payload = res.data || res;
    const items = payload.items || payload.data || (Array.isArray(payload) ? payload : []);
    searchSuggestions.value = items;
    showDropdown.value = items.length > 0 || q.length >= 2;
    highlightedIndex.value = 0;
  } catch (err) {
    searchSuggestions.value = [];
    showDropdown.value = false;
  } finally {
    isSearchingSuggestions.value = false;
  }
}

async function selectProductFromSearch(item) {
  showDropdown.value = false;
  searchQuery.value = item.product_name || item.name;
  searchSuggestions.value = [];
  const productId = item.product_id || item.id;
  await loadProductDetails(productId);
}

async function loadProductDetails(productId) {
  loadingProduct.value = true;
  Object.keys(addQuantities).forEach(k => delete addQuantities[k]);

  try {
    const res = await api.get(`/products/${productId}`, {
      params: { store_id: selectedStoreId.value }
    });
    const details = res.data || res;
    selectedProduct.value = details;

    const variants = details.variants || [];
    productVariants.value = variants.map(v => ({
      ...v,
      sizes: (v.sizes || []).map(s => {
        const sizeNum = s.size_number || s.size?.size_number || 'N/A';
        const stock = typeof s.current_stock === 'number' ? s.current_stock : (s.stock_quantity || 0);

        return {
          id: s.id,
          size_number: sizeNum,
          sku: s.sku || `SKU-${s.id}`,
          selling_price: s.selling_price || 0,
          current_stock: stock,
        };
      })
    }));
  } catch (err) {
    alert('Failed to load product sizes and stock details.');
  } finally {
    loadingProduct.value = false;
  }
}

function updateQty(pvsId, delta) {
  const current = addQuantities[pvsId] || 0;
  const next = Math.max(0, current + delta);
  if (next === 0) {
    delete addQuantities[pvsId];
  } else {
    addQuantities[pvsId] = next;
  }
}

function clearSelectedProduct() {
  selectedProduct.value = null;
  productVariants.value = [];
  Object.keys(addQuantities).forEach(k => delete addQuantities[k]);
  searchQuery.value = '';
  focusSearchInput();
}

function clearSearch() {
  searchQuery.value = '';
  searchSuggestions.value = [];
  showDropdown.value = false;
  highlightedIndex.value = 0;
  focusSearchInput();
}

function onKeyDown() {
  if (!showDropdown.value || searchSuggestions.value.length === 0) return;
  highlightedIndex.value = (highlightedIndex.value + 1) % searchSuggestions.value.length;
}

function onKeyUp() {
  if (!showDropdown.value || searchSuggestions.value.length === 0) return;
  highlightedIndex.value = (highlightedIndex.value - 1 + searchSuggestions.value.length) % searchSuggestions.value.length;
}

function onKeyEnter() {
  if (showDropdown.value && searchSuggestions.value.length > 0) {
    const item = searchSuggestions.value[highlightedIndex.value] || searchSuggestions.value[0];
    if (item) {
      selectProductFromSearch(item);
    }
  }
}

function onKeyEsc() {
  showDropdown.value = false;
  highlightedIndex.value = 0;
}

function onInputFocus() {
  if (searchQuery.value.trim().length >= 2 && searchSuggestions.value.length > 0) {
    showDropdown.value = true;
  }
}

function handleClickOutside(event) {
  if (searchContainerRef.value && !searchContainerRef.value.contains(event.target)) {
    showDropdown.value = false;
  }
}

function openConfirmationModal() {
  if (totalUnitsToAdd.value <= 0) return;
  submitError.value = '';
  showConfirmModal.value = true;
}

async function submitStockAdd() {
  if (totalUnitsToAdd.value <= 0 || !selectedProduct.value || submitting.value) return;
  submitting.value = true;
  submitError.value = '';

  try {
    const payloadItems = confirmItems.value.map(i => ({
      product_variant_size_id: i.pvsId,
      quantity: i.quantity,
    }));

    const payload = {
      store_id: selectedStoreId.value,
      product_id: selectedProduct.value.id,
      notes: stockNotes.value.trim() || 'Direct Stock Add',
      items: payloadItems,
    };

    const res = await api.post('/inventory/stock-add', payload);
    const data = res.data || res;
    successData.value = data;

    // Immediately clear pending quantity inputs to prevent double updates
    Object.keys(addQuantities).forEach(k => delete addQuantities[k]);
    stockNotes.value = '';

    showConfirmModal.value = false;
    showSuccessModal.value = true;
  } catch (err) {
    const errData = err.response?.data;
    submitError.value = errData?.message || errData?.error || 'Stock update failed. No stock was changed.';
  } finally {
    submitting.value = false;
  }
}

function startSearchNextProduct() {
  showSuccessModal.value = false;
  successData.value = null;
  selectedProduct.value = null;
  productVariants.value = [];
  Object.keys(addQuantities).forEach(k => delete addQuantities[k]);
  stockNotes.value = '';
  searchQuery.value = '';
  focusSearchInput();
}

function focusSearchInput() {
  nextTick(() => {
    if (searchInputRef.value) {
      searchInputRef.value.focus();
    }
  });
}

onMounted(() => {
  fetchStores();
  document.addEventListener('click', handleClickOutside);
  focusSearchInput();
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>
