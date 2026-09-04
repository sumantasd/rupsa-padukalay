<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-12 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Footwear Products & SKUs</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Master footwear catalog, article numbers, sizing matrix, color variants and pricing.
        </p>
      </div>
      <router-link
        v-if="hasPermission('products.create')"
        to="/admin/products/create"
        class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-black shadow-md shadow-red-600/20 transition-all flex items-center gap-2 self-start sm:self-auto"
      >
        <span>👟</span>
        <span>Add New Product</span>
      </router-link>
    </div>

    <!-- Search & Filters -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex flex-col md:flex-row items-center justify-between gap-3">
      <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
        <!-- Search Input -->
        <div class="relative w-full sm:w-64">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
          <input
            v-model="searchQuery"
            @input="fetchProducts"
            type="text"
            placeholder="Search article number or product..."
            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white transition-all"
          />
        </div>

        <!-- Category Filter -->
        <select
          v-model="selectedCategory"
          @change="fetchProducts"
          class="w-full sm:w-auto bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600"
        >
          <option value="">All Categories</option>
          <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
        </select>

        <!-- Brand Filter -->
        <select
          v-model="selectedBrand"
          @change="fetchProducts"
          class="w-full sm:w-auto bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600"
        >
          <option value="">All Brands</option>
          <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
        </select>
      </div>

      <div class="flex items-center gap-3 w-full md:w-auto justify-end">
        <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer">
          <input
            v-model="activeOnly"
            @change="fetchProducts"
            type="checkbox"
            class="rounded text-red-600 focus:ring-red-600"
          />
          <span>Active Only</span>
        </label>
        <span class="text-xs font-extrabold text-slate-400">|</span>
        <span class="text-xs font-black text-slate-700">{{ products.length }} Articles</span>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="bg-white p-8 rounded-2xl border border-slate-200 text-center text-slate-400 font-bold text-xs shadow-xs">
      <span class="animate-pulse">Loading footwear catalog...</span>
    </div>

    <!-- Empty State -->
    <div v-else-if="products.length === 0" class="bg-white p-12 rounded-2xl border border-slate-200 text-center text-slate-500 font-bold text-xs shadow-xs">
      No footwear articles found. Click "Add New Product" to create one.
    </div>

    <!-- Products Content Container (Mobile Cards + Desktop Table) -->
    <template v-else>
      <!-- MOBILE PRODUCT CARDS VIEW (< md) -->
      <div class="space-y-3 md:hidden">
        <MobileListCard
          v-for="p in products"
          :key="p.id"
          :title="p.name"
          :subtitle="'ART: ' + (p.article_number || 'RP-' + p.id) + ' • ' + (p.brand?.name || 'RUPSA')"
          :status="p.is_active ? 'Active' : 'Inactive'"
          :status-type="p.is_active ? 'success' : 'neutral'"
          :metric="p.category?.name || 'Footwear'"
        >
          <div class="flex items-center gap-3 py-1">
            <div class="h-12 w-12 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-xl shrink-0 overflow-hidden">
              <img v-if="p.primary_image_url || p.image_url" :src="p.primary_image_url || p.image_url" :alt="p.name" class="w-full h-full object-cover" />
              <span v-else>👞</span>
            </div>
            <div class="space-y-0.5 text-xs font-mono">
              <div>Gender: <strong class="text-slate-900 uppercase font-bold">{{ p.gender || 'Unisex' }}</strong></div>
              <div>Variants: <strong class="text-red-600 font-bold">{{ p.variants?.length || 0 }} Color(s)</strong></div>
            </div>
          </div>

          <template #actions>
            <router-link
              :to="'/admin/products/' + p.id"
              class="px-3 py-1.5 bg-slate-900 text-white rounded-lg font-bold text-xs cursor-pointer flex items-center gap-1"
            >
              <span>🔍 View</span>
            </router-link>
            <router-link
              v-if="hasPermission('products.edit')"
              :to="'/admin/products/' + p.id + '/edit'"
              class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-300 rounded-lg font-bold text-xs cursor-pointer flex items-center gap-1"
            >
              <span>✏️ Edit</span>
            </router-link>
            <button
              v-if="hasPermission('products.delete')"
              @click="confirmDeleteProduct(p)"
              class="px-2.5 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 rounded-lg font-bold text-xs cursor-pointer flex items-center gap-1"
            >
              <span>🗑️ Delete</span>
            </button>
          </template>
        </MobileListCard>
      </div>

      <!-- DESKTOP DATA TABLE (>= md) -->
      <div class="hidden md:block bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
        <div class="w-full overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-wider">
              <tr>
                <th class="px-5 py-3.5">Article / Product</th>
                <th class="px-5 py-3.5">Category</th>
                <th class="px-5 py-3.5">Brand</th>
                <th class="px-5 py-3.5">Gender</th>
                <th class="px-5 py-3.5">Variants & SKUs</th>
                <th class="px-5 py-3.5">Status</th>
                <th class="px-5 py-3.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
              <tr v-for="p in products" :key="p.id" class="hover:bg-slate-50/80 transition-colors">
              <!-- Article / Name -->
              <td class="px-5 py-3.5">
                <div class="flex items-center gap-3">
                  <div class="h-10 w-10 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-lg shrink-0 overflow-hidden">
                    <img v-if="p.primary_image_url || p.image_url" :src="p.primary_image_url || p.image_url" class="h-full w-full object-cover" />
                    <span v-else>👟</span>
                  </div>
                  <div>
                    <router-link :to="`/admin/products/${p.id}`" class="font-black text-slate-900 leading-tight hover:text-red-600">
                      {{ p.name }}
                    </router-link>
                    <div class="font-mono text-[10px] font-bold text-red-600 mt-0.5">
                      ART: {{ p.article_number }}
                    </div>
                  </div>
                </div>
              </td>

              <!-- Category -->
              <td class="px-5 py-3.5">
                <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 text-[10px] font-bold">
                  {{ p.category?.name || 'Uncategorized' }}
                </span>
              </td>

              <!-- Brand -->
              <td class="px-5 py-3.5 font-bold text-slate-800">
                {{ p.brand?.name || 'N/A' }}
              </td>

              <!-- Gender -->
              <td class="px-5 py-3.5">
                <span class="px-2 py-0.5 rounded bg-purple-50 text-purple-700 text-[10px] font-extrabold uppercase border border-purple-100">
                  {{ p.gender || 'Unisex' }}
                </span>
              </td>

              <!-- Variants & SKUs -->
              <td class="px-5 py-3.5">
                <div class="space-y-0.5">
                  <span class="font-bold text-slate-800 text-[11px]">
                    {{ p.variants?.length || 0 }} Colors / {{ getTotalSkus(p) }} SKUs
                  </span>
                  <div v-if="p.variants?.length" class="flex items-center gap-1 flex-wrap">
                    <span
                      v-for="v in p.variants"
                      :key="v.id"
                      class="h-3 w-3 rounded-full border border-slate-300 inline-block shadow-2xs"
                      :style="{ backgroundColor: v.color?.hex_code || '#000' }"
                      :title="v.color?.name"
                    ></span>
                  </div>
                </div>
              </td>

              <!-- Status -->
              <td class="px-5 py-3.5">
                <span
                  :class="[
                    'px-2 py-0.5 rounded text-[10px] font-extrabold uppercase',
                    p.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'
                  ]"
                >
                  {{ p.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>

              <!-- Actions -->
              <td class="px-5 py-3.5 text-right space-x-1.5 whitespace-nowrap">
                <router-link
                  :to="`/admin/products/${p.id}`"
                  class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg font-bold text-[11px] transition-colors inline-block"
                >
                  View
                </router-link>
                <router-link
                  v-if="hasPermission('products.edit')"
                  :to="`/admin/products/${p.id}/edit`"
                  class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg font-bold text-[11px] transition-colors inline-block"
                >
                  Edit
                </router-link>
                <button
                  v-if="hasPermission('products.edit')"
                  @click="toggleStatus(p)"
                  :class="[
                    'px-2.5 py-1 rounded-lg font-bold text-[11px] transition-colors',
                    p.is_active ? 'bg-amber-50 hover:bg-amber-100 text-amber-800' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700'
                  ]"
                >
                  {{ p.is_active ? 'Deactivate' : 'Activate' }}
                </button>
                <button
                  v-if="hasPermission('products.edit')"
                  @click="confirmDeleteProduct(p)"
                  class="px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-700 rounded-lg font-bold text-[11px] transition-colors inline-block"
                >
                  🗑️ Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    </template>

    <!-- PRODUCT DELETE CONFIRMATION MODAL -->
    <div v-if="productToDelete" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-md w-full p-6 space-y-4 text-xs">
        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
          <div class="flex items-center gap-2">
            <span class="text-xl">⚠️</span>
            <span class="font-black text-sm text-slate-900 uppercase">Confirm Product Deletion</span>
          </div>
          <button @click="productToDelete = null; deleteError = null" class="text-slate-400 hover:text-slate-800 font-bold">✕</button>
        </div>

        <div class="space-y-2">
          <p class="text-slate-600 font-medium">
            Are you sure you want to delete this footwear product?
          </p>
          <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-1">
            <div class="font-black text-slate-900 text-sm">{{ productToDelete.name }}</div>
            <div class="font-mono text-[11px] font-bold text-red-600">ART: {{ productToDelete.article_number }}</div>
            <div class="text-[11px] text-slate-500 font-bold">Brand: {{ productToDelete.brand?.name || 'N/A' }} | Category: {{ productToDelete.category?.name || 'N/A' }}</div>
          </div>

          <!-- Error Alert if deletion is blocked -->
          <div v-if="deleteError" class="p-3 bg-red-50 border border-red-200 rounded-xl text-red-800 text-[11px] font-bold space-y-2">
            <div>{{ deleteError }}</div>
            <button
              v-if="productToDelete.is_active"
              @click="deactivateAndClose(productToDelete)"
              class="w-full py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-bold text-center transition-colors"
            >
              Deactivate Product Instead
            </button>
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-slate-200">
          <button
            @click="productToDelete = null; deleteError = null"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold"
          >
            Cancel
          </button>
          <button
            @click="executeDeleteProduct"
            :disabled="deleting"
            class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black shadow-md flex items-center gap-1"
          >
            <span>{{ deleting ? 'Deleting...' : 'Confirm Delete' }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useAuth } from '../../composables/useAuth';
import api from '../../services/api';
import MobileListCard from '../../components/ui/MobileListCard.vue';

const { hasPermission } = useAuth();

const products = ref([]);
const categories = ref([]);
const brands = ref([]);

const loading = ref(false);
const searchQuery = ref('');
const selectedCategory = ref('');
const selectedBrand = ref('');
const activeOnly = ref(false);

function getTotalSkus(product) {
  if (!product.variants) return 0;
  return product.variants.reduce((acc, v) => acc + (v.sizes?.length || 0), 0);
}

async function fetchProducts() {
  loading.value = true;
  try {
    const params = {};
    if (searchQuery.value) params.search = searchQuery.value;
    if (selectedCategory.value) params.category_id = selectedCategory.value;
    if (selectedBrand.value) params.brand_id = selectedBrand.value;
    if (activeOnly.value) params.active_only = true;

    const response = await api.get('/products', { params });
    products.value = response.data || [];
  } catch (err) {
    console.error('Failed to fetch products:', err);
  } finally {
    loading.value = false;
  }
}

async function fetchDropdownMasters() {
  try {
    const [cRes, bRes] = await Promise.all([
      api.get('/categories'),
      api.get('/brands'),
    ]);
    categories.value = cRes.data || [];
    brands.value = bRes.data || [];
  } catch (err) {
    console.error('Failed to load master dropdown data:', err);
  }
}

async function toggleStatus(product) {
  try {
    await api.patch(`/products/${product.id}/status`);
    fetchProducts();
  } catch (err) {
    alert('Failed to toggle product status.');
  }
}

const productToDelete = ref(null);
const deleting = ref(false);
const deleteError = ref(null);

function confirmDeleteProduct(product) {
  productToDelete.value = product;
  deleteError.value = null;
}

async function executeDeleteProduct() {
  if (!productToDelete.value) return;
  deleting.value = true;
  deleteError.value = null;
  try {
    await api.delete(`/products/${productToDelete.value.id}`);
    productToDelete.value = null;
    fetchProducts();
  } catch (err) {
    console.error('Failed to delete product:', err);
    deleteError.value = err.response?.data?.message || 'Failed to delete product because dependent transactional records exist.';
  } finally {
    deleting.value = false;
  }
}

async function deactivateAndClose(product) {
  await toggleStatus(product);
  productToDelete.value = null;
  deleteError.value = null;
}

onMounted(() => {
  fetchProducts();
  fetchDropdownMasters();
});
</script>
