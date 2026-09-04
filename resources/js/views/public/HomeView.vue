<template>
  <div class="space-y-12 pb-16 bg-slate-50 text-slate-800 antialiased font-sans max-w-full overflow-x-hidden">
    
    <!-- 1. HERO SLIDER BANNER (4 Auto-Rotating Slides) -->
    <section class="relative overflow-hidden bg-slate-950 text-white border-b border-slate-800">
      <div class="relative h-[340px] sm:h-[480px] lg:h-[540px] w-full flex items-center justify-center">
        <div
          v-for="(slide, idx) in heroSlides"
          :key="idx"
          :class="[
            'absolute inset-0 transition-opacity duration-700 ease-in-out flex items-center',
            currentSlide === idx ? 'opacity-100 z-10 pointer-events-auto' : 'opacity-0 z-0 pointer-events-none'
          ]"
        >
          <img
            :src="slide.image"
            :alt="slide.title"
            class="absolute inset-0 w-full h-full object-cover object-center opacity-40 filter brightness-90"
          />
          <div class="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-950/80 to-transparent"></div>

          <div class="relative max-w-7xl mx-auto px-6 sm:px-12 w-full space-y-3 sm:space-y-5">
            <span class="inline-block px-3 py-1 bg-red-600/90 text-white text-[10px] sm:text-xs font-black uppercase tracking-widest rounded-full shadow-md">
              {{ slide.tag }}
            </span>
            <h2 class="text-2xl sm:text-4xl lg:text-5xl font-black text-white tracking-tight leading-tight max-w-2xl">
              {{ slide.title }}
            </h2>
            <p class="text-xs sm:text-base text-slate-300 font-medium max-w-xl line-clamp-2">
              {{ slide.subtitle }}
            </p>
            <div class="pt-2">
              <router-link
                :to="slide.link"
                class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-black rounded-xl text-xs sm:text-sm inline-flex items-center gap-2 shadow-lg shadow-red-600/30 transition-all hover:translate-x-1"
              >
                <span>{{ slide.cta }}</span>
                <span>→</span>
              </router-link>
            </div>
          </div>
        </div>

        <button
          @click="prevSlide"
          class="absolute left-4 top-1/2 -translate-y-1/2 z-20 h-10 w-10 sm:h-12 sm:w-12 rounded-full bg-slate-900/60 hover:bg-slate-950 text-white flex items-center justify-center font-bold text-lg border border-white/20 backdrop-blur-xs transition-all"
          aria-label="Previous Slide"
        >
          ‹
        </button>
        <button
          @click="nextSlide"
          class="absolute right-4 top-1/2 -translate-y-1/2 z-20 h-10 w-10 sm:h-12 sm:w-12 rounded-full bg-slate-900/60 hover:bg-slate-950 text-white flex items-center justify-center font-bold text-lg border border-white/20 backdrop-blur-xs transition-all"
          aria-label="Next Slide"
        >
          ›
        </button>

        <div class="absolute bottom-4 left-1/2 -translate-x-1/2 z-20 flex items-center gap-2">
          <button
            v-for="(_, idx) in heroSlides"
            :key="idx"
            @click="currentSlide = idx"
            :class="[
              'h-2.5 rounded-full transition-all',
              currentSlide === idx ? 'w-8 bg-red-600' : 'w-2.5 bg-white/50 hover:bg-white'
            ]"
            :aria-label="`Go to slide ${idx + 1}`"
          ></button>
        </div>
      </div>
    </section>

    <!-- 2. FOUR SMALL PROMOTIONAL BANNERS (Desktop: 4 in 1 row / Mobile: 2x2 grid) -->
    <section v-if="hpContent.section_visibility?.small_banners !== false" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-5">
        <div
          v-for="(banner, idx) in smallBannersList"
          :key="banner.id || idx"
          @click="navigateToUrl(banner.url)"
          class="relative rounded-2xl overflow-hidden bg-slate-900 text-white h-32 sm:h-40 border border-slate-800 shadow-sm hover:shadow-md cursor-pointer group transition-all"
        >
          <img
            v-if="banner.image_url"
            :src="banner.image_url"
            :alt="banner.title"
            class="w-full h-full object-cover opacity-60 group-hover:scale-105 transition-transform duration-500"
          />
          <div v-else class="w-full h-full bg-gradient-to-br from-slate-900 via-slate-800 to-slate-950"></div>

          <div class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-950/40 to-transparent p-3.5 sm:p-4 flex flex-col justify-end space-y-1">
            <h4 class="font-black text-xs sm:text-sm text-white leading-tight">
              {{ banner.title }}
            </h4>
            <p class="text-[10px] text-slate-300 line-clamp-1">
              {{ banner.subtitle }}
            </p>
            <div class="pt-1">
              <span class="text-[10px] font-black text-red-400 group-hover:text-red-300 flex items-center gap-1">
                <span>{{ banner.button_text || 'Explore' }}</span>
                <span>→</span>
              </span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 3. SHOP BY CATEGORY SECTION (Dynamic Category Carousel) -->
    <section v-if="hpContent.section_visibility?.category_carousel !== false" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">
      <div class="flex items-center justify-between border-b border-slate-200 pb-3">
        <div>
          <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight uppercase flex items-center gap-2">
            <span>👟</span>
            <span>SHOP BY CATEGORY</span>
          </h2>
          <p class="text-xs text-slate-500 font-medium mt-0.5">Explore our wide selection of leather formals, casual sneakers, sports & ortho sandals.</p>
        </div>
        <div class="hidden sm:flex items-center gap-2">
          <button
            @click="scrollCategoryCarousel(-1)"
            class="h-9 w-9 rounded-xl bg-white border border-slate-200 shadow-2xs hover:bg-slate-100 font-bold text-slate-700 flex items-center justify-center"
          >
            ‹
          </button>
          <button
            @click="scrollCategoryCarousel(1)"
            class="h-9 w-9 rounded-xl bg-white border border-slate-200 shadow-2xs hover:bg-slate-100 font-bold text-slate-700 flex items-center justify-center"
          >
            ›
          </button>
        </div>
      </div>

      <!-- Category Carousel Container -->
      <div
        ref="categoryCarouselRef"
        class="flex items-stretch gap-3 sm:gap-5 overflow-x-auto scrollbar-none snap-x snap-mandatory py-2 scroll-smooth"
      >
        <div
          v-for="cat in categoriesList"
          :key="cat.id"
          @click="openCategory(cat.slug)"
          class="w-36 sm:w-48 shrink-0 bg-white rounded-2xl border border-slate-200/90 shadow-2xs hover:shadow-lg hover:border-red-600/50 transition-all duration-300 p-3 sm:p-4 text-center cursor-pointer group flex flex-col items-center justify-between space-y-3 snap-start"
        >
          <div class="h-20 w-20 sm:h-24 sm:w-24 rounded-full bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center p-2 group-hover:scale-105 transition-transform shrink-0">
            <img
              v-if="cat.image_url"
              :src="cat.image_url"
              :alt="cat.name"
              class="h-full w-full object-cover rounded-full"
            />
            <span v-else class="text-3xl">👟</span>
          </div>

          <div>
            <h3 class="font-black text-xs sm:text-sm text-slate-900 group-hover:text-red-600 transition-colors leading-tight">
              {{ cat.name }}
            </h3>
            <span class="text-[10px] text-slate-400 font-bold uppercase mt-0.5 block">Explore Range</span>
          </div>
        </div>
      </div>
    </section>

    <!-- 4. SECTION 1: NEW FOOTWEAR ARRIVALS (10 Products Grid: 5+5 Desktop / 2 per row Mobile with BUY ON WHATSAPP) -->
    <section v-if="hpContent.section_visibility?.section1 !== false" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
      <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
          <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight uppercase">
            {{ hpContent.section1_title || 'NEW FOOTWEAR ARRIVALS' }}
          </h2>
          <p class="text-xs text-slate-500 font-medium mt-0.5">Fresh hand-crafted footwear designed for style, durability & all-day arch comfort.</p>
        </div>
        <router-link to="/categories/men" class="text-xs font-black text-red-600 hover:text-red-700 flex items-center gap-1">
          <span>View All</span>
          <span>→</span>
        </router-link>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-5">
        <div
          v-for="product in section1Products"
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
            <span v-if="getDiscountPercent(product)" class="absolute top-2.5 left-2.5 bg-red-600 text-white font-black text-[9px] sm:text-[10px] px-2 py-0.5 rounded-md shadow-xs">
              {{ getDiscountPercent(product) }}% OFF
            </span>
            <span class="absolute top-2.5 right-2.5 bg-slate-900/80 backdrop-blur-xs text-white font-mono font-bold text-[8px] sm:text-[9px] px-1.5 py-0.5 rounded">
              ART: {{ product.article_number || product.code || 'RP-100' }}
            </span>
          </div>

          <div class="p-3.5 space-y-2 flex-1 flex flex-col justify-between text-xs">
            <div>
              <div class="text-[9px] font-extrabold text-red-600 uppercase tracking-widest">
                {{ product.brand?.name || 'RUPSA FOOTWEAR' }}
              </div>
              <h3 class="font-bold text-slate-900 text-xs sm:text-sm line-clamp-2 leading-tight mt-0.5">
                {{ product.name }}
              </h3>
            </div>

            <div class="space-y-2 pt-1 border-t border-slate-100">
              <div class="flex items-baseline gap-2">
                <span class="font-black text-slate-900 text-sm sm:text-base">₹{{ product.selling_price || product.mrp }}</span>
                <span v-if="product.mrp && product.mrp > product.selling_price" class="text-[10px] sm:text-xs text-slate-400 line-through">
                  ₹{{ product.mrp }}
                </span>
              </div>

              <!-- BUY ON WHATSAPP ACTION BUTTON -->
              <button
                @click="buyOnWhatsApp(product)"
                class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-xl text-[10px] sm:text-xs transition-colors flex items-center justify-center gap-1.5 shadow-md shadow-emerald-600/20"
              >
                <span>💬</span>
                <span>BUY ON WHATSAPP</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 5. FULL-WIDTH PROMOTIONAL BANNER 1 -->
    <section v-if="hpContent.section_visibility?.full_banner1 !== false" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="relative rounded-2xl sm:rounded-3xl overflow-hidden bg-slate-900 text-white shadow-xl border border-slate-800">
        <img
          v-if="hpContent.full_banner1?.image_url"
          :src="hpContent.full_banner1.image_url"
          :alt="hpContent.full_banner1.title"
          class="w-full h-48 sm:h-64 lg:h-80 object-cover opacity-60 filter brightness-95"
        />
        <div v-else class="w-full h-48 sm:h-64 lg:h-80 bg-gradient-to-r from-red-950 via-slate-900 to-slate-950 flex items-center"></div>

        <div class="absolute inset-0 bg-gradient-to-r from-slate-950/90 via-slate-950/60 to-transparent flex items-center p-6 sm:p-12">
          <div class="max-w-2xl space-y-3">
            <span class="inline-block px-3 py-1 bg-red-600 text-white text-[10px] sm:text-xs font-black uppercase tracking-wider rounded-md shadow-xs">
              LIMITED PERIOD FESTIVE OFFER
            </span>
            <h3 class="text-xl sm:text-3xl lg:text-4xl font-black text-white leading-tight">
              {{ hpContent.full_banner1?.title || 'MONSOON FOOTWEAR SALE — UP TO 50% OFF' }}
            </h3>
            <p class="text-xs sm:text-sm text-slate-300 font-medium line-clamp-2">
              {{ hpContent.full_banner1?.subtitle || 'Upgrade your footwear wardrobe with genuine leather oxfords, waterproof sandals & daily comfort slippers.' }}
            </p>
            <div class="pt-2">
              <router-link
                :to="hpContent.full_banner1?.url || '/categories/men'"
                class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white font-black rounded-xl text-xs sm:text-sm inline-flex items-center gap-2 shadow-lg shadow-red-600/30 transition-all"
              >
                <span>{{ hpContent.full_banner1?.button_text || 'Explore Monsoon Deals' }}</span>
                <span>→</span>
              </router-link>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 6. THREE PROMOTIONAL CATEGORY BANNERS -->
    <section v-if="hpContent.section_visibility?.promo_banners !== false" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6">
        <div
          v-for="(banner, idx) in hpContent.promo_banners"
          :key="banner.id || idx"
          class="relative rounded-2xl overflow-hidden bg-slate-900 text-white h-56 sm:h-64 lg:h-72 border border-slate-800 shadow-md group"
        >
          <img
            v-if="banner.image_url"
            :src="banner.image_url"
            :alt="banner.title"
            class="w-full h-full object-cover opacity-50 group-hover:scale-105 transition-transform duration-500"
          />
          <div v-else class="w-full h-full bg-gradient-to-br from-slate-900 via-slate-800 to-slate-950"></div>

          <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/40 to-transparent p-5 flex flex-col justify-end space-y-2">
            <h4 class="font-black text-base sm:text-lg text-white leading-tight">
              {{ banner.title }}
            </h4>
            <p class="text-[11px] text-slate-300 line-clamp-1">
              {{ banner.subtitle }}
            </p>
            <div>
              <router-link
                :to="banner.url || '/categories/men'"
                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg text-xs inline-flex items-center gap-1.5 shadow-md"
              >
                <span>{{ banner.button_text || 'Explore Category' }}</span>
                <span>→</span>
              </router-link>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 7. SECOND PRODUCT SECTION (10 Products Grid with BUY ON WHATSAPP) -->
    <section v-if="hpContent.section_visibility?.section2 !== false" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
      <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
          <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight uppercase">
            {{ hpContent.section2_title || 'TRENDING FOOTWEAR' }}
          </h2>
          <p class="text-xs text-slate-500 font-medium mt-0.5">Top customer picks and best-selling footwear styles across all categories.</p>
        </div>
        <router-link to="/categories/women" class="text-xs font-black text-red-600 hover:text-red-700 flex items-center gap-1">
          <span>View All</span>
          <span>→</span>
        </router-link>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-5">
        <div
          v-for="product in section2Products"
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
            <span v-if="getDiscountPercent(product)" class="absolute top-2.5 left-2.5 bg-red-600 text-white font-black text-[9px] sm:text-[10px] px-2 py-0.5 rounded-md shadow-xs">
              {{ getDiscountPercent(product) }}% OFF
            </span>
            <span class="absolute top-2.5 right-2.5 bg-slate-900/80 backdrop-blur-xs text-white font-mono font-bold text-[8px] sm:text-[9px] px-1.5 py-0.5 rounded">
              ART: {{ product.article_number || product.code || 'RP-200' }}
            </span>
          </div>

          <div class="p-3.5 space-y-2 flex-1 flex flex-col justify-between text-xs">
            <div>
              <div class="text-[9px] font-extrabold text-red-600 uppercase tracking-widest">
                {{ product.brand?.name || 'RUPSA FOOTWEAR' }}
              </div>
              <h3 class="font-bold text-slate-900 text-xs sm:text-sm line-clamp-2 leading-tight mt-0.5">
                {{ product.name }}
              </h3>
            </div>

            <div class="space-y-2 pt-1 border-t border-slate-100">
              <div class="flex items-baseline gap-2">
                <span class="font-black text-slate-900 text-sm sm:text-base">₹{{ product.selling_price || product.mrp }}</span>
                <span v-if="product.mrp && product.mrp > product.selling_price" class="text-[10px] sm:text-xs text-slate-400 line-through">
                  ₹{{ product.mrp }}
                </span>
              </div>

              <!-- BUY ON WHATSAPP ACTION BUTTON -->
              <button
                @click="buyOnWhatsApp(product)"
                class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-xl text-[10px] sm:text-xs transition-colors flex items-center justify-center gap-1.5 shadow-md shadow-emerald-600/20"
              >
                <span>💬</span>
                <span>BUY ON WHATSAPP</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 8. REELS / SHORT VIDEO SECTION (4 Cards - 9:16 Aspect Ratio) -->
    <section v-if="hpContent.section_visibility?.reels !== false" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
      <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
          <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight uppercase flex items-center gap-2">
            <span>🎬</span>
            <span>RUPSA REELS & FOOTWEAR SHOWCASE</span>
          </h2>
          <p class="text-xs text-slate-500 font-medium mt-0.5">Watch short video reels of our genuine leather craftsmanship & durability tests.</p>
        </div>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 sm:gap-6">
        <div
          v-for="(reel, idx) in hpContent.reels"
          :key="reel.id || idx"
          @click="playReel(reel)"
          class="relative aspect-[9/16] rounded-2xl overflow-hidden bg-slate-900 text-white shadow-xl border border-slate-800 cursor-pointer group"
        >
          <img
            v-if="reel.poster_url"
            :src="reel.poster_url"
            :alt="reel.title"
            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
          />
          <div v-else class="w-full h-full bg-gradient-to-b from-slate-800 via-slate-900 to-slate-950 flex items-center justify-center">
            <span class="text-4xl opacity-40">👟</span>
          </div>

          <div class="absolute inset-0 bg-slate-950/40 group-hover:bg-slate-950/20 transition-all flex items-center justify-center">
            <div class="h-12 w-12 rounded-full bg-red-600/90 text-white flex items-center justify-center font-black text-xl shadow-lg shadow-red-600/40 group-hover:scale-110 transition-transform">
              ▶
            </div>
          </div>

          <div class="absolute inset-x-0 bottom-0 p-3.5 bg-gradient-to-t from-slate-950 via-slate-950/80 to-transparent space-y-1">
            <span class="text-[9px] font-black text-red-500 uppercase tracking-widest block">{{ reel.tag || '#RupsaStyle' }}</span>
            <h4 class="font-bold text-xs sm:text-sm text-white line-clamp-2 leading-tight">
              {{ reel.title }}
            </h4>
          </div>
        </div>
      </div>
    </section>

    <!-- 9. SECOND FULL-WIDTH BANNER -->
    <section v-if="hpContent.section_visibility?.full_banner2 !== false" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="relative rounded-2xl sm:rounded-3xl overflow-hidden bg-slate-900 text-white shadow-xl border border-slate-800">
        <img
          v-if="hpContent.full_banner2?.image_url"
          :src="hpContent.full_banner2.image_url"
          :alt="hpContent.full_banner2.title"
          class="w-full h-48 sm:h-64 lg:h-80 object-cover opacity-60 filter brightness-95"
        />
        <div v-else class="w-full h-48 sm:h-64 lg:h-80 bg-gradient-to-r from-slate-900 via-red-950 to-slate-950 flex items-center"></div>

        <div class="absolute inset-0 bg-gradient-to-r from-slate-950/90 via-slate-950/60 to-transparent flex items-center p-6 sm:p-12">
          <div class="max-w-2xl space-y-3">
            <span class="inline-block px-3 py-1 bg-red-600 text-white text-[10px] sm:text-xs font-black uppercase tracking-wider rounded-md shadow-xs">
              WOMEN'S SPECIAL COLLECTION
            </span>
            <h3 class="text-xl sm:text-3xl lg:text-4xl font-black text-white leading-tight">
              {{ hpContent.full_banner2?.title || 'WOMEN\'S ETHNIC & DAILY COMFORT COLLECTION' }}
            </h3>
            <p class="text-xs sm:text-sm text-slate-300 font-medium line-clamp-2">
              {{ hpContent.full_banner2?.subtitle || 'Hand-stitched footwear engineered for maximum arch support and day-long elegance.' }}
            </p>
            <div class="pt-2">
              <router-link
                :to="hpContent.full_banner2?.url || '/categories/women'"
                class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white font-black rounded-xl text-xs sm:text-sm inline-flex items-center gap-2 shadow-lg shadow-red-600/30 transition-all"
              >
                <span>{{ hpContent.full_banner2?.button_text || 'Explore Women Collection' }}</span>
                <span>→</span>
              </router-link>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 10. FINAL HORIZONTAL PRODUCT CAROUSEL (10 Products with BUY ON WHATSAPP) -->
    <section v-if="hpContent.section_visibility?.carousel !== false" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
      <div class="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
          <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight uppercase flex items-center gap-2">
            <span>⚡</span>
            <span>{{ hpContent.carousel_title || 'BEST SELLING FOOTWEAR' }}</span>
          </h2>
          <p class="text-xs text-slate-500 font-medium mt-0.5">Swipe horizontally or use arrows to explore top-rated footwear articles.</p>
        </div>
        <div class="hidden sm:flex items-center gap-2">
          <button
            @click="scrollCarousel(-1)"
            class="h-9 w-9 rounded-xl bg-white border border-slate-200 shadow-2xs hover:bg-slate-100 font-bold text-slate-700 flex items-center justify-center"
          >
            ‹
          </button>
          <button
            @click="scrollCarousel(1)"
            class="h-9 w-9 rounded-xl bg-white border border-slate-200 shadow-2xs hover:bg-slate-100 font-bold text-slate-700 flex items-center justify-center"
          >
            ›
          </button>
        </div>
      </div>

      <div
        ref="carouselRef"
        class="flex items-stretch gap-4 sm:gap-6 overflow-x-auto scrollbar-none snap-x snap-mandatory py-2 scroll-smooth"
      >
        <div
          v-for="product in carouselProducts"
          :key="product.id"
          class="w-44 sm:w-60 shrink-0 bg-white rounded-2xl border border-slate-200/90 shadow-xs hover:shadow-xl hover:border-emerald-600/40 transition-all duration-300 flex flex-col justify-between overflow-hidden group snap-start"
        >
          <div class="relative bg-slate-100 aspect-square overflow-hidden flex items-center justify-center p-3">
            <img
              :src="getProductImage(product)"
              :alt="product.name"
              class="h-full w-full object-cover object-center group-hover:scale-105 transition-transform duration-500"
              loading="lazy"
            />
            <span v-if="getDiscountPercent(product)" class="absolute top-2.5 left-2.5 bg-red-600 text-white font-black text-[9px] sm:text-[10px] px-2 py-0.5 rounded-md shadow-xs">
              {{ getDiscountPercent(product) }}% OFF
            </span>
          </div>

          <div class="p-3.5 space-y-2 flex-1 flex flex-col justify-between text-xs">
            <div>
              <div class="text-[9px] font-extrabold text-red-600 uppercase tracking-widest">
                {{ product.brand?.name || 'RUPSA FOOTWEAR' }}
              </div>
              <h3 class="font-bold text-slate-900 text-xs sm:text-sm line-clamp-2 leading-tight mt-0.5">
                {{ product.name }}
              </h3>
            </div>

            <div class="space-y-2 pt-1 border-t border-slate-100">
              <div class="flex items-baseline gap-2">
                <span class="font-black text-slate-900 text-sm sm:text-base">₹{{ product.selling_price || product.mrp }}</span>
                <span v-if="product.mrp && product.mrp > product.selling_price" class="text-[10px] sm:text-xs text-slate-400 line-through">
                  ₹{{ product.mrp }}
                </span>
              </div>

              <!-- BUY ON WHATSAPP ACTION BUTTON -->
              <button
                @click="buyOnWhatsApp(product)"
                class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-xl text-[10px] sm:text-xs transition-colors flex items-center justify-center gap-1.5 shadow-md shadow-emerald-600/20"
              >
                <span>💬</span>
                <span>BUY ON WHATSAPP</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 11. OUR PARTNERS SECTION (Dynamic Brand Carousel) -->
    <section v-if="hpContent.section_visibility?.our_partners !== false && brandsList.length > 0" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
      <div class="text-center space-y-1">
        <h2 class="text-xl sm:text-3xl font-black text-slate-900 tracking-tight uppercase">
          OUR PARTNERS
        </h2>
        <p class="text-xs sm:text-sm text-slate-500 font-bold">Trusted Brands, Premium Quality</p>
        <div class="w-12 h-1 bg-red-600 rounded-full mx-auto mt-2"></div>
      </div>

      <div class="relative">
        <div
          ref="partnerCarouselRef"
          class="flex items-center gap-4 sm:gap-6 overflow-x-auto scrollbar-none snap-x snap-mandatory py-3 scroll-smooth"
        >
          <div
            v-for="brand in brandsList"
            :key="brand.id"
            @click="openCategory('men')"
            class="w-36 sm:w-48 shrink-0 bg-white rounded-2xl border border-slate-200/90 shadow-2xs hover:shadow-lg hover:border-red-600/50 transition-all duration-300 p-4 text-center cursor-pointer group flex flex-col items-center justify-center space-y-2 snap-start"
          >
            <div class="h-16 w-28 flex items-center justify-center p-2 overflow-hidden">
              <img
                v-if="brand.logo_url || brand.image_url"
                :src="brand.logo_url || brand.image_url"
                :alt="brand.name"
                class="max-h-full max-w-full object-contain filter group-hover:brightness-110 transition-all"
              />
              <span v-else class="font-black text-sm text-slate-800 tracking-wider group-hover:text-red-600 transition-colors uppercase">
                {{ brand.name }}
              </span>
            </div>
            <span class="text-[10px] text-slate-400 font-bold uppercase block">{{ brand.products_count ? `${brand.products_count} Articles` : 'Official Partner' }}</span>
          </div>
        </div>

        <button
          @click="scrollPartnerCarousel(-1)"
          class="absolute left-0 top-1/2 -translate-y-1/2 h-9 w-9 rounded-full bg-white/90 border border-slate-200 shadow-md text-slate-800 font-bold flex items-center justify-center hover:bg-slate-100 transition-all -ml-3"
          aria-label="Previous Brand"
        >
          ‹
        </button>
        <button
          @click="scrollPartnerCarousel(1)"
          class="absolute right-0 top-1/2 -translate-y-1/2 h-9 w-9 rounded-full bg-white/90 border border-slate-200 shadow-md text-slate-800 font-bold flex items-center justify-center hover:bg-slate-100 transition-all -mr-3"
          aria-label="Next Brand"
        >
          ›
        </button>
      </div>
    </section>

    <!-- 12. WHY CHOOSE RUPSA SECTION -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="bg-white rounded-3xl p-8 border border-slate-200/90 shadow-sm grid grid-cols-1 md:grid-cols-4 gap-6 text-center">
        <div class="space-y-2 p-4 rounded-2xl bg-slate-50 border border-slate-100">
          <div class="text-3xl">🚚</div>
          <h4 class="font-black text-sm text-slate-900">Free Nationwide Express Delivery</h4>
          <p class="text-xs text-slate-500">Free shipping on all orders above ₹500 across India.</p>
        </div>

        <div class="space-y-2 p-4 rounded-2xl bg-slate-50 border border-slate-100">
          <div class="text-3xl">🦶</div>
          <h4 class="font-black text-sm text-slate-900">Ergonomic Arch Support</h4>
          <p class="text-xs text-slate-500">Orthopedically tested cushioning for daily comfort.</p>
        </div>

        <div class="space-y-2 p-4 rounded-2xl bg-slate-50 border border-slate-100">
          <div class="text-3xl">👟</div>
          <h4 class="font-black text-sm text-slate-900">100% Genuine Leather</h4>
          <p class="text-xs text-slate-500">Authentic Indian leather craftsmanship & hand stitching.</p>
        </div>

        <div class="space-y-2 p-4 rounded-2xl bg-slate-50 border border-slate-100">
          <div class="text-3xl">↩️</div>
          <h4 class="font-black text-sm text-slate-900">Easy 15-Day Replacements</h4>
          <p class="text-xs text-slate-500">Hassle-free size replacement and return assurance.</p>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useWebsiteStore } from '../../stores/websiteStore';
import { useWhatsApp } from '../../composables/useWhatsApp';
import api from '../../services/api';

const router = useRouter();
const websiteStore = useWebsiteStore();
const { buyOnWhatsApp } = useWhatsApp();

const currentSlide = ref(0);
let slideTimer = null;
const carouselRef = ref(null);
const categoryCarouselRef = ref(null);
const partnerCarouselRef = ref(null);

const productsList = ref([]);
const categoriesList = ref([]);
const brandsList = ref([]);

const hpContent = computed(() => websiteStore.settings.homepage_content || {});

const smallBannersList = computed(() => hpContent.value.small_banners || [
  { id: 1, title: 'Executive Leather', subtitle: 'Oxfords & Brogues', button_text: 'Explore', url: '/categories/men', image_url: '' },
  { id: 2, title: 'Comfort Ortho', subtitle: 'Daily Slippers', button_text: 'Explore', url: '/categories/slippers', image_url: '' },
  { id: 3, title: 'Ethnic Sandals', subtitle: 'Festive Collection', button_text: 'Explore', url: '/categories/sandals', image_url: '' },
  { id: 4, title: 'Pro Sport Sneakers', subtitle: 'Air Cushion Soles', button_text: 'Explore', url: '/categories/sports-shoes', image_url: '' },
]);

const heroSlides = ref([
  {
    tag: 'FESTIVE COLLECTION 2026',
    title: 'Executive Genuine Leather & Ergonomic Arch Comfort',
    subtitle: 'Hand-stitched oxfords, formal brogues and daily ortho casuals built for Indian foot ergonomics.',
    cta: 'Shop Men Collection',
    link: '/categories/men',
    image: 'https://images.unsplash.com/photo-1549298916-b41d501d3772?auto=format&fit=crop&w=1600&q=80',
  },
  {
    tag: 'WOMEN COMFORT RANGE',
    title: 'Ethno-Modern Footwear & Soft Memory Cushion Heels',
    subtitle: 'Step into grace with feather-light soles and high-density memory arch cushioning.',
    cta: 'Explore Women Shoes',
    link: '/categories/women',
    image: 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=1600&q=80',
  },
  {
    tag: 'MONSOON SPECIAL DEALS',
    title: 'Waterproof Anti-Skid Rubber & Daily Ortho Slippers',
    subtitle: 'Heavy-duty non-slip grip soles engineered for monsoon durability and all-day traction.',
    cta: 'Shop Casuals & Slippers',
    link: '/categories/others',
    image: 'https://images.unsplash.com/photo-1608231387042-66d1773070a5?auto=format&fit=crop&w=1600&q=80',
  },
  {
    tag: 'KIDS ACTIVE RANGE',
    title: 'Vibrant Play-Proof Sneakers & School Uniform Shoes',
    subtitle: 'Ultra-flexible lightweight soles designed for energetic feet and daily school activities.',
    cta: 'Shop Kids Footwear',
    link: '/categories/kids',
    image: 'https://images.unsplash.com/photo-1514989940723-e8e51635b782?auto=format&fit=crop&w=1600&q=80',
  },
]);

const section1Products = computed(() => productsList.value.slice(0, 10));
const section2Products = computed(() => {
  if (productsList.value.length > 10) return productsList.value.slice(10, 20);
  return productsList.value.slice(0, 10);
});
const carouselProducts = computed(() => {
  if (productsList.value.length > 5) return productsList.value.slice(0, 10);
  return productsList.value;
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

function navigateToUrl(url) {
  if (!url) return;
  if (url.startsWith('/')) {
    router.push(url);
  } else {
    window.open(url, '_blank');
  }
}

function openCategory(slug) {
  if (slug) router.push(`/categories/${slug}`);
}

function nextSlide() {
  currentSlide.value = (currentSlide.value + 1) % heroSlides.value.length;
}

function prevSlide() {
  currentSlide.value = (currentSlide.value - 1 + heroSlides.value.length) % heroSlides.value.length;
}

function scrollCarousel(direction) {
  if (carouselRef.value) {
    carouselRef.value.scrollBy({ left: direction * 300, behavior: 'smooth' });
  }
}

function scrollCategoryCarousel(direction) {
  if (categoryCarouselRef.value) {
    categoryCarouselRef.value.scrollBy({ left: direction * 240, behavior: 'smooth' });
  }
}

function scrollPartnerCarousel(direction) {
  if (partnerCarouselRef.value) {
    partnerCarouselRef.value.scrollBy({ left: direction * 240, behavior: 'smooth' });
  }
}

function playReel(reel) {
  if (reel.url) {
    navigateToUrl(reel.url);
  } else {
    alert(`Playing Reel: ${reel.title}`);
  }
}

async function loadProducts() {
  try {
    const res = await api.get('/public/products');
    const data = res.data || res;
    productsList.value = Array.isArray(data) ? data : [];
  } catch (err) {
    console.error('Failed to load public products:', err);
  }
}

async function loadCategories() {
  try {
    const res = await api.get('/public/categories');
    const data = res.data || res;
    categoriesList.value = Array.isArray(data) ? data : [];
  } catch (err) {
    console.error('Failed to load public categories:', err);
  }
}

async function loadBrands() {
  try {
    const res = await api.get('/public/brands');
    const data = res.data || res;
    brandsList.value = Array.isArray(data) ? data : [];
  } catch (err) {
    console.error('Failed to load public brands:', err);
  }
}

onMounted(() => {
  websiteStore.fetchSettings();
  loadProducts();
  loadCategories();
  loadBrands();
  slideTimer = setInterval(nextSlide, 5000);
});

onUnmounted(() => {
  if (slideTimer) clearInterval(slideTimer);
});
</script>

<style scoped>
.scrollbar-none::-webkit-scrollbar {
  display: none;
}
.scrollbar-none {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
