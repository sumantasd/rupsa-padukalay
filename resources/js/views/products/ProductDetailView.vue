<template>
  <div class="space-y-6 max-w-6xl mx-auto pb-16 antialiased font-sans">
    <!-- Top Header Navigation -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
      <div class="flex items-center gap-3">
        <router-link
          to="/admin/products"
          class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-extrabold text-xs transition-colors"
        >
          ← Back to Catalog
        </router-link>
        <div>
          <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">{{ product?.name || 'Footwear Article Details' }}</h1>
          <p class="font-mono text-xs font-bold text-red-600 mt-0.5">ART: {{ product?.article_number }}</p>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <router-link
          v-if="product && hasPermission('products.edit')"
          :to="`/admin/products/${product.id}/edit`"
          class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold text-xs transition-colors flex items-center gap-1.5"
        >
          <span>✏️</span>
          <span>Edit Article</span>
        </router-link>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12 text-slate-400 font-bold text-xs animate-pulse">
      Loading article details...
    </div>

    <div v-else-if="!product" class="text-center py-12 text-slate-400 font-bold">
      Product not found.
    </div>

    <div v-else class="space-y-6">
      <!-- 1. Product Summary Hero Card -->
      <div class="bg-white rounded-2xl border border-slate-200/90 p-6 shadow-xs grid grid-cols-1 md:grid-cols-12 gap-6">
        <div class="md:col-span-4 flex flex-col items-center justify-center p-4 bg-slate-50 border border-slate-200 rounded-2xl">
          <div class="h-48 w-48 rounded-xl bg-white flex items-center justify-center text-6xl shadow-sm overflow-hidden">
            <img v-if="product.primary_image_url || product.image_url" :src="product.primary_image_url || product.image_url" class="h-full w-full object-cover" />
            <span v-else>👟</span>
          </div>
          <span
            :class="[
              'mt-3 px-3 py-1 rounded-full text-[10px] font-extrabold uppercase',
              product.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'
            ]"
          >
            {{ product.is_active ? 'Active Status' : 'Inactive Status' }}
          </span>
        </div>

        <div class="md:col-span-8 space-y-4 text-xs">
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 p-4 bg-slate-50 rounded-xl">
            <div v-if="moduleStore.isModuleEnabled('productFieldCategory')">
              <span class="text-slate-400 block text-[10px] font-bold uppercase">Category</span>
              <span class="font-black text-slate-900 text-sm">{{ product.category?.name || 'N/A' }}</span>
            </div>
            <div>
              <span class="text-slate-400 block text-[10px] font-bold uppercase">Brand Partner</span>
              <span class="font-black text-slate-900 text-sm">{{ product.brand?.name || 'N/A' }}</span>
            </div>
            <div v-if="moduleStore.isModuleEnabled('productFieldGender')">
              <span class="text-slate-400 block text-[10px] font-bold uppercase">Gender Target</span>
              <span class="font-black text-purple-700 text-sm uppercase">{{ product.gender || 'Unisex' }}</span>
            </div>
            <div v-if="moduleStore.isModuleEnabled('productFieldUpperMaterial')">
              <span class="text-slate-400 block text-[10px] font-bold uppercase">Upper Material</span>
              <span class="font-bold text-slate-800">{{ product.upper_material || 'N/A' }}</span>
            </div>
            <div v-if="moduleStore.isModuleEnabled('productFieldSoleMaterial')">
              <span class="text-slate-400 block text-[10px] font-bold uppercase">Sole Material</span>
              <span class="font-bold text-slate-800">{{ product.sole_material || 'N/A' }}</span>
            </div>
            <div>
              <span class="text-slate-400 block text-[10px] font-bold uppercase">HSN Code</span>
              <span class="font-mono font-bold text-slate-800">{{ product.hsn_code?.code || 'N/A' }}</span>
            </div>
          </div>

          <div v-if="product.description" class="space-y-1">
            <span class="font-bold text-slate-700 block text-[11px]">Product Specs & Description</span>
            <p class="text-slate-600 leading-relaxed bg-slate-50 p-3 rounded-xl border border-slate-100">
              {{ product.description }}
            </p>
          </div>
        </div>
      </div>

      <!-- 2. Variants & Size SKUs Table -->
      <div class="bg-white rounded-2xl border border-slate-200/90 p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div class="flex items-center gap-2">
            <span class="text-base">{{ moduleStore.isModuleEnabled('productFieldColor') ? '🎨' : '📏' }}</span>
            <h2 class="text-xs font-black text-slate-900 uppercase tracking-wider">
              {{ moduleStore.isModuleEnabled('productFieldColor') ? 'Color Variants & Size SKUs Matrix' : 'Size SKUs Matrix' }}
            </h2>
          </div>
          <span class="text-xs font-bold text-slate-500 bg-slate-100 px-3 py-1 rounded-xl">
            <template v-if="moduleStore.isModuleEnabled('productFieldColor')">
              {{ product.variants?.length || 0 }} Colors / {{ totalSkus }} Size SKUs
            </template>
            <template v-else>
              {{ totalSkus }} Size SKUs
            </template>
          </span>
        </div>

        <div v-for="variant in product.variants" :key="variant.id" class="border border-slate-200 rounded-xl p-4 space-y-3 bg-slate-50/50">
          <div v-if="moduleStore.isModuleEnabled('productFieldColor')" class="flex items-center gap-2 font-black text-slate-900 text-sm">
            <span
              class="h-4 w-4 rounded-full border border-slate-300 shadow-2xs"
              :style="{ backgroundColor: variant.color?.hex_code || '#000' }"
            ></span>
            <span>{{ variant.color?.name || variant.color_name || 'Standard Color' }} ({{ variant.color?.code || variant.color_code || 'DEF' }})</span>
          </div>

          <div class="overflow-x-auto bg-white rounded-xl border border-slate-200">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-100 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase">
                <tr>
                  <th class="px-4 py-2.5">Size (IND)</th>
                  <th class="px-4 py-2.5">Available Stock</th>
                  <th class="px-4 py-2.5">MRP (₹)</th>
                  <th class="px-4 py-2.5">Selling Price (₹)</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                <tr v-for="s in variant.sizes" :key="s.id">
                  <td class="px-4 py-2 font-black text-slate-900">
                    IND {{ s.size_number || s.size?.size_number || s.size_id }}
                  </td>
                  <td class="px-4 py-2 font-black text-emerald-700">
                    {{ s.stock_quantity ?? 0 }} prs
                  </td>
                  <td class="px-4 py-2 font-bold text-slate-400 line-through">₹{{ s.mrp }}</td>
                  <td class="px-4 py-2 font-black text-slate-900">₹{{ s.selling_price }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useAuth } from '../../composables/useAuth';
import { useModuleStore } from '../../stores/moduleStore';
import api from '../../services/api';

const route = useRoute();
const { hasPermission } = useAuth();
const moduleStore = useModuleStore();

const product = ref(null);
const loading = ref(false);

const totalSkus = computed(() => {
  if (!product.value || !product.value.variants) return 0;
  return product.value.variants.reduce((acc, v) => acc + (v.sizes?.length || 0), 0);
});

async function fetchProductDetails() {
  loading.value = true;
  try {
    const response = await api.get(`/products/${route.params.id}`);
    product.value = response.data;
  } catch (err) {
    console.error('Failed to load product details:', err);
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  if (!moduleStore.initialized) {
    moduleStore.fetchSettings();
  }
  fetchProductDetails();
});
</script>
