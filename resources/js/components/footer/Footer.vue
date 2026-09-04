<template>
  <footer v-if="footerSettings.enabled !== false" class="bg-slate-50 text-slate-800 font-sans border-t border-slate-200/90 relative antialiased select-none">
    <!-- MAIN 4-COLUMN FOOTER CONTAINER (Exact Reference Design) -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-12">
        
        <!-- COLUMN 1 — SHOP AT RUPSA PADUKALAYA -->
        <div class="space-y-4">
          <div>
            <h3 class="font-black text-xs sm:text-sm text-slate-900 uppercase tracking-wider">
              {{ footerSettings.col1?.title || 'SHOP AT RUPSA PADUKALAYA' }}
            </h3>
            <div class="w-8 h-0.5 bg-red-600 mt-1.5"></div>
          </div>

          <ul class="space-y-2.5 text-xs font-semibold text-slate-800">
            <li v-for="(link, idx) in footerSettings.col1?.links || []" :key="idx">
              <router-link
                v-if="link.url && link.url.startsWith('/')"
                :to="link.url"
                class="hover:text-red-600 transition-colors inline-flex items-center group"
              >
                <span class="text-slate-400 group-hover:text-red-600 text-xs font-mono mr-2">›</span>
                <span>{{ link.label }}</span>
              </router-link>
              <a
                v-else
                :href="link.url || '#'"
                class="hover:text-red-600 transition-colors inline-flex items-center group"
              >
                <span class="text-slate-400 group-hover:text-red-600 text-xs font-mono mr-2">›</span>
                <span>{{ link.label }}</span>
              </a>
            </li>
          </ul>
        </div>

        <!-- COLUMN 2 — IT'S WOW! IT'S RUPSA & KEEP IN TOUCH -->
        <div class="space-y-8">
          <div class="space-y-4">
            <div>
              <h3 class="font-black text-xs sm:text-sm text-slate-900 uppercase tracking-wider">
                {{ footerSettings.col2?.title || "IT'S WOW! IT'S RUPSA" }}
              </h3>
              <div class="w-8 h-0.5 bg-red-600 mt-1.5"></div>
            </div>

            <ul class="space-y-2.5 text-xs font-semibold text-slate-800">
              <li v-for="(link, idx) in footerSettings.col2?.links || []" :key="idx">
                <router-link
                  v-if="link.url && link.url.startsWith('/')"
                  :to="link.url"
                  class="hover:text-red-600 transition-colors inline-flex items-center group"
                >
                  <span class="text-slate-400 group-hover:text-red-600 text-xs font-mono mr-2">›</span>
                  <span>{{ link.label }}</span>
                </router-link>
                <a
                  v-else
                  :href="link.url || '#'"
                  class="hover:text-red-600 transition-colors inline-flex items-center group"
                >
                  <span class="text-slate-400 group-hover:text-red-600 text-xs font-mono mr-2">›</span>
                  <span>{{ link.label }}</span>
                </a>
              </li>
            </ul>
          </div>

          <!-- KEEP IN TOUCH (Social Media Circular Buttons) -->
          <div class="space-y-3 pt-1">
            <div>
              <h4 class="font-black text-xs text-slate-900 uppercase tracking-wider">
                {{ footerSettings.col2?.keep_in_touch_title || 'KEEP IN TOUCH' }}
              </h4>
              <div class="w-8 h-0.5 bg-red-600 mt-1.5"></div>
            </div>

            <div class="flex items-center gap-2.5 flex-wrap">
              <a
                v-for="(soc, sIdx) in activeSocialLinks"
                :key="sIdx"
                :href="soc.url || '#'"
                target="_blank"
                rel="noopener"
                class="h-9 w-9 rounded-full bg-black hover:bg-red-600 text-white flex items-center justify-center transition-all text-xs font-bold shadow-2xs hover:scale-110"
                :title="soc.platform"
              >
                <span v-if="soc.platform === 'facebook'">f</span>
                <span v-else-if="soc.platform === 'instagram'">📷</span>
                <span v-else-if="soc.platform === 'twitter'">𝕏</span>
                <span v-else-if="soc.platform === 'youtube'">▶</span>
                <span v-else-if="soc.platform === 'linkedin'">in</span>
                <span v-else>🌐</span>
              </a>
            </div>
          </div>
        </div>

        <!-- COLUMN 3 — CORPORATE (Pure Corporate - No Employee Corner) -->
        <div class="space-y-4">
          <div>
            <h3 class="font-black text-xs sm:text-sm text-slate-900 uppercase tracking-wider">
              {{ footerSettings.col3?.title || 'CORPORATE' }}
            </h3>
            <div class="w-8 h-0.5 bg-red-600 mt-1.5"></div>
          </div>

          <ul class="space-y-2.5 text-xs font-semibold text-slate-800">
            <li v-for="(link, idx) in footerSettings.col3?.links || []" :key="idx">
              <router-link
                v-if="link.url && link.url.startsWith('/')"
                :to="link.url"
                class="hover:text-red-600 transition-colors inline-flex items-center group"
              >
                <span class="text-slate-400 group-hover:text-red-600 text-xs font-mono mr-2">›</span>
                <span>{{ link.label }}</span>
              </router-link>
              <a
                v-else
                :href="link.url || '#'"
                class="hover:text-red-600 transition-colors inline-flex items-center group"
              >
                <span class="text-slate-400 group-hover:text-red-600 text-xs font-mono mr-2">›</span>
                <span>{{ link.label }}</span>
              </a>
            </li>
          </ul>
        </div>

        <!-- COLUMN 4 — SUPPORT & SITEMAP (Pure Customer Support - No Account Links) -->
        <div class="space-y-6 flex flex-col justify-between">
          <div class="space-y-4">
            <div>
              <h3 class="font-black text-xs sm:text-sm text-slate-900 uppercase tracking-wider">
                {{ footerSettings.col4?.title || 'SUPPORT' }}
              </h3>
              <div class="w-8 h-0.5 bg-red-600 mt-1.5"></div>
            </div>

            <ul class="space-y-2.5 text-xs font-semibold text-slate-800">
              <li v-for="(link, idx) in footerSettings.col4?.links || []" :key="idx">
                <router-link
                  v-if="link.url && link.url.startsWith('/')"
                  :to="link.url"
                  class="hover:text-red-600 transition-colors inline-flex items-center group"
                >
                  <span class="text-slate-400 group-hover:text-red-600 text-xs font-mono mr-2">›</span>
                  <span>{{ link.label }}</span>
                </router-link>
                <a
                  v-else
                  :href="link.url || '#'"
                  class="hover:text-red-600 transition-colors inline-flex items-center group"
                >
                  <span class="text-slate-400 group-hover:text-red-600 text-xs font-mono mr-2">›</span>
                  <span>{{ link.label }}</span>
                </a>
              </li>
            </ul>
          </div>

          <!-- SITEMAP BUTTON (Clean Outlined Rounded Rectangular Button) -->
          <div class="pt-4">
            <router-link
              to="/about"
              class="inline-flex items-center gap-3 px-4 py-2.5 bg-white rounded-xl border border-slate-300 shadow-2xs hover:border-red-600 transition-all text-xs font-extrabold text-slate-900 group"
            >
              <div class="h-8 w-8 rounded-full bg-black text-white flex items-center justify-center text-sm font-bold shrink-0 group-hover:bg-red-600 transition-colors">
                🔀
              </div>
              <span>Sitemap</span>
            </router-link>
          </div>
        </div>

      </div>
    </div>

    <!-- TRUST / SERVICE FEATURES STRIP -->
    <FooterTrustFeatures :features="footerSettings.trust_features || []" />

    <!-- PAYMENT METHODS ACCEPTANCE STRIP -->
    <FooterPaymentMethods :methods="footerSettings.payment_methods || []" />

    <!-- THIN DIVIDER & BOTTOM COPYRIGHT BAR WITH DEVELOPER CREDIT -->
    <div class="border-t border-slate-200/90 py-5 px-4 text-center text-xs font-medium text-slate-700 bg-slate-50">
      <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2">
        <span>{{ copyrightText }}</span>
        <div class="text-xs font-medium text-slate-600">
          <span>Developed By </span>
          <a href="https://techgoogly.com" target="_blank" rel="noopener" class="font-bold text-red-600 hover:text-red-700 hover:underline">Tech Googly</a>
        </div>
      </div>
    </div>

    <!-- FLOATING SCROLL TO TOP BUTTON -->
    <ScrollToTop />
  </footer>
</template>

<script setup>
import { computed } from 'vue';
import { useWebsiteStore } from '../../stores/websiteStore';
import FooterTrustFeatures from './FooterTrustFeatures.vue';
import FooterPaymentMethods from './FooterPaymentMethods.vue';
import ScrollToTop from './ScrollToTop.vue';

const websiteStore = useWebsiteStore();

const footerSettings = computed(() => websiteStore.settings?.footer || {});

const activeSocialLinks = computed(() => {
  const list = footerSettings.value.col2?.social_links || [];
  return list.filter(x => x.enabled !== false);
});

const copyrightText = computed(() => {
  const raw = footerSettings.value.copyright_text || '© {CURRENT_YEAR}, Rupsa Padukalaya. All rights reserved.';
  return raw.replace('{CURRENT_YEAR}', new Date().getFullYear());
});
</script>
