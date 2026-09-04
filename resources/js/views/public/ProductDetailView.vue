<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-12">
    <!-- Breadcrumb -->
    <div class="text-[11px] text-slate-500 flex items-center gap-1 font-medium border-b border-slate-200 pb-4">
      <router-link to="/" class="hover:text-red-700">Home</router-link>
      <span>/</span>
      <span class="text-slate-900 font-bold">{{ product.name || 'Footwear Article' }}</span>
    </div>

    <!-- Product Showcase & Selection -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
      <!-- Left: Image Gallery -->
      <div class="space-y-4">
        <div class="h-96 bg-white rounded-3xl border border-slate-200 shadow-xs flex items-center justify-center text-9xl relative overflow-hidden">
          <img v-if="product.image_url" :src="product.image_url" class="h-full w-full object-cover" />
          <span v-else class="select-none">👞</span>
          <span v-if="product.discount" class="absolute top-4 left-4 bg-red-700 text-white font-extrabold text-xs px-3 py-1 rounded-full shadow-sm">
            {{ product.discount }}
          </span>
        </div>
      </div>

      <!-- Right: Product Purchase Details -->
      <div class="space-y-6">
        <div>
          <span class="text-xs font-mono font-bold text-red-700 bg-red-50 border border-red-200 px-2.5 py-1 rounded-md">
            ART: {{ product.article_number || 'RP-805' }}
          </span>
          <h1 class="text-2xl sm:text-3xl font-black text-slate-900 mt-2">{{ product.name }}</h1>
          <p class="text-xs text-slate-500 mt-1">Category: {{ product.category }} | Genuine Footwear Guarantee</p>
        </div>

        <!-- Price Section -->
        <div class="p-4 rounded-2xl bg-slate-100 border border-slate-200 flex items-center gap-4">
          <span class="text-2xl font-black text-slate-900">₹{{ formatPrice(product.selling_price) }}</span>
          <span v-if="product.mrp && product.mrp > product.selling_price" class="text-sm text-slate-400 line-through">₹{{ formatPrice(product.mrp) }}</span>
          <span class="text-xs font-bold text-emerald-600 bg-emerald-100 px-2 py-0.5 rounded">Inclusive of all GST Taxes</span>
        </div>

        <!-- Size Selector -->
        <div class="space-y-2">
          <div class="flex items-center justify-between text-xs">
            <span class="font-bold text-slate-900 uppercase">Select Shoe Size (IND)</span>

          </div>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="sz in availableSizes"
              :key="sz"
              @click="selectedSize = sz"
              :class="[
                'w-12 h-12 rounded-xl text-xs font-extrabold border transition-all flex items-center justify-center',
                selectedSize === sz ? 'bg-red-700 text-white border-red-700 shadow-md' : 'bg-white text-slate-800 border-slate-300 hover:border-red-600'
              ]"
            >
              {{ sz }}
            </button>
          </div>
        </div>

        <!-- Stock Availability -->
        <div class="flex items-center gap-2 text-xs font-bold text-emerald-600">
          <span>✅ In Stock</span>
          <span class="text-slate-400">|</span>
          <span class="text-slate-600">Available for Direct WhatsApp Ordering</span>
        </div>

        <!-- BUY ON WHATSAPP ACTION BUTTON (Replaces Add to Cart) -->
        <div class="pt-2">
          <button
            @click="buyOnWhatsApp(product, '', selectedSize)"
            class="w-full py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs shadow-lg shadow-emerald-600/30 transition-all flex items-center justify-center gap-2"
          >
            <span>💬</span>
            <span>BUY ON WHATSAPP</span>
          </button>
        </div>

        <!-- Accordion Info Tabs -->
        <div class="border-t border-slate-200 pt-6 space-y-4 text-xs">
          <div>
            <h3 class="font-bold text-slate-900 mb-1">Product Description</h3>
            <p class="text-slate-600 leading-relaxed">{{ product.description }}</p>
          </div>
          <div>
            <h3 class="font-bold text-slate-900 mb-1">Material & Care</h3>
            <p class="text-slate-600 leading-relaxed">{{ product.care }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useWhatsApp } from '../../composables/useWhatsApp';
import api from '../../services/api';

const route = useRoute();
const { buyOnWhatsApp } = useWhatsApp();

const selectedSize = ref('08');
const availableSizes = ['06', '07', '08', '09', '10', '11'];

const product = ref({
  article_number: 'RP-805',
  name: 'Executive Oxford Genuine Leather Shoe',
  category: "Men's Executive Leather",
  selling_price: 1792.00,
  mrp: 2240.00,
  discount: '20% OFF',
  image_url: '',
  description: 'Handcrafted executive leather oxford built with full-grain leather uppers, anti-skid TPR soles, and cushioned arch-support insoles for all-day formal comfort.',
  care: 'Clean with a soft damp cloth. Use neutral leather polish for natural shine. Store in shoe bag when not in use.',
});

function formatPrice(val) {
  return Number(val || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

onMounted(async () => {
  const id = route.params.id;
  try {
    const res = await api.get(`/public/products`);
    const list = res.data || res;
    if (Array.isArray(list)) {
      const p = list.find(x => x.id == id);
      if (p) {
        product.value = {
          article_number: p.article_number || 'RP-' + p.id,
          name: p.name,
          category: p.category?.name || 'Footwear',
          selling_price: p.selling_price || 1792.00,
          mrp: p.mrp || 2240.00,
          discount: '15% OFF',
          image_url: p.primary_image_url || p.image_url || '',
          description: p.description || product.value.description,
          care: product.value.care,
        };
      }
    }
  } catch (e) {
    // Keep fallback
  }
});
</script>
