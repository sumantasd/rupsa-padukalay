<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6 antialiased font-sans">
    <!-- Breadcrumb & Header Title -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
      <div>
        <div class="text-[11px] text-slate-500 flex items-center gap-1 font-medium">
          <router-link to="/" class="hover:text-red-700">Home</router-link>
          <span>/</span>
          <router-link to="/products" class="hover:text-red-700">Catalog</router-link>
          <span v-if="activeCategoryTitle">/</span>
          <span v-if="activeCategoryTitle" class="text-slate-900 font-bold uppercase">{{ activeCategoryTitle }}</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 mt-1 uppercase tracking-tight">
          {{ activeCategoryTitle ? `${activeCategoryTitle} Collection` : 'All Footwear Collection' }}
        </h1>
      </div>

      <div class="text-xs text-slate-500 font-medium">
        Showing <span class="font-black text-slate-900">{{ filteredProducts.length }}</span> Articles
      </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-8">
      <!-- Desktop Sidebar Filters -->
      <aside class="w-full lg:w-64 space-y-6 shrink-0">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs space-y-5">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="font-bold text-xs uppercase tracking-wider text-slate-900 flex items-center gap-1.5">
              <span>⚙️</span>
              <span>Filter Footwear</span>
            </h3>
            <button @click="resetFilters" class="text-[10px] font-black text-red-600 hover:underline">Reset All</button>
          </div>

          <!-- Search Input -->
          <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-700 uppercase tracking-wide block">Search Article</label>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search by article # or name..."
              class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-red-600 font-medium"
            />
          </div>

          <!-- Category Filter -->
          <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-700 uppercase tracking-wide block">Category</label>
            <select v-model="selectedCategory" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2 text-xs text-slate-800 font-bold focus:ring-2 focus:ring-red-600">
              <option value="">All Footwear Categories</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
          </div>

          <!-- Gender Filter -->
          <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-700 uppercase tracking-wide block">Gender / Audience</label>
            <select v-model="selectedGender" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2 text-xs text-slate-800 font-bold focus:ring-2 focus:ring-red-600">
              <option value="">All Genders</option>
              <option value="men">Men's Collection</option>
              <option value="women">Women's Collection</option>
              <option value="kids">Kids & Junior</option>
              <option value="unisex">Unisex Comfort</option>
            </select>
          </div>

          <!-- Brand Filter -->
          <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-700 uppercase tracking-wide block">Brand</label>
            <select v-model="selectedBrand" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2 text-xs text-slate-800 font-bold focus:ring-2 focus:ring-red-600">
              <option value="">All Brands</option>
              <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
          </div>

          <!-- Sort By -->
          <div class="space-y-1">
            <label class="text-[10px] font-black text-slate-700 uppercase tracking-wide block">Sort By Price</label>
            <select v-model="sortBy" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2 text-xs text-slate-800 font-bold focus:ring-2 focus:ring-red-600">
              <option value="newest">Featured & Newest</option>
              <option value="price_low">Price: Low to High</option>
              <option value="price_high">Price: High to Low</option>
            </select>
          </div>
        </div>
      </aside>

      <!-- Main Product Grid & States -->
      <div class="flex-1 space-y-6">
        
        <!-- 1. SKELETON LOADING STATE (Visual Product Cards Placeholder) -->
        <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <div
            v-for="n in 6"
            :key="n"
            class="bg-white rounded-2xl border border-slate-200 p-4 space-y-3 animate-pulse"
          >
            <div class="bg-slate-200 aspect-square rounded-xl w-full"></div>
            <div class="h-3 bg-slate-200 rounded w-1/3"></div>
            <div class="h-4 bg-slate-200 rounded w-3/4"></div>
            <div class="h-3 bg-slate-200 rounded w-1/2"></div>
            <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
              <div class="h-5 bg-slate-200 rounded w-1/4"></div>
              <div class="h-8 bg-slate-200 rounded-xl w-1/2"></div>
            </div>
          </div>
        </div>

        <!-- 2. ERROR STATE (Clean User-Facing Error Message) -->
        <div v-else-if="error" class="p-12 text-center bg-white rounded-2xl border border-red-200 shadow-sm space-y-4 max-w-md mx-auto">
          <div class="text-4xl">⚠️</div>
          <div class="space-y-1">
            <h3 class="font-black text-slate-900 text-base">Unable to load products right now.</h3>
            <p class="text-xs text-slate-500">Please check your internet connection or try reloading the catalog.</p>
          </div>
          <button
            @click="loadData"
            class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white font-black text-xs rounded-xl shadow-md transition-colors inline-flex items-center gap-1.5"
          >
            <span>🔄</span>
            <span>Try Again</span>
          </button>
        </div>

        <!-- 3. EMPTY CATEGORY / ZERO PRODUCTS STATE -->
        <div v-else-if="filteredProducts.length === 0" class="p-12 text-center bg-white rounded-2xl border border-slate-200 shadow-sm space-y-4 max-w-md mx-auto">
          <div class="text-4xl">👟</div>
          <div class="space-y-1">
            <h3 class="font-black text-slate-900 text-base">No products available in this category.</h3>
            <p class="text-xs text-slate-500">No footwear articles match your current search filters.</p>
          </div>
          <button
            @click="resetFilters"
            class="px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-black text-xs rounded-xl transition-colors inline-flex items-center gap-1.5"
          >
            <span>✨</span>
            <span>Clear Filters & Show All</span>
          </button>
        </div>

        <!-- 4. REAL PRODUCT GRID (Using Real Database Footwear Products) -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <div
            v-for="product in filteredProducts"
            :key="product.id"
            class="bg-white rounded-2xl border border-slate-200/90 shadow-xs hover:shadow-xl hover:border-emerald-600/40 transition-all duration-300 flex flex-col justify-between overflow-hidden group"
          >
            <div class="relative bg-slate-100 aspect-square overflow-hidden flex items-center justify-center p-3">
              <img
                :src="getProductImage(product)"
                :alt="product.name"
                class="h-full w-full object-cover object-center group-hover:scale-105 transition-transform duration-500"
                loading="lazy"
              />
              <span v-if="getDiscountPercent(product)" class="absolute top-2.5 left-2.5 bg-red-600 text-white font-black text-[10px] px-2 py-0.5 rounded-md shadow-xs">
                {{ getDiscountPercent(product) }}% OFF
              </span>
              <span class="absolute top-2.5 right-2.5 bg-slate-900/80 backdrop-blur-xs text-white font-mono font-bold text-[9px] px-1.5 py-0.5 rounded">
                ART: {{ product.article_number || product.code || 'RP-' + product.id }}
              </span>
            </div>

            <div class="p-4 space-y-3 flex-1 flex flex-col justify-between text-xs">
              <div>
                <div class="text-[9px] font-extrabold text-red-600 uppercase tracking-widest">
                  {{ product.brand?.name || 'RUPSA FOOTWEAR' }}
                </div>
                <h3 class="font-bold text-slate-900 text-xs sm:text-sm line-clamp-2 leading-tight mt-0.5">
                  {{ product.name }}
                </h3>
                <p class="text-[11px] text-slate-500 mt-1">
                  Category: {{ product.category?.name || 'Footwear' }}
                </p>
              </div>

              <div class="space-y-2 pt-2 border-t border-slate-100">
                <div class="flex items-baseline gap-2">
                  <span class="font-black text-slate-900 text-base">₹{{ product.selling_price || product.mrp }}</span>
                  <span v-if="product.mrp && product.mrp > product.selling_price" class="text-xs text-slate-400 line-through">
                    ₹{{ product.mrp }}
                  </span>
                </div>

                <!-- BUY ON WHATSAPP ACTION BUTTON -->
                <button
                  @click="buyOnWhatsApp(product)"
                  class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-xl text-xs transition-colors flex items-center justify-center gap-1.5 shadow-md shadow-emerald-600/20"
                >
                  <span>💬</span>
                  <span>BUY ON WHATSAPP</span>
                </button>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useWhatsApp } from '../../composables/useWhatsApp';
import api from '../../services/api';

const route = useRoute();
const { buyOnWhatsApp } = useWhatsApp();

const products = ref([]);
const categories = ref([]);
const brands = ref([]);

const loading = ref(true);
const error = ref(false);

const searchQuery = ref('');
const selectedCategory = ref('');
const selectedGender = ref('');
const selectedBrand = ref('');
const sortBy = ref('newest');

const activeCategoryTitle = computed(() => {
  const param = route.params.category;
  if (!param) return '';
  if (param === 'men') return 'Men\'s Footwear';
  if (param === 'women') return 'Women\'s Footwear';
  if (param === 'kids') return 'Kids Collection';
  if (param === 'others') return 'Accessories & Slippers';
  
  const found = categories.value.find(c => c.slug === param || c.name.toLowerCase().replace(/\s+/g, '-') === param);
  if (found) return found.name;
  
  return param.replace(/-/g, ' ').toUpperCase();
});

function getProductImage(product) {
  if (product.primary_image_url) return product.primary_image_url;
  if (product.image_url) return product.image_url;
  return 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=600&q=80';
}

function getDiscountPercent(product) {
  if (product.mrp && product.selling_price && product.mrp > product.selling_price) {
    return Math.round(((product.mrp - product.selling_price) / product.mrp) * 100);
  }
  return 0;
}

function resetFilters() {
  searchQuery.value = '';
  selectedCategory.value = '';
  selectedGender.value = '';
  selectedBrand.value = '';
  sortBy.value = 'newest';
}

const filteredProducts = computed(() => {
  let list = [...products.value];

  // Route param category filter
  const routeParam = route.params.category;
  if (routeParam) {
    if (routeParam === 'men') {
      list = list.filter(p => p.gender === 'men' || p.gender === 'unisex');
    } else if (routeParam === 'women') {
      list = list.filter(p => p.gender === 'women' || p.gender === 'unisex');
    } else if (routeParam === 'kids') {
      list = list.filter(p => p.gender === 'kids' || p.gender === 'boys' || p.gender === 'girls');
    } else if (routeParam === 'others') {
      list = list.filter(p => p.category?.name?.toLowerCase().includes('slipper') || p.category?.name?.toLowerCase().includes('others'));
    } else {
      list = list.filter(p => {
        const catSlug = p.category?.slug || p.category?.name?.toLowerCase().replace(/\s+/g, '-');
        return catSlug === routeParam;
      });
    }
  }

  // Search filter
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase();
    list = list.filter(p =>
      (p.name && p.name.toLowerCase().includes(q)) ||
      (p.article_number && p.article_number.toLowerCase().includes(q)) ||
      (p.code && p.code.toLowerCase().includes(q))
    );
  }

  // Sidebar Category Filter
  if (selectedCategory.value) {
    list = list.filter(p => p.category_id == selectedCategory.value || p.category?.id == selectedCategory.value);
  }

  // Sidebar Gender Filter
  if (selectedGender.value) {
    if (selectedGender.value === 'kids') {
      list = list.filter(p => p.gender === 'kids' || p.gender === 'boys' || p.gender === 'girls');
    } else {
      list = list.filter(p => p.gender === selectedGender.value || p.gender === 'unisex');
    }
  }

  // Sidebar Brand Filter
  if (selectedBrand.value) {
    list = list.filter(p => p.brand_id == selectedBrand.value || p.brand?.id == selectedBrand.value);
  }

  // Sorting
  if (sortBy.value === 'price_low') {
    list.sort((a, b) => (a.selling_price || a.mrp || 0) - (b.selling_price || b.mrp || 0));
  } else if (sortBy.value === 'price_high') {
    list.sort((a, b) => (b.selling_price || b.mrp || 0) - (a.selling_price || a.mrp || 0));
  }

  return list;
});

async function loadData() {
  loading.value = true;
  error.value = false;
  try {
    const [pRes, cRes, bRes] = await Promise.allSettled([
      api.get('/public/products'),
      api.get('/public/categories'),
      api.get('/public/brands'),
    ]);

    if (pRes.status === 'fulfilled') {
      const pData = pRes.value.data || pRes.value;
      products.value = Array.isArray(pData) ? pData : [];
    } else {
      error.value = true;
    }

    if (cRes.status === 'fulfilled') {
      const cData = cRes.value.data || cRes.value;
      categories.value = Array.isArray(cData) ? cData : [];
    }

    if (bRes.status === 'fulfilled') {
      const bData = bRes.value.data || bRes.value;
      brands.value = Array.isArray(bData) ? bData : [];
    }
  } catch (err) {
    console.error('Failed to load public catalog:', err);
    error.value = true;
  } finally {
    loading.value = false;
  }
}

watch(() => route.params.category, () => {
  resetFilters();
});

onMounted(() => {
  loadData();
});
</script>
