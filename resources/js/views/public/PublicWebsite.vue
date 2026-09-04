<template>
  <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col font-sans">
    <!-- Public Header Navbar -->
    <header class="sticky top-0 z-40 bg-slate-900/90 backdrop-blur-md border-b border-slate-800">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-indigo-600 flex items-center justify-center font-black text-white text-xl shadow-lg shadow-indigo-600/30">
            R
          </div>
          <div>
            <h1 class="font-extrabold text-lg text-white tracking-wide">RUPSA PADUKALAYA</h1>
            <p class="text-[10px] text-slate-400 font-medium tracking-wider uppercase">Premium Footwear Retailer</p>
          </div>
        </div>

        <nav class="hidden md:flex items-center gap-8 text-sm font-semibold text-slate-300">
          <a href="#hero" class="hover:text-indigo-400 transition-colors">Home</a>
          <a href="#categories" class="hover:text-indigo-400 transition-colors">Categories</a>
          <a href="#featured" class="hover:text-indigo-400 transition-colors">Featured Shoes</a>
          <a href="#stores" class="hover:text-indigo-400 transition-colors">Store Locations</a>
          <a href="#contact" class="hover:text-indigo-400 transition-colors">Contact Us</a>
        </nav>

        <div class="flex items-center gap-3">
          <a
            href="/admin/login"
            class="px-4 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-md transition-all flex items-center gap-1.5"
          >
            <span>Staff Portal / ERP</span>
            <span>→</span>
          </a>
        </div>
      </div>
    </header>

    <!-- Main Content Body -->
    <main class="flex-1 space-y-16 py-8">
      <!-- Hero Banner Section -->
      <section id="hero" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-12">
        <div class="rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 border border-slate-800 p-8 sm:p-12 lg:p-16 shadow-2xl relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-8">
          <div class="max-w-xl space-y-6 z-10">
            <span class="inline-block px-3 py-1 text-xs font-bold uppercase tracking-wider rounded-full bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
              New Footwear Arrivals 2026
            </span>
            <h2 class="text-3xl sm:text-5xl font-black text-white leading-tight tracking-tight">
              Comfort & Elegance For Every Step.
            </h2>
            <p class="text-sm sm:text-base text-slate-300 leading-relaxed">
              Explore the authentic footwear collection at RUPSA PADUKALAYA. From executive leather shoes to traditional ethnic footwear, experience craftsmanship and comfort designed for all occasions.
            </p>
            <div class="flex items-center gap-4 pt-2">
              <a href="#featured" class="px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold shadow-lg transition-all">
                Browse Showcase
              </a>
              <a href="#stores" class="px-6 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-sm font-bold border border-slate-700 transition-all">
                Find Nearest Store
              </a>
            </div>
          </div>
          <div class="text-8xl sm:text-9xl opacity-90 select-none transform hover:scale-105 transition-transform duration-300">
            👟
          </div>
        </div>
      </section>

      <!-- Categories Section -->
      <section id="categories" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-10">
          <h3 class="text-2xl font-black text-white">Footwear Categories</h3>
          <p class="text-xs text-slate-400 mt-2">Tailored sizing, durable materials & ergonomic sole comfort</p>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div v-for="cat in categories" :key="cat.title" class="p-6 rounded-2xl bg-slate-900 border border-slate-800 hover:border-indigo-500 transition-all text-center space-y-3">
            <div class="text-4xl">{ cat.icon }</div>
            <h4 class="font-bold text-sm text-white">{ cat.title }</h4>
            <p class="text-xs text-slate-400">{ cat.desc }</p>
          </div>
        </div>
      </section>

      <!-- Featured Product Showcase -->
      <section id="featured" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-8">
          <div>
            <h3 class="text-2xl font-black text-white">Bestselling Shoes</h3>
            <p class="text-xs text-slate-400 mt-1">Curated footwear articles in stock</p>
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div v-for="shoe in featuredShoes" :key="shoe.name" class="rounded-2xl bg-slate-900 border border-slate-800 p-5 hover:border-indigo-500 transition-all flex flex-col justify-between">
            <div>
              <div class="h-40 bg-slate-950 rounded-xl flex items-center justify-center text-6xl mb-4 border border-slate-800/80">
                { shoe.icon }
              </div>
              <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-indigo-950 text-indigo-400 border border-indigo-800 inline-block mb-2">
                { shoe.article }
              </span>
              <h4 class="font-bold text-sm text-white mb-1">{ shoe.name }</h4>
              <p class="text-xs text-slate-400">{ shoe.color } | Available Sizes: { shoe.sizes }</p>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-800 flex items-center justify-between">
              <span class="text-base font-black text-emerald-400">₹{ shoe.price }</span>
              <span class="text-[11px] font-bold text-slate-400">In Store</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Store Locations Showcase -->
      <section id="stores" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="rounded-3xl bg-slate-900 border border-slate-800 p-8 sm:p-10">
          <div class="max-w-xl mb-8">
            <h3 class="text-2xl font-black text-white">Our Retail Outlets</h3>
            <p class="text-xs text-slate-400 mt-1">Visit our footwear stores across Kolkata & nearby regions</p>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div v-for="st in stores" :key="st.name" class="p-5 rounded-xl bg-slate-950 border border-slate-800 space-y-2">
              <div class="font-extrabold text-sm text-indigo-400">{ st.name }</div>
              <p class="text-xs text-slate-300">{ st.address }</p>
              <p class="text-xs text-slate-400 font-mono">Phone: { st.phone }</p>
            </div>
          </div>
        </div>
      </section>

      <!-- Customer Contact Form -->
      <section id="contact" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto rounded-3xl bg-slate-900 border border-slate-800 p-8">
          <h3 class="text-xl font-black text-white mb-2 text-center">Customer Enquiry & Feedback</h3>
          <p class="text-xs text-slate-400 mb-6 text-center">Have a question about shoe availability, bulk orders or store timing?</p>
          <form @submit.prevent="submitEnquiry" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <input type="text" placeholder="Your Full Name" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              <input type="email" placeholder="Email Address" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <textarea placeholder="Write your enquiry..." rows="3" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            <button type="submit" class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md transition-colors">
              Send Customer Enquiry
            </button>
            <p v-if="enquirySubmitted" class="text-xs text-emerald-400 text-center font-semibold pt-2">
              Thank you! Your enquiry has been received. Our team will contact you shortly.
            </p>
          </form>
        </div>
      </section>
    </main>

    <!-- Public Footer -->
    <footer class="bg-slate-950 border-t border-slate-800 py-10 text-xs text-slate-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div>
          <span class="font-bold text-slate-300">RUPSA PADUKALAYA</span> — Quality Footwear Retailer © 2026. All rights reserved.
        </div>
        <div class="flex items-center gap-6">
          <a href="/admin/login" class="text-indigo-400 font-bold hover:underline">Admin ERP / POS Portal</a>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const categories = [
  { icon: '👞', title: 'Men Formal', desc: 'Oxford, Derby & Executive Leather' },
  { icon: '👠', title: 'Women Footwear', desc: 'Ethnic Sandals, Heels & Flats' },
  { icon: '👟', title: 'Sports & Casual', desc: 'Sneakers, Running Shoes & Canvas' },
  { icon: '🩴', title: 'Daily Comfort', desc: 'Slippers, Flip-Flops & Ortho Support' },
];

const featuredShoes = [
  { icon: '👞', article: 'ART-805', name: 'Executive Oxford Leather', color: 'Black', sizes: '06 - 10', price: '1,792.00' },
  { icon: '👟', article: 'ART-902', name: 'Pro Running Sports Shoe', color: 'Navy / Blue', sizes: '07 - 11', price: '1,499.00' },
  { icon: '👠', article: 'ART-401', name: 'Traditional Ethnic Sandal', color: 'Gold / Tan', sizes: '04 - 08', price: '1,250.00' },
  { icon: '🩴', article: 'ART-105', name: 'Ortho Comfort Slipper', color: 'Brown', sizes: '06 - 10', price: '499.00' },
];

const stores = [
  { name: 'Main Bazaar Branch', address: '123 Main Road, Kolkata 700001', phone: '+91 9876543210' },
  { name: 'Station Road Store', address: '45 Station Square, Kolkata 700005', phone: '+91 9876543211' },
  { name: 'Metro Plaza Outlet', address: '88 Commercial Hub, Kolkata 700016', phone: '+91 9876543212' },
];

const enquirySubmitted = ref(false);

function submitEnquiry() {
  enquirySubmitted.value = true;
  setTimeout(() => {
    enquirySubmitted.value = false;
  }, 5000);
}
</script>
