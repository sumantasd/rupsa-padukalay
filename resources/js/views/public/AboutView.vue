<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-16 antialiased font-sans">
    <!-- A. HERO SECTION -->
    <div class="relative rounded-3xl overflow-hidden bg-slate-950 text-white min-h-[360px] sm:min-h-[420px] flex items-center justify-center border border-slate-800 shadow-2xl">
      <!-- Background Image Overlay -->
      <img
        src="https://images.unsplash.com/photo-1549298916-b41d501d3772?auto=format&fit=crop&w=1800&q=80"
        alt="RUPSA PADUKALAYA Footwear Craftsmanship"
        class="absolute inset-0 w-full h-full object-cover opacity-35"
        loading="eager"
      />
      <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/60 to-transparent"></div>

      <div class="relative z-10 text-center max-w-3xl mx-auto px-6 py-12 space-y-4">
        <span class="inline-block px-3.5 py-1 rounded-full bg-red-600/30 text-red-400 text-[10px] sm:text-xs font-black uppercase tracking-widest border border-red-600/40 backdrop-blur-xs">
          HERITAGE & CRAFTSMANSHIP
        </span>
        <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black tracking-tight text-white uppercase">
          ABOUT {{ contactInfo.business_name || 'RUPSA PADUKALAYA' }}
        </h1>
        <p class="text-sm sm:text-lg text-slate-200 font-medium max-w-xl mx-auto leading-relaxed">
          Step into comfort, style and confidence. Crafted for everyday durability and authentic footwear quality.
        </p>
      </div>
    </div>

    <!-- B. OUR STORY -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
      <div class="relative rounded-3xl overflow-hidden shadow-xl border border-slate-200/80 bg-slate-100 aspect-4/3 sm:aspect-16/10">
        <img
          src="https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?auto=format&fit=crop&w=1200&q=80"
          alt="Our Story - Rupsa Padukalaya Footwear Store"
          class="w-full h-full object-cover"
          loading="lazy"
        />
        <div class="absolute bottom-4 left-4 bg-slate-900/90 text-white backdrop-blur-md px-4 py-2 rounded-2xl border border-slate-700/80 text-xs font-bold flex items-center gap-2">
          <span>✨</span>
          <span>Quality Footwear Since Day One</span>
        </div>
      </div>

      <div class="space-y-6">
        <div class="space-y-2">
          <span class="text-xs font-black text-red-600 uppercase tracking-widest block">WHO WE ARE</span>
          <h2 class="text-2xl sm:text-4xl font-black text-slate-900 tracking-tight uppercase">OUR STORY</h2>
        </div>
        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed font-medium">
          Welcome to <strong>{{ contactInfo.business_name || 'RUPSA PADUKALAYA' }}</strong> — your trusted footwear destination dedicated to combining authentic Indian craftsmanship, ergonomic arch support, and modern contemporary fashion.
        </p>
        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed font-medium">
          From executive formal oxfords and genuine leather brogues to festive ethnic sandals, daily ortho comfort slippers, and high-performance athletic sneakers, we carefully curate every article to deliver uncompromised quality and long-lasting durability.
        </p>

        <div class="grid grid-cols-2 gap-4 pt-2">
          <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200">
            <span class="text-2xl font-black text-slate-900 block font-mono">10,000+</span>
            <span class="text-[11px] font-bold text-slate-500 uppercase">Happy Customers</span>
          </div>
          <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200">
            <span class="text-2xl font-black text-slate-900 block font-mono">100%</span>
            <span class="text-[11px] font-bold text-slate-500 uppercase">Genuine Products</span>
          </div>
        </div>
      </div>
    </div>

    <!-- C. OUR PROMISE -->
    <div class="space-y-8">
      <div class="text-center max-w-xl mx-auto space-y-2">
        <span class="text-xs font-black text-red-600 uppercase tracking-widest block">OUR COMMITMENT</span>
        <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight uppercase">OUR PROMISE TO YOU</h2>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="p-6 bg-white rounded-3xl border border-slate-200 shadow-sm hover:border-red-600/50 transition-all space-y-3">
          <div class="h-12 w-12 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center text-2xl font-bold">
            🛡️
          </div>
          <h3 class="font-black text-sm text-slate-900 uppercase">QUALITY</h3>
          <p class="text-xs text-slate-500 font-medium leading-relaxed">
            Carefully selected materials and hand-stitched footwear engineered for daily wear and maximum durability.
          </p>
        </div>

        <div class="p-6 bg-white rounded-3xl border border-slate-200 shadow-sm hover:border-red-600/50 transition-all space-y-3">
          <div class="h-12 w-12 rounded-2xl bg-slate-100 text-slate-900 flex items-center justify-center text-2xl font-bold">
            ✨
          </div>
          <h3 class="font-black text-sm text-slate-900 uppercase">STYLE</h3>
          <p class="text-xs text-slate-500 font-medium leading-relaxed">
            Contemporary designs tailored for every occasion — from formal corporate wear to festive family gatherings.
          </p>
        </div>

        <div class="p-6 bg-white rounded-3xl border border-slate-200 shadow-sm hover:border-red-600/50 transition-all space-y-3">
          <div class="h-12 w-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl font-bold">
            🏷️
          </div>
          <h3 class="font-black text-sm text-slate-900 uppercase">VALUE</h3>
          <p class="text-xs text-slate-500 font-medium leading-relaxed">
            Fair and transparent pricing providing maximum comfort without premium markups.
          </p>
        </div>

        <div class="p-6 bg-white rounded-3xl border border-slate-200 shadow-sm hover:border-red-600/50 transition-all space-y-3">
          <div class="h-12 w-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-2xl font-bold">
            🤝
          </div>
          <h3 class="font-black text-sm text-slate-900 uppercase">CUSTOMER FIRST</h3>
          <p class="text-xs text-slate-500 font-medium leading-relaxed">
            A customer shopping experience backed by easy size replacements and direct WhatsApp assistance.
          </p>
        </div>
      </div>
    </div>

    <!-- D. WHY CHOOSE US -->
    <div class="p-8 sm:p-12 bg-slate-900 text-white rounded-3xl border border-slate-800 space-y-8 shadow-xl">
      <div class="text-center max-w-xl mx-auto space-y-2">
        <span class="text-xs font-black text-red-500 uppercase tracking-widest block">ADVANTAGE RUPSA</span>
        <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight uppercase">WHY CHOOSE RUPSA PADUKALAYA</h2>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 text-xs">
        <div class="p-5 bg-slate-800/80 rounded-2xl border border-slate-700/80 space-y-2">
          <div class="text-xl">👟</div>
          <div class="font-bold text-white text-sm">Wide Footwear Collection</div>
          <p class="text-slate-300">Complete catalogue covering Men, Women, Kids and specialty accessories.</p>
        </div>

        <div class="p-5 bg-slate-800/80 rounded-2xl border border-slate-700/80 space-y-2">
          <div class="text-xl">👨‍👩‍👧‍👦</div>
          <div class="font-bold text-white text-sm">Men, Women & Kids</div>
          <p class="text-slate-300">Curated footwear options designed specifically for every family member's fit.</p>
        </div>

        <div class="p-5 bg-slate-800/80 rounded-2xl border border-slate-700/80 space-y-2">
          <div class="text-xl">🏅</div>
          <div class="font-bold text-white text-sm">Trusted Footwear Brands</div>
          <p class="text-slate-300">Partnered with leading footwear manufacturers known for authentic quality.</p>
        </div>

        <div class="p-5 bg-slate-800/80 rounded-2xl border border-slate-700/80 space-y-2">
          <div class="text-xl">🦶</div>
          <div class="font-bold text-white text-sm">Ergonomic Arch Comfort</div>
          <p class="text-slate-300">Insoles engineered to reduce heel stress during daily walking.</p>
        </div>

        <div class="p-5 bg-slate-800/80 rounded-2xl border border-slate-700/80 space-y-2">
          <div class="text-xl">🚚</div>
          <div class="font-bold text-white text-sm">Fast Dispatch & COD</div>
          <p class="text-slate-300">Reliable delivery options with Cash on Delivery support.</p>
        </div>

        <div class="p-5 bg-slate-800/80 rounded-2xl border border-slate-700/80 space-y-2">
          <div class="text-xl">💬</div>
          <div class="font-bold text-white text-sm">Direct WhatsApp Orders</div>
          <p class="text-slate-300">Order directly over WhatsApp with personalized size recommendations.</p>
        </div>
      </div>
    </div>

    <!-- E. OUR COLLECTION (Master Data Categories) -->
    <div class="space-y-8">
      <div class="text-center max-w-xl mx-auto space-y-2">
        <span class="text-xs font-black text-red-600 uppercase tracking-widest block">EXPLORE CATALOGUE</span>
        <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight uppercase">OUR CATEGORIES</h2>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 sm:gap-6">
        <router-link
          v-for="cat in categories"
          :key="cat.id"
          :to="'/categories/' + (cat.slug || cat.name.toLowerCase().replace(/\s+/g, '-'))"
          class="group relative rounded-3xl overflow-hidden bg-slate-100 aspect-3/4 border border-slate-200 shadow-sm flex flex-col justify-end p-5 transition-transform hover:-translate-y-1"
        >
          <img
            :src="cat.image_url || 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=600&q=80'"
            :alt="cat.name"
            class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
            loading="lazy"
          />
          <div class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-950/40 to-transparent"></div>

          <div class="relative z-10 space-y-1">
            <h3 class="font-black text-white text-base sm:text-lg tracking-tight uppercase group-hover:text-red-400 transition-colors">
              {{ cat.name }}
            </h3>
            <span class="text-[10px] font-bold text-slate-300 uppercase tracking-wider block">
              Explore Collection →
            </span>
          </div>
        </router-link>
      </div>
    </div>

    <!-- F. OUR PARTNERS (Master Data Brands Carousel) -->
    <div v-if="brands && brands.length > 0" class="space-y-6 pt-4 border-t border-slate-100">
      <div class="text-center max-w-xl mx-auto space-y-1">
        <span class="text-xs font-black text-red-600 uppercase tracking-widest block">TRUSTED FOOTWEAR BRANDS</span>
        <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight uppercase">OUR PARTNERS</h2>
      </div>

      <div class="flex items-center gap-6 overflow-x-auto scrollbar-none py-4 px-2">
        <div
          v-for="b in brands"
          :key="b.id"
          class="h-16 w-36 bg-white border border-slate-200 rounded-2xl flex items-center justify-center p-3 shrink-0 shadow-2xs"
        >
          <img v-if="b.logo_url || b.image_url" :src="b.logo_url || b.image_url" :alt="b.name" class="max-h-full max-w-full object-contain" />
          <span v-else class="font-black text-xs text-slate-800 uppercase tracking-wider">{{ b.name }}</span>
        </div>
      </div>
    </div>

    <!-- G. STORE / VISIT US -->
    <div class="p-8 bg-slate-50 rounded-3xl border border-slate-200/90 space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-6">
        <div>
          <span class="text-xs font-black text-red-600 uppercase tracking-widest block">RETAIL HUB</span>
          <h2 class="text-2xl font-black text-slate-900 tracking-tight uppercase">VISIT {{ contactInfo.business_name || 'RUPSA PADUKALAYA' }}</h2>
          <p class="text-xs text-slate-500 font-medium mt-1">
            {{ contactInfo.full_address || 'DHANTALA BAZAR, DHANTALA, NADIA, 741202, WEST BENGAL, INDIA' }}
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <a
            :href="'tel:' + (contactInfo.phone || '+919735125112').replace(/\s+/g, '')"
            class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-black transition-all flex items-center gap-2 shadow-xs"
          >
            <span>📞</span>
            <span>Call Now</span>
          </a>
          <a
            :href="'https://wa.me/' + (contactInfo.whatsapp_phone || '919735125112').replace(/[^0-9]/g, '')"
            target="_blank"
            rel="noopener"
            class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black transition-all flex items-center gap-2 shadow-xs"
          >
            <span>💬</span>
            <span>WhatsApp</span>
          </a>
          <a
            :href="contactInfo.maps_url || 'https://maps.google.com/?q=RUPSA+PADUKALAYA+DHANTALA+BAZAR+DHANTALA+NADIA+741202+WEST+BENGAL'"
            target="_blank"
            rel="noopener"
            class="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition-all flex items-center gap-2"
          >
            <span>🗺️</span>
            <span>Get Directions</span>
          </a>
        </div>
      </div>
    </div>

    <!-- H. FINAL CTA -->
    <div class="rounded-3xl bg-gradient-to-r from-red-600 via-red-700 to-slate-900 text-white p-8 sm:p-12 text-center space-y-4 shadow-xl">
      <h2 class="text-2xl sm:text-4xl font-black tracking-tight uppercase">STEP INTO SOMETHING BETTER</h2>
      <p class="text-xs sm:text-sm text-red-100 font-medium max-w-xl mx-auto">
        Explore our latest men, women and kids footwear collection.
      </p>
      <div class="pt-2">
        <router-link
          to="/categories/men"
          class="inline-block px-8 py-3.5 bg-white text-slate-900 hover:bg-slate-100 font-black text-xs uppercase tracking-wider rounded-2xl shadow-lg transition-all"
        >
          SHOP NOW →
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useWebsiteStore } from '../../stores/websiteStore';
import api from '../../services/api';

const websiteStore = useWebsiteStore();

const categories = ref([]);
const brands = ref([]);

const contactInfo = computed(() => websiteStore.settings?.contact_info || {});

async function loadData() {
  try {
    const [cRes, bRes] = await Promise.all([
      api.get('/public/categories'),
      api.get('/public/brands'),
    ]);
    categories.value = Array.isArray(cRes.data || cRes) ? (cRes.data || cRes) : [];
    brands.value = Array.isArray(bRes.data || bRes) ? (bRes.data || bRes) : [];
  } catch (err) {
    console.error('Failed to load categories/brands for About page:', err);
  }
}

onMounted(() => {
  websiteStore.fetchSettings();
  loadData();
});
</script>
