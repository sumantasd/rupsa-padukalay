<template>
  <div class="min-h-screen bg-slate-50 text-slate-800 font-sans flex flex-col antialiased">
    <!-- INITIAL BOOTSTRAP LOADING SKELETON (Prevents any old content flash on refresh) -->
    <div v-if="!websiteStore.isLoaded" class="bg-white border-b border-slate-200 shadow-xs z-50 sticky top-0">
      <div class="bg-slate-900 text-white text-[11px] py-2 px-4 flex justify-center animate-pulse">
        <span class="w-48 h-3 bg-slate-700 rounded"></span>
      </div>
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 sm:h-20 flex items-center justify-between gap-4">
        <div class="h-10 w-36 bg-slate-200 rounded-xl animate-pulse"></div>
        <div class="hidden lg:flex items-center gap-6">
          <div v-for="i in 5" :key="i" class="h-4 w-16 bg-slate-200 rounded animate-pulse"></div>
        </div>
        <div class="flex items-center gap-3">
          <div class="h-8 w-8 bg-slate-200 rounded-full animate-pulse"></div>
          <div class="h-9 w-24 bg-slate-200 rounded-xl animate-pulse"></div>
        </div>
      </div>
    </div>

    <!-- ACTUAL PUBLISHED HEADER (Renders only after published configuration is loaded) -->
    <template v-else>
      <!-- LEVEL 1 — TOP PROMOTIONAL ANNOUNCEMENT BAR -->
      <div
        v-if="webSettings.announcement_bar.enabled"
        :style="{ backgroundColor: webSettings.announcement_bar.bg_color || '#ea580c', color: webSettings.announcement_bar.text_color || '#ffffff' }"
        class="text-white text-[11px] font-black py-2 px-4 border-b border-orange-700/30 overflow-hidden relative select-none"
      >
        <div class="max-w-7xl mx-auto flex items-center justify-center">
          <div class="w-full overflow-hidden whitespace-nowrap text-center">
            <div class="inline-flex items-center gap-6 animate-marquee">
              <template v-for="(item, idx) in webSettings.announcement_bar.items" :key="idx">
                <span class="flex items-center gap-1.5 uppercase tracking-wide">
                  <span>{{ item }}</span>
                </span>
                <span v-if="idx < webSettings.announcement_bar.items.length - 1" class="text-white/60 font-bold">•</span>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- LEVEL 2 — MAIN NAVIGATION HEADER -->
      <header :class="['bg-white border-b border-slate-200/90 shadow-xs transition-all z-40', webSettings.header.sticky ? 'sticky top-0' : 'relative']">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 sm:h-20 flex items-center justify-between gap-4">
          
          <!-- Mobile Left: Hamburger Toggle -->
          <button
            @click="mobileMenuOpen = !mobileMenuOpen"
            class="lg:hidden p-2 text-slate-700 hover:text-red-600 text-xl font-bold rounded-lg focus:outline-none"
            aria-label="Toggle Navigation Menu"
          >
            ☰
          </button>

          <!-- Brand Logo (Dynamic Image or Fallback Text Logo) -->
          <router-link to="/" class="flex items-center gap-2 shrink-0">
            <img
              v-if="webSettings.header.logo_url"
              :src="webSettings.header.logo_url"
              alt="RUPSA PADUKALAYA"
              class="h-10 sm:h-12 w-auto max-w-[180px] sm:max-w-[220px] object-contain"
            />
            <div v-else class="bg-slate-900 text-white px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 border border-slate-800 shadow-xs">
              <span class="h-7 w-7 rounded-lg bg-red-600 text-white flex items-center justify-center font-black text-sm shadow-2xs">
                R
              </span>
              <div class="flex flex-col">
                <span class="font-black text-sm tracking-wider leading-none text-white">
                  RUPSA <span class="text-red-500">PADUKALAYA</span>
                </span>
                <span class="text-[8px] font-extrabold text-slate-400 tracking-widest uppercase mt-0.5">
                  STEP INTO COMFORT
                </span>
              </div>
            </div>
          </router-link>

          <!-- CENTER — DYNAMIC DESKTOP NAVIGATION MENU WITH TYPOGRAPHY STYLING -->
          <nav
            class="hidden lg:flex items-center text-slate-800 uppercase"
            :style="{
              fontSize: (webSettings.header.navigation_styling?.font_size || 15) + 'px',
              fontWeight: webSettings.header.navigation_styling?.font_weight || '700',
              gap: (webSettings.header.navigation_styling?.item_spacing || 16) + 'px',
              letterSpacing: webSettings.header.navigation_styling?.letter_spacing || '0.02em',
            }"
          >
            <router-link
              v-for="item in activeNavItems"
              :key="item.id || item.url"
              :to="item.url"
              class="px-3 py-2 rounded-lg transition-colors hover:text-red-600 hover:bg-slate-50"
              active-class="text-red-600 font-black"
            >
              {{ item.label }}
            </router-link>
          </nav>

          <!-- RIGHT — ACTION ICONS & CONFIGURABLE HEADER BUTTON -->
          <div class="flex items-center gap-2 sm:gap-3 shrink-0">
            <!-- Search Icon Button -->
            <button
              v-if="webSettings.header.show_search_icon"
              @click="showSearchModal = true"
              class="p-2 text-slate-700 hover:text-red-600 text-lg transition-colors rounded-full hover:bg-slate-100"
              title="Search Footwear"
            >
              🔍
            </button>

            <!-- CONFIGURABLE HEADER BUTTON (Immediately After Search Icon) -->
            <template v-if="webSettings.header.header_button?.enabled !== false">
              <router-link
                v-if="webSettings.header.header_button?.url?.startsWith('/')"
                :to="webSettings.header.header_button.url"
                :class="[
                  'font-black rounded-xl transition-all shadow-xs shrink-0 items-center justify-center',
                  headerButtonStyleClass,
                  headerButtonSizeClass,
                  webSettings.header.header_button.show_desktop ? 'hidden sm:inline-flex' : 'hidden',
                  webSettings.header.header_button.show_mobile ? 'inline-flex sm:hidden' : 'hidden'
                ]"
              >
                {{ webSettings.header.header_button.text || 'Shop Now' }}
              </router-link>
              <a
                v-else
                :href="webSettings.header.header_button?.url || '/categories/men'"
                :target="webSettings.header.header_button?.target || '_self'"
                :class="[
                  'font-black rounded-xl transition-all shadow-xs shrink-0 items-center justify-center',
                  headerButtonStyleClass,
                  headerButtonSizeClass,
                  webSettings.header.header_button.show_desktop ? 'hidden sm:inline-flex' : 'hidden',
                  webSettings.header.header_button.show_mobile ? 'inline-flex sm:hidden' : 'hidden'
                ]"
              >
                {{ webSettings.header.header_button.text || 'Shop Now' }}
              </a>
            </template>

            <!-- Account Icon -->
            <button
              v-if="webSettings.header.show_account_icon"
              @click="showAccountInfo = true"
              class="p-2 text-slate-700 hover:text-red-600 text-lg transition-colors rounded-full hover:bg-slate-100"
              title="My Account"
            >
              👤
            </button>

            <!-- Shopping Bag / Cart Icon -->
            <button
              v-if="webSettings.header.show_cart_icon"
              @click="cartOpen = true"
              class="p-2 text-slate-700 hover:text-red-600 text-lg transition-colors rounded-full hover:bg-slate-100 relative"
              title="Shopping Bag"
            >
              🛍️
              <span
                v-if="cartItems.length > 0"
                class="absolute -top-0.5 -right-0.5 h-4 w-4 rounded-full bg-orange-600 text-white text-[9px] font-black flex items-center justify-center shadow-2xs"
              >
                {{ cartTotalItems }}
              </span>
            </button>
          </div>
        </div>
      </header>

      <!-- MOBILE OFF-CANVAS NAVIGATION DRAWER -->
      <div v-if="mobileMenuOpen" class="fixed inset-0 z-50 flex lg:hidden">
        <div @click="mobileMenuOpen = false" class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs"></div>
        <div class="relative bg-white w-4/5 max-w-sm h-full shadow-2xl flex flex-col justify-between overflow-y-auto">
          <div>
            <div class="p-4 bg-slate-900 text-white flex items-center justify-between">
              <div class="flex items-center gap-2">
                <img
                  v-if="webSettings.header.logo_url"
                  :src="webSettings.header.logo_url"
                  alt="RUPSA PADUKALAYA"
                  class="h-8 w-auto max-w-[160px] object-contain"
                />
                <div v-else class="flex items-center gap-2">
                  <span class="h-6 w-6 rounded bg-red-600 text-white font-black text-xs flex items-center justify-center">R</span>
                  <span class="font-black text-xs tracking-wider">RUPSA PADUKALAYA</span>
                </div>
              </div>
              <button @click="mobileMenuOpen = false" class="text-slate-400 hover:text-white text-lg">✕</button>
            </div>

            <div class="p-4 space-y-3 text-xs font-bold text-slate-800 divide-y divide-slate-100">
              <router-link
                v-for="item in activeNavItems"
                :key="item.id || item.url"
                @click="mobileMenuOpen = false"
                :to="item.url"
                class="block pt-2 hover:text-red-600"
              >
                {{ item.label }}
              </router-link>
            </div>
          </div>

          <div class="p-4 bg-slate-50 border-t border-slate-200 text-[11px] text-slate-500 space-y-2">
            <p class="font-bold text-slate-700">RUPSA PADUKALAYA Customer Support</p>
            <p>📞 Phone: {{ webSettings.contact_info.phone }}</p>
            <p>✉️ Email: {{ webSettings.contact_info.email }}</p>
          </div>
        </div>
      </div>

      <!-- SEARCH OVERLAY MODAL -->
      <div v-if="showSearchModal" class="fixed inset-0 z-50 flex items-start justify-center pt-20 p-4 bg-slate-950/60 backdrop-blur-xs">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl w-full max-w-xl overflow-hidden space-y-4 p-6">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="font-black text-sm text-slate-900 flex items-center gap-2">
              <span>🔍</span>
              <span>Search RUPSA Footwear</span>
            </h3>
            <button @click="showSearchModal = false" class="text-slate-400 hover:text-slate-700 text-lg">✕</button>
          </div>

          <div class="relative">
            <input
              v-model="searchQuery"
              @input="performSearch"
              type="text"
              placeholder="Search article number, product name, brand or category..."
              class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-3 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 font-medium"
              autofocus
            />
          </div>

          <div class="max-h-64 overflow-y-auto divide-y divide-slate-100 text-xs">
            <div v-if="searching" class="text-center py-6 text-slate-400 font-bold">
              Searching catalog...
            </div>
            <div v-else-if="searchResults.length === 0 && searchQuery.trim()" class="text-center py-6 text-slate-400 font-bold">
              No products found matching "{{ searchQuery }}".
            </div>
            <div
              v-for="item in searchResults"
              :key="item.id"
              @click="selectSearchResult(item)"
              class="p-3 hover:bg-slate-50 rounded-xl flex items-center justify-between cursor-pointer transition-colors"
            >
              <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-lg bg-slate-100 border border-slate-200 flex items-center justify-center text-lg overflow-hidden shrink-0">
                  <img v-if="item.primary_image_url || item.image_url" :src="item.primary_image_url || item.image_url" class="h-full w-full object-cover" />
                  <span v-else>👟</span>
                </div>
                <div>
                  <div class="font-bold text-slate-900">{{ item.name }}</div>
                  <div class="font-mono text-[10px] text-red-600 font-bold">ART: {{ item.article_number }}</div>
                </div>
              </div>
              <div class="text-right">
                <div class="font-black text-slate-900">₹{{ item.selling_price || item.mrp || '999' }}</div>
                <div class="text-[10px] text-slate-400 uppercase font-bold">{{ item.brand?.name || 'In Stock' }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- SHOPPING BAG / CART SLIDE-OUT DRAWER -->
      <div v-if="cartOpen" class="fixed inset-0 z-50 flex justify-end">
        <div @click="cartOpen = false" class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs"></div>
        <div class="relative bg-white w-full max-w-md h-full shadow-2xl flex flex-col justify-between overflow-y-auto">
          <div class="p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 class="font-black text-slate-900 text-sm flex items-center gap-2">
                <span>🛍️</span>
                <span>Your Shopping Bag ({{ cartTotalItems }})</span>
              </h3>
              <button @click="cartOpen = false" class="text-slate-400 hover:text-slate-700 text-lg">✕</button>
            </div>

            <div v-if="cartItems.length === 0" class="text-center py-12 text-slate-400 space-y-2">
              <div class="text-4xl">🛒</div>
              <p class="font-bold text-xs">Your shopping bag is empty.</p>
              <button @click="cartOpen = false" class="px-4 py-2 bg-red-600 text-white rounded-xl font-bold text-xs">
                Start Shopping
              </button>
            </div>

            <div v-else class="space-y-3">
              <div v-for="(item, idx) in cartItems" :key="idx" class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between gap-3 text-xs">
                <div class="h-12 w-12 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-xl shrink-0 overflow-hidden">
                  <img v-if="item.image" :src="item.image" class="h-full w-full object-cover" />
                  <span v-else>👟</span>
                </div>
                <div class="flex-1">
                  <div class="font-bold text-slate-900 leading-tight">{{ item.name }}</div>
                  <div class="text-[10px] text-slate-500 mt-0.5">Size: IND {{ item.size }} | {{ item.color }}</div>

                  <div class="font-black text-slate-900 mt-1">₹{{ item.price }}</div>
                </div>
                <div class="flex items-center gap-2">
                  <button @click="updateCartQty(idx, -1)" class="h-6 w-6 rounded bg-slate-200 font-bold text-slate-700 flex items-center justify-center">-</button>
                  <span class="font-bold text-xs">{{ item.qty }}</span>
                  <button @click="updateCartQty(idx, 1)" class="h-6 w-6 rounded bg-slate-200 font-bold text-slate-700 flex items-center justify-center">+</button>
                </div>
              </div>
            </div>
          </div>

          <div v-if="cartItems.length > 0" class="p-6 bg-slate-50 border-t border-slate-200 space-y-3 text-xs">
            <div class="flex justify-between font-bold text-slate-700">
              <span>Subtotal</span>
              <span>₹{{ cartSubtotal }}</span>
            </div>
            <div class="flex justify-between font-bold text-emerald-700">
              <span>Estimated Shipping</span>
              <span>FREE</span>
            </div>
            <div class="flex justify-between font-black text-slate-900 text-sm border-t border-slate-200 pt-2">
              <span>Total Payable</span>
              <span class="text-red-600">₹{{ cartSubtotal }}</span>
            </div>
            <button @click="checkout" class="w-full py-3 bg-red-600 hover:bg-red-700 text-white font-black rounded-xl text-xs shadow-md shadow-red-600/20 transition-all">
              Proceed to Checkout
            </button>
          </div>
        </div>
      </div>

      <!-- MAIN PAGE CONTENT ROUTER VIEW -->
      <main class="flex-1">
        <router-view />
      </main>

      <!-- SINGLE COMPLETE FOOTER MATCHING REFERENCE DESIGN -->
      <Footer />
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useWebsiteStore } from '../../stores/websiteStore';
import Footer from '../../components/footer/Footer.vue';
import api from '../../services/api';

const router = useRouter();
const websiteStore = useWebsiteStore();

const mobileMenuOpen = ref(false);
const showSearchModal = ref(false);
const showAccountInfo = ref(false);
const cartOpen = ref(false);

const searchQuery = ref('');
const searching = ref(false);
const searchResults = ref([]);

const webSettings = computed(() => websiteStore.settings);
const activeNavItems = computed(() => (webSettings.value.navigation || []).filter(x => x.enabled !== false));

const headerButtonStyleClass = computed(() => {
  const style = webSettings.value.header?.header_button?.style || 'solid-red';
  if (style === 'outline-dark') return 'border-2 border-slate-900 text-slate-900 hover:bg-slate-900 hover:text-white';
  if (style === 'solid-dark') return 'bg-slate-900 text-white hover:bg-slate-800';
  return 'bg-red-600 hover:bg-red-700 text-white shadow-red-600/20';
});

const headerButtonSizeClass = computed(() => {
  const size = webSettings.value.header?.header_button?.size || 'md';
  if (size === 'sm') return 'px-3 py-1.5 text-[11px]';
  if (size === 'lg') return 'px-5 py-2.5 text-xs';
  return 'px-4 py-2 text-xs';
});

const cartItems = ref([
  { name: 'Executive Oxford Genuine Leather', size: '08', color: 'Black', price: 1792, qty: 1, image: '' }
]);

const cartTotalItems = computed(() => cartItems.value.reduce((acc, item) => acc + item.qty, 0));
const cartSubtotal = computed(() => cartItems.value.reduce((acc, item) => acc + (item.price * item.qty), 0));

async function performSearch() {
  if (!searchQuery.value.trim()) {
    searchResults.value = [];
    return;
  }
  searching.value = true;
  try {
    const res = await api.get('/products', { params: { search: searchQuery.value.trim() } });
    searchResults.value = (res.data || []).slice(0, 5);
  } catch (err) {
    console.error('Search failed:', err);
  } finally {
    searching.value = false;
  }
}

function selectSearchResult(item) {
  showSearchModal.value = false;
  router.push(`/products/${item.id}`);
}

function updateCartQty(index, delta) {
  if (cartItems.value[index]) {
    cartItems.value[index].qty += delta;
    if (cartItems.value[index].qty <= 0) {
      cartItems.value.splice(index, 1);
    }
  }
}

function checkout() {
  alert('Thank you! Checkout feature is ready for payment gateway integration.');
}

onMounted(() => {
  websiteStore.fetchSettings();
});
</script>

<style scoped>
@keyframes marquee {
  0% { transform: translateX(0%); }
  100% { transform: translateX(-50%); }
}
.animate-marquee {
  display: inline-flex;
  animation: marquee 25s linear infinite;
}
.animate-marquee:hover {
  animation-play-state: paused;
}
</style>
