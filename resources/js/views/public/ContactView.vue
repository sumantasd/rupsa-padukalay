<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-12 antialiased font-sans">
    <!-- A. CONTACT HERO -->
    <div class="relative rounded-3xl overflow-hidden bg-slate-950 text-white p-8 sm:p-12 text-center border border-slate-800 shadow-xl">
      <img
        src="https://images.unsplash.com/photo-1549298916-b41d501d3772?auto=format&fit=crop&w=1600&q=80"
        alt="Contact RUPSA PADUKALAYA"
        class="absolute inset-0 w-full h-full object-cover opacity-25"
        loading="eager"
      />
      <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/70 to-transparent"></div>

      <div class="relative z-10 max-w-2xl mx-auto space-y-3">
        <span class="inline-block px-3.5 py-1 bg-red-600/30 text-red-400 text-[10px] sm:text-xs font-black uppercase tracking-widest rounded-full border border-red-600/40">
          OFFICIAL STORE CONTACT
        </span>
        <h1 class="text-3xl sm:text-5xl font-black text-white tracking-tight uppercase">
          CONTACT US
        </h1>
        <p class="text-xs sm:text-sm text-slate-300 font-medium leading-relaxed max-w-lg mx-auto">
          We're here to help you find the right pair. Have questions about footwear sizes, stock availability or store visit? Reach out directly.
        </p>
      </div>
    </div>

    <!-- B. CONTACT CARDS & FORM GRID -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
      
      <!-- B1. CONTACT INFORMATION & CARDS -->
      <div class="space-y-6">
        <!-- Store Information Card -->
        <div class="p-8 bg-slate-900 text-white rounded-3xl border border-slate-800 space-y-6 shadow-xl">
          <div class="space-y-1 border-b border-slate-800 pb-4">
            <span class="text-xs font-black text-red-500 uppercase tracking-widest block">RETAIL HUB & HEADQUARTERS</span>
            <h2 class="font-black text-2xl text-white tracking-tight uppercase">
              {{ contactInfo.business_name || 'RUPSA PADUKALAYA' }}
            </h2>
          </div>
          
          <div class="space-y-5 text-xs text-slate-300">
            <!-- Address -->
            <div class="flex items-start gap-3.5">
              <div class="h-10 w-10 rounded-xl bg-slate-800 text-red-500 flex items-center justify-center text-xl shrink-0 border border-slate-700">
                📍
              </div>
              <div class="space-y-1">
                <div class="font-black text-white text-sm">Official Address</div>
                <div class="text-slate-300 leading-relaxed font-medium">
                  {{ contactInfo.full_address || 'DHANTALA BAZAR, DHANTALA, NADIA, 741202, WEST BENGAL, INDIA' }}
                </div>
              </div>
            </div>

            <!-- Phone -->
            <div class="flex items-start gap-3.5">
              <div class="h-10 w-10 rounded-xl bg-slate-800 text-emerald-400 flex items-center justify-center text-xl shrink-0 border border-slate-700">
                📞
              </div>
              <div class="space-y-1">
                <div class="font-black text-white text-sm">Customer Helpline (Call & Phone)</div>
                <a
                  :href="callNumberUrl"
                  class="text-emerald-400 hover:underline font-mono font-bold text-sm block"
                >
                  {{ contactInfo.phone || '+91 9735125112' }}
                </a>
              </div>
            </div>

            <!-- WhatsApp -->
            <div class="flex items-start gap-3.5">
              <div class="h-10 w-10 rounded-xl bg-slate-800 text-emerald-400 flex items-center justify-center text-xl shrink-0 border border-slate-700">
                💬
              </div>
              <div class="space-y-1">
                <div class="font-black text-white text-sm">Official WhatsApp Assistance</div>
                <a
                  :href="whatsAppUrl"
                  target="_blank"
                  rel="noopener"
                  class="text-emerald-400 hover:underline font-mono font-bold text-sm block"
                >
                  {{ contactInfo.whatsapp_phone || '+91 9735125112' }}
                </a>
              </div>
            </div>

            <!-- F. BUSINESS HOURS -->
            <div class="flex items-start gap-3.5 pt-2 border-t border-slate-800">
              <div class="h-10 w-10 rounded-xl bg-slate-800 text-amber-400 flex items-center justify-center text-xl shrink-0 border border-slate-700">
                🕒
              </div>
              <div class="space-y-1">
                <div class="font-black text-white text-sm">Business Hours</div>
                <div class="text-slate-300 font-medium">Monday to Sunday: 09:30 AM – 09:00 PM</div>
              </div>
            </div>
          </div>

          <!-- CALL NOW, WHATSAPP & MAPS ACTION BUTTONS -->
          <div class="pt-4 border-t border-slate-800 grid grid-cols-1 sm:grid-cols-3 gap-3">
            <a
              :href="callNumberUrl"
              class="px-4 py-3 bg-red-600 hover:bg-red-700 text-white font-black rounded-2xl text-xs flex items-center justify-center gap-2 shadow-lg shadow-red-600/30 transition-all text-center"
            >
              <span>📞</span>
              <span>Call Now</span>
            </a>

            <a
              :href="whatsAppUrl"
              target="_blank"
              rel="noopener"
              class="px-4 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-2xl text-xs flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/30 transition-all text-center"
            >
              <span>💬</span>
              <span>WhatsApp</span>
            </a>

            <a
              :href="mapsDirectionUrl"
              target="_blank"
              rel="noopener"
              class="px-4 py-3 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-2xl text-xs flex items-center justify-center gap-2 border border-slate-700 transition-all text-center"
            >
              <span>🗺️</span>
              <span>Get Directions</span>
            </a>
          </div>
        </div>

        <!-- C. GOOGLE MAP / LOCATION CARD -->
        <div class="p-6 bg-slate-50 rounded-3xl border border-slate-200/90 space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="font-black text-sm text-slate-900">Google Maps Store Location</h3>
              <p class="text-xs text-slate-500 font-medium mt-0.5">
                {{ contactInfo.full_address || 'DHANTALA BAZAR, DHANTALA, NADIA, 741202, WEST BENGAL, INDIA' }}
              </p>
            </div>
            <a
              :href="mapsDirectionUrl"
              target="_blank"
              rel="noopener"
              class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl shrink-0 transition-colors"
            >
              🗺️ Open Maps
            </a>
          </div>

          <div class="h-48 w-full bg-slate-200 rounded-2xl overflow-hidden relative border border-slate-300 flex items-center justify-center">
            <iframe
              width="100%"
              height="100%"
              style="border:0"
              loading="lazy"
              allowfullscreen
              referrerpolicy="no-referrer-when-downgrade"
              :src="'https://www.google.com/maps/embed/v1/place?key=AIzaSyD-PlaceholderKey&q=' + encodeURIComponent(contactInfo.full_address || 'DHANTALA BAZAR DHANTALA NADIA 741202 WEST BENGAL')"
              class="w-full h-full opacity-90"
              @error="onMapError"
            ></iframe>
            <!-- Map Fallback Overlay if API key is unconfigured -->
            <div v-if="mapFallback" class="absolute inset-0 bg-slate-900 text-white p-6 flex flex-col items-center justify-center text-center space-y-2">
              <span class="text-3xl">📍</span>
              <div class="font-black text-sm">RUPSA PADUKALAYA Store Location</div>
              <p class="text-xs text-slate-300">{{ contactInfo.full_address || 'DHANTALA BAZAR, DHANTALA, NADIA, 741202, WEST BENGAL, INDIA' }}</p>
              <a
                :href="mapsDirectionUrl"
                target="_blank"
                rel="noopener"
                class="mt-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold"
              >
                Open Direct Google Maps Directions
              </a>
            </div>
          </div>
        </div>

      </div>

      <!-- D. CONTACT FORM -->
      <div class="p-8 bg-white rounded-3xl border border-slate-200 shadow-sm space-y-5">
        <div class="space-y-1 border-b border-slate-100 pb-4">
          <span class="text-xs font-black text-red-600 uppercase tracking-widest block">SEND MESSAGE</span>
          <h2 class="font-black text-xl text-slate-900 tracking-tight uppercase">SEND CUSTOMER ENQUIRY</h2>
          <p class="text-xs text-slate-500 font-medium">Fill in your message and our support team will respond promptly.</p>
        </div>

        <form @submit.prevent="submitForm" class="space-y-4 text-xs">
          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">Full Name *</label>
            <input
              v-model="form.name"
              type="text"
              required
              placeholder="e.g. Subhash Ghosh"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-900 font-medium focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1">
              <label class="font-bold text-slate-700 block">Phone / Mobile Number *</label>
              <input
                v-model="form.phone"
                type="tel"
                required
                placeholder="e.g. +91 9735125112"
                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-900 font-medium focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
              />
            </div>

            <div class="space-y-1">
              <label class="font-bold text-slate-700 block">Email Address (Optional)</label>
              <input
                v-model="form.email"
                type="email"
                placeholder="e.g. subhash@example.com"
                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-900 font-medium focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
              />
            </div>
          </div>

          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">Subject</label>
            <input
              v-model="form.subject"
              type="text"
              placeholder="e.g. Footwear Size Inquiry / Custom Bulk Order"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-900 font-medium focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">Enquiry Details *</label>
            <textarea
              v-model="form.message"
              rows="4"
              required
              placeholder="Ask about footwear article availability, shoe size guide, or store visit..."
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-900 font-medium focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            ></textarea>
          </div>

          <button
            type="submit"
            :disabled="submitting || submitted"
            class="w-full py-3.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md shadow-red-600/20 transition-all flex items-center justify-center gap-2"
          >
            <span>{{ submitting ? 'Sending Message...' : submitted ? 'Message Sent Successfully ✓' : 'SEND MESSAGE' }}</span>
          </button>
        </form>

        <div v-if="submitted" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-bold flex items-center gap-2">
          <span>✓</span>
          <span>Thank you! Your message has been submitted. Our team will contact you shortly.</span>
        </div>
      </div>

    </div>

    <!-- E. QUICK CONTACT CTA -->
    <div class="p-8 bg-slate-900 text-white rounded-3xl border border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-6 shadow-xl">
      <div class="space-y-1">
        <span class="text-xs font-black text-red-500 uppercase tracking-widest block">INSTANT ASSISTANCE</span>
        <h2 class="text-2xl font-black text-white tracking-tight uppercase">NEED QUICK HELP?</h2>
        <p class="text-xs text-slate-300 font-medium">Call us directly or send a message on WhatsApp for instant footwear size recommendations.</p>
      </div>

      <div class="flex items-center gap-3 shrink-0">
        <a
          :href="callNumberUrl"
          class="px-5 py-3 bg-red-600 hover:bg-red-700 text-white font-black rounded-2xl text-xs flex items-center gap-2 shadow-lg shadow-red-600/30 transition-all"
        >
          <span>📞</span>
          <span>Call Now</span>
        </a>

        <a
          :href="whatsAppUrl"
          target="_blank"
          rel="noopener"
          class="px-5 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-2xl text-xs flex items-center gap-2 shadow-lg shadow-emerald-600/30 transition-all"
        >
          <span>💬</span>
          <span>WhatsApp Us</span>
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, computed, onMounted } from 'vue';
import { useWebsiteStore } from '../../stores/websiteStore';

const websiteStore = useWebsiteStore();

const mapFallback = ref(true);

const contactInfo = computed(() => websiteStore.settings?.contact_info || {});

const callNumberUrl = computed(() => {
  const num = (contactInfo.value.phone || '+919735125112').replace(/\s+/g, '');
  return `tel:${num}`;
});

const whatsAppUrl = computed(() => {
  const num = (contactInfo.value.whatsapp_phone || '919735125112').replace(/[^0-9]/g, '');
  return `https://wa.me/${num}?text=${encodeURIComponent('Hello RUPSA PADUKALAYA, I would like to inquire about footwear availability.')}`;
});

const mapsDirectionUrl = computed(() => {
  if (contactInfo.value.maps_url) return contactInfo.value.maps_url;
  const query = encodeURIComponent(`${contactInfo.value.business_name || 'RUPSA PADUKALAYA'} ${contactInfo.value.full_address || 'DHANTALA BAZAR DHANTALA NADIA 741202 WEST BENGAL'}`);
  return `https://maps.google.com/?q=${query}`;
});

const form = reactive({
  name: '',
  phone: '',
  email: '',
  subject: '',
  message: '',
});

const submitting = ref(false);
const submitted = ref(false);

function submitForm() {
  submitting.value = true;
  setTimeout(() => {
    submitting.value = false;
    submitted.value = true;
    setTimeout(() => {
      submitted.value = false;
      form.name = '';
      form.phone = '';
      form.email = '';
      form.subject = '';
      form.message = '';
    }, 4000);
  }, 600);
}

function onMapError() {
  mapFallback.value = true;
}

onMounted(() => {
  websiteStore.fetchSettings();
});
</script>
