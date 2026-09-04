<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-16 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Front Website Management</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Central management panel for public RUPSA PADUKALAYA homepage content, header, footer, favicon, business contact & banners.
        </p>
      </div>
      <div class="flex items-center gap-3">
        <button
          @click="showPreviewModal = true"
          class="px-3.5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-xs"
        >
          <span>👁️</span>
          <span>Preview Website</span>
        </button>
        <button
          @click="fetchSettings"
          class="px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-colors"
        >
          Reset
        </button>
        <button
          v-if="hasPermission('products.edit')"
          @click="publishSettings"
          :disabled="saving"
          class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-black shadow-md shadow-red-600/20 transition-all flex items-center gap-2"
        >
          <span>🚀</span>
          <span>{{ saving ? 'Publishing...' : 'Publish Changes' }}</span>
        </button>
      </div>
    </div>

    <!-- Notification Alert -->
    <div v-if="saveSuccess" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-bold flex items-center gap-2">
      <span>✓</span>
      <span>Front website settings published successfully. The live public website will update automatically.</span>
    </div>

    <!-- Settings Navigation Tabs -->
    <div class="bg-white p-2 rounded-2xl border border-slate-200/90 shadow-xs flex items-center gap-2 overflow-x-auto scrollbar-none text-xs font-bold">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        @click="activeTab = tab.id"
        :class="[
          'px-4 py-2.5 rounded-xl transition-all whitespace-nowrap flex items-center gap-2',
          activeTab === tab.id ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'
        ]"
      >
        <span>{{ tab.icon }}</span>
        <span>{{ tab.label }}</span>
      </button>
    </div>

    <!-- ========================================== -->
    <!-- TAB 1: HOME PAGE CONTENT MANAGEMENT        -->
    <!-- ========================================== -->
    <div v-if="activeTab === 'homepage'" class="space-y-6">
      
      <!-- SUB-NAVIGATION / SECTION ACCORDION SELECTOR -->
      <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="font-black text-sm text-slate-900 flex items-center gap-2">
              <span>🏠</span>
              <span>Home Page Central Management Panel</span>
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">Manage banners, categories, brands, product sections, video reels and section display ordering.</p>
          </div>
          <button
            @click="showPreviewModal = true"
            class="px-3 py-1.5 bg-red-50 text-red-700 hover:bg-red-100 rounded-lg text-xs font-bold border border-red-200 transition-colors"
          >
            Live Preview
          </button>
        </div>

        <!-- Section Navigation Pills -->
        <div class="flex items-center gap-2 overflow-x-auto scrollbar-none text-xs font-bold pt-1">
          <button
            v-for="sec in hpSubTabs"
            :key="sec.id"
            @click="activeHpSubTab = sec.id"
            :class="[
              'px-3.5 py-1.5 rounded-lg transition-all whitespace-nowrap',
              activeHpSubTab === sec.id ? 'bg-red-600 text-white shadow-2xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
            ]"
          >
            {{ sec.label }}
          </button>
        </div>
      </div>

      <!-- 1. MAIN HERO BANNER SLIDER MANAGER -->
      <div v-if="activeHpSubTab === 'hero'" class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-6">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="font-black text-sm text-slate-900 flex items-center gap-2">
              <span>🖼️</span>
              <span>Main Hero Banner Slider ({{ settings.homepage_content.hero_slides?.length || 0 }} Slides)</span>
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">Add, edit, enable/disable, reorder and upload images for top hero carousel slides.</p>
          </div>
          <button
            @click="addHeroSlide"
            class="px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition-colors"
          >
            ➕ Add Hero Slide
          </button>
        </div>

        <div class="space-y-4">
          <div
            v-for="(slide, idx) in settings.homepage_content.hero_slides"
            :key="slide.id || idx"
            class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-4"
          >
            <div class="flex items-center justify-between border-b border-slate-200 pb-2">
              <div class="flex items-center gap-2">
                <span class="font-black text-xs text-slate-900">Slide #{{ idx + 1 }}</span>
                <span :class="['px-2 py-0.5 rounded text-[10px] font-black', slide.enabled !== false ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600']">
                  {{ slide.enabled !== false ? 'Active' : 'Disabled' }}
                </span>
              </div>
              <div class="flex items-center gap-2 text-xs">
                <button @click="moveArrayItem(settings.homepage_content.hero_slides, idx, -1)" :disabled="idx === 0" class="px-2 py-1 bg-white border rounded font-bold disabled:opacity-30">↑</button>
                <button @click="moveArrayItem(settings.homepage_content.hero_slides, idx, 1)" :disabled="idx === settings.homepage_content.hero_slides.length - 1" class="px-2 py-1 bg-white border rounded font-bold disabled:opacity-30">↓</button>
                <button @click="deleteArrayItem(settings.homepage_content.hero_slides, idx)" class="px-2.5 py-1 bg-red-50 text-red-700 rounded font-bold">Remove</button>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
              <div class="space-y-2">
                <label class="font-bold text-slate-700 block">Banner Image (Upload File)</label>
                <div v-if="slide.image" class="h-28 w-full bg-slate-900 rounded-xl overflow-hidden relative border border-slate-300">
                  <img :src="slide.image" class="h-full w-full object-cover" />
                </div>
                <input type="file" accept="image/*" @change="uploadMediaFile($event, (url) => slide.image = url)" class="w-full text-[10px]" />
              </div>

              <div class="space-y-3">
                <div class="grid grid-cols-2 gap-2">
                  <div>
                    <label class="font-bold text-slate-700 block">Tag / Category Badge</label>
                    <input v-model="slide.tag" type="text" placeholder="FESTIVE COLLECTION 2026" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 font-bold text-slate-900" />
                  </div>
                  <div>
                    <label class="font-bold text-slate-700 block">CTA Button Text</label>
                    <input v-model="slide.cta" type="text" placeholder="Shop Collection" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 font-bold text-slate-900" />
                  </div>
                </div>

                <div>
                  <label class="font-bold text-slate-700 block">Slide Title</label>
                  <input v-model="slide.title" type="text" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 font-bold text-slate-900" />
                </div>

                <div>
                  <label class="font-bold text-slate-700 block">Slide Subtitle</label>
                  <input v-model="slide.subtitle" type="text" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-slate-700" />
                </div>

                <div>
                  <label class="font-bold text-slate-700 block">Target Link URL</label>
                  <input v-model="slide.link" type="text" placeholder="/categories/men" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 font-mono text-slate-900" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 2. 4 SMALL PROMOTIONAL BANNERS MANAGER -->
      <div v-if="activeHpSubTab === 'small_banners'" class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-6">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="font-black text-sm text-slate-900 flex items-center gap-2">
              <span>🖼️</span>
              <span>4 Small Promotional Banners (Below Hero Slider)</span>
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">Manage 4 small promotional banners positioned directly below top hero slider.</p>
          </div>
          <label class="flex items-center gap-2 font-bold text-xs text-slate-800 cursor-pointer bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-200">
            <input v-model="settings.homepage_content.section_visibility.small_banners" type="checkbox" class="rounded text-red-600" />
            <span>Enable Section</span>
          </label>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
          <div v-for="(b, idx) in settings.homepage_content.small_banners" :key="b.id || idx" class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
            <div class="font-black text-xs text-slate-900">Small Banner {{ idx + 1 }}</div>

            <div class="space-y-1">
              <label class="font-bold text-slate-700 block">Image Upload</label>
              <div v-if="b.image_url" class="h-20 w-full bg-slate-200 rounded-xl overflow-hidden border border-slate-300">
                <img :src="b.image_url" class="h-full w-full object-cover" />
              </div>
              <input type="file" accept="image/*" @change="uploadMediaFile($event, (url) => b.image_url = url)" class="w-full text-[10px]" />
            </div>

            <div class="space-y-1">
              <label class="font-bold text-slate-700 block">Title & Subtitle</label>
              <input v-model="b.title" type="text" placeholder="Title" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 font-bold text-slate-900 mb-1" />
              <input v-model="b.subtitle" type="text" placeholder="Subtitle" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-slate-700" />
            </div>

            <div class="space-y-1">
              <label class="font-bold text-slate-700 block">Link URL</label>
              <input v-model="b.url" type="text" placeholder="/categories/..." class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 font-mono text-slate-900" />
            </div>
          </div>
        </div>
      </div>

      <!-- 3. SHOP BY CATEGORY MANAGER -->
      <div v-if="activeHpSubTab === 'categories'" class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-6">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="font-black text-sm text-slate-900 flex items-center gap-2">
              <span>🏷️</span>
              <span>Shop By Category Selection (From Master Data)</span>
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">Select which footwear categories from Master Data appear on the public homepage slider.</p>
          </div>
          <label class="flex items-center gap-2 font-bold text-xs text-slate-800 cursor-pointer bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-200">
            <input v-model="settings.homepage_content.section_visibility.category_carousel" type="checkbox" class="rounded text-red-600" />
            <span>Enable Category Section</span>
          </label>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 text-xs">
          <div
            v-for="cat in masterCategories"
            :key="cat.id"
            @click="toggleCategorySelection(cat.id)"
            :class="[
              'p-3 rounded-2xl border transition-all cursor-pointer flex flex-col items-center justify-center text-center space-y-2',
              isCategorySelected(cat.id) ? 'bg-red-50 border-red-600 shadow-2xs' : 'bg-slate-50 border-slate-200 hover:bg-slate-100'
            ]"
          >
            <div class="h-12 w-12 rounded-full bg-white border border-slate-200 overflow-hidden flex items-center justify-center p-1">
              <img v-if="cat.image_url" :src="cat.image_url" class="h-full w-full object-cover rounded-full" />
              <span v-else class="text-xl">👟</span>
            </div>
            <span class="font-bold text-slate-900 text-[11px] leading-tight">{{ cat.name }}</span>
            <span :class="['text-[9px] font-black uppercase px-2 py-0.5 rounded-full', isCategorySelected(cat.id) ? 'bg-red-600 text-white' : 'bg-slate-200 text-slate-600']">
              {{ isCategorySelected(cat.id) ? 'Selected' : 'Click to Add' }}
            </span>
          </div>
        </div>
      </div>

      <!-- 4. OUR PARTNERS / BRANDS MANAGER -->
      <div v-if="activeHpSubTab === 'brands'" class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-6">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="font-black text-sm text-slate-900 flex items-center gap-2">
              <span>🏅</span>
              <span>Our Partners / Brands Selection (From Master Data)</span>
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">Select which brand logos appear in the "OUR PARTNERS" footer strip carousel.</p>
          </div>
          <label class="flex items-center gap-2 font-bold text-xs text-slate-800 cursor-pointer bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-200">
            <input v-model="settings.homepage_content.section_visibility.our_partners" type="checkbox" class="rounded text-red-600" />
            <span>Enable Partners Section</span>
          </label>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4 text-xs">
          <div
            v-for="brand in masterBrands"
            :key="brand.id"
            @click="toggleBrandSelection(brand.id)"
            :class="[
              'p-3 rounded-2xl border transition-all cursor-pointer flex flex-col items-center justify-center text-center space-y-2',
              isBrandSelected(brand.id) ? 'bg-red-50 border-red-600 shadow-2xs' : 'bg-slate-50 border-slate-200 hover:bg-slate-100'
            ]"
          >
            <div class="h-12 w-24 bg-white border border-slate-200 rounded-xl overflow-hidden flex items-center justify-center p-1">
              <img v-if="brand.logo_url || brand.image_url" :src="brand.logo_url || brand.image_url" class="max-h-full max-w-full object-contain" />
              <span v-else class="font-black text-slate-800 text-xs uppercase">{{ brand.name }}</span>
            </div>
            <span class="font-bold text-slate-900 text-[11px]">{{ brand.name }}</span>
            <span :class="['text-[9px] font-black uppercase px-2 py-0.5 rounded-full', isBrandSelected(brand.id) ? 'bg-red-600 text-white' : 'bg-slate-200 text-slate-600']">
              {{ isBrandSelected(brand.id) ? 'Selected' : 'Click to Add' }}
            </span>
          </div>
        </div>
      </div>

      <!-- 5. DYNAMIC HOMEPAGE PRODUCT SECTIONS BUILDER -->
      <div v-if="activeHpSubTab === 'product_sections'" class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-6">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="font-black text-sm text-slate-900 flex items-center gap-2">
              <span>⚡</span>
              <span>Dynamic Product Sections Builder</span>
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">Create custom product grids (e.g. New Arrivals, Trending, Best Sellers) with Master Data search.</p>
          </div>
          <button
            @click="addProductSection"
            class="px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition-colors"
          >
            ➕ Add Product Section
          </button>
        </div>

        <div class="space-y-6">
          <div
            v-for="(sec, sIdx) in settings.homepage_content.product_sections"
            :key="sec.id || sIdx"
            class="p-5 bg-slate-50 border border-slate-200 rounded-2xl space-y-4"
          >
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
              <div class="flex items-center gap-3">
                <input v-model="sec.title" type="text" placeholder="Section Title (e.g. New Footwear Arrivals)" class="bg-white border border-slate-300 rounded-xl px-3 py-1.5 font-black text-sm text-slate-900 w-64 sm:w-80" />
                <label class="flex items-center gap-1.5 font-bold text-xs cursor-pointer">
                  <input v-model="sec.enabled" type="checkbox" class="rounded text-red-600" />
                  <span>Enabled</span>
                </label>
              </div>

              <div class="flex items-center gap-2 text-xs">
                <button @click="openProductPicker(sIdx)" class="px-3 py-1.5 bg-red-600 text-white font-bold rounded-xl shadow-xs">
                  🔍 Select Products ({{ sec.product_ids?.length || 0 }})
                </button>
                <button @click="deleteArrayItem(settings.homepage_content.product_sections, sIdx)" class="px-3 py-1.5 bg-red-50 text-red-700 font-bold rounded-xl">
                  Remove Section
                </button>
              </div>
            </div>

            <!-- Selected Products List -->
            <div v-if="sec.product_ids && sec.product_ids.length > 0" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3 text-xs">
              <div
                v-for="(pId, pIdx) in sec.product_ids"
                :key="pId"
                class="bg-white p-2.5 rounded-xl border border-slate-200 flex flex-col justify-between space-y-2 relative group"
              >
                <div class="h-16 w-full bg-slate-100 rounded-lg overflow-hidden flex items-center justify-center p-1">
                  <img :src="getProductById(pId)?.primary_image_url || getProductById(pId)?.image_url" class="h-full w-full object-cover" />
                </div>
                <div class="text-[10px]">
                  <span class="font-bold text-slate-900 block truncate">{{ getProductById(pId)?.name || `Article #${pId}` }}</span>
                  <span class="text-slate-400 block font-mono">₹{{ getProductById(pId)?.selling_price }}</span>
                </div>
                <button
                  @click="removeProductFromSection(sIdx, pIdx)"
                  class="absolute top-1 right-1 h-5 w-5 rounded-full bg-red-600 text-white font-bold text-[10px] flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                >
                  ✕
                </button>
              </div>
            </div>

            <div v-else class="p-4 text-center text-xs text-slate-400 bg-white rounded-xl border border-dashed border-slate-200 font-bold">
              No products assigned yet. Click "Select Products" to choose articles from Master Data.
            </div>
          </div>
        </div>
      </div>

      <!-- 6. FULL-WIDTH BANNERS MANAGER -->
      <div v-if="activeHpSubTab === 'full_banners'" class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-6">
        <div class="border-b border-slate-100 pb-3">
          <h3 class="font-black text-sm text-slate-900">Full-Width Promotional Banners</h3>
          <p class="text-xs text-slate-500 mt-0.5">Manage large full-width promotional banners displayed between product sections.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
          <!-- Full Banner 1 -->
          <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
            <div class="font-black text-slate-900 flex justify-between items-center">
              <span>Full-Width Banner 1</span>
              <label class="flex items-center gap-1.5 font-bold cursor-pointer">
                <input v-model="settings.homepage_content.full_banner1.enabled" type="checkbox" class="rounded text-red-600" />
                <span>Enabled</span>
              </label>
            </div>
            <div class="space-y-1">
              <label class="font-bold text-slate-700 block">Banner Image Upload</label>
              <div v-if="settings.homepage_content.full_banner1.image_url" class="h-24 w-full bg-slate-900 rounded-xl overflow-hidden">
                <img :src="settings.homepage_content.full_banner1.image_url" class="h-full w-full object-cover" />
              </div>
              <input type="file" accept="image/*" @change="uploadMediaFile($event, (url) => settings.homepage_content.full_banner1.image_url = url)" class="w-full text-[10px]" />
            </div>
            <input v-model="settings.homepage_content.full_banner1.title" type="text" placeholder="Title" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 font-bold text-slate-900" />
            <input v-model="settings.homepage_content.full_banner1.subtitle" type="text" placeholder="Subtitle" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-slate-700" />
            <input v-model="settings.homepage_content.full_banner1.url" type="text" placeholder="/categories/..." class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 font-mono text-slate-900" />
          </div>

          <!-- Full Banner 2 -->
          <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
            <div class="font-black text-slate-900 flex justify-between items-center">
              <span>Full-Width Banner 2</span>
              <label class="flex items-center gap-1.5 font-bold cursor-pointer">
                <input v-model="settings.homepage_content.full_banner2.enabled" type="checkbox" class="rounded text-red-600" />
                <span>Enabled</span>
              </label>
            </div>
            <div class="space-y-1">
              <label class="font-bold text-slate-700 block">Banner Image Upload</label>
              <div v-if="settings.homepage_content.full_banner2.image_url" class="h-24 w-full bg-slate-900 rounded-xl overflow-hidden">
                <img :src="settings.homepage_content.full_banner2.image_url" class="h-full w-full object-cover" />
              </div>
              <input type="file" accept="image/*" @change="uploadMediaFile($event, (url) => settings.homepage_content.full_banner2.image_url = url)" class="w-full text-[10px]" />
            </div>
            <input v-model="settings.homepage_content.full_banner2.title" type="text" placeholder="Title" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 font-bold text-slate-900" />
            <input v-model="settings.homepage_content.full_banner2.subtitle" type="text" placeholder="Subtitle" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-slate-700" />
            <input v-model="settings.homepage_content.full_banner2.url" type="text" placeholder="/categories/..." class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 font-mono text-slate-900" />
          </div>
        </div>
      </div>

      <!-- 7. REELS / VIDEO SLOTS MANAGER -->
      <div v-if="activeHpSubTab === 'reels'" class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-6">
        <div class="border-b border-slate-100 pb-3">
          <h3 class="font-black text-sm text-slate-900">Short Video Reels (4 Slots)</h3>
          <p class="text-xs text-slate-500 mt-0.5">Manage 4 short video reel slots with poster thumbnails and target links.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
          <div v-for="(r, idx) in settings.homepage_content.reels" :key="r.id || idx" class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
            <div class="font-black text-xs text-slate-900">Reel Slot {{ idx + 1 }}</div>
            <div class="space-y-1">
              <label class="font-bold text-slate-700 block">Thumbnail Upload</label>
              <div v-if="r.poster_url" class="h-28 w-full bg-slate-900 rounded-xl overflow-hidden">
                <img :src="r.poster_url" class="h-full w-full object-cover" />
              </div>
              <input type="file" accept="image/*" @change="uploadMediaFile($event, (url) => r.poster_url = url)" class="w-full text-[10px]" />
            </div>
            <input v-model="r.title" type="text" placeholder="Title" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 font-bold text-slate-900" />
            <input v-model="r.tag" type="text" placeholder="#Tag" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-slate-700 font-bold" />
            <input v-model="r.url" type="text" placeholder="/categories/..." class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 font-mono text-slate-900" />
          </div>
        </div>
      </div>

    </div>

    <!-- ========================================== -->
    <!-- TAB 2: DEDICATED HEADER & FOOTER PAGE      -->
    <!-- ========================================== -->
    <div v-if="activeTab === 'header_footer'" class="space-y-6">
      <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex items-center gap-3 overflow-x-auto">
        <button
          @click="activeHfSection = 'header'"
          :class="[
            'px-5 py-2.5 rounded-xl font-black text-xs transition-all flex items-center gap-2 whitespace-nowrap',
            activeHfSection === 'header' ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
          ]"
        >
          <span>🎨</span>
          <span>HEADER MANAGEMENT</span>
        </button>

        <button
          @click="activeHfSection = 'footer'"
          :class="[
            'px-5 py-2.5 rounded-xl font-black text-xs transition-all flex items-center gap-2 whitespace-nowrap',
            activeHfSection === 'footer' ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
          ]"
        >
          <span>🦶</span>
          <span>FOOTER MANAGEMENT</span>
        </button>

        <button
          @click="activeHfSection = 'favicon'"
          :class="[
            'px-5 py-2.5 rounded-xl font-black text-xs transition-all flex items-center gap-2 whitespace-nowrap',
            activeHfSection === 'favicon' ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
          ]"
        >
          <span>🔖</span>
          <span>FAVICON / SITE ICON</span>
        </button>
      </div>

      <!-- HEADER MANAGEMENT SECTION -->
      <div v-if="activeHfSection === 'header'" class="space-y-6">
        <div class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-6">
          <div class="border-b border-slate-100 pb-3">
            <h3 class="font-black text-sm text-slate-900 flex items-center gap-2">
              <span>🎨</span>
              <span>Header Branding & Layout Settings</span>
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">Upload logo, configure layout structure, sticky behavior and header action buttons.</p>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 text-xs">
            <div class="space-y-4">
              <div>
                <label class="font-bold text-slate-700 block">Header Logo (File Upload)</label>
                <div class="mt-2 flex items-center gap-4">
                  <div v-if="settings.header.logo_url" class="h-14 p-2 bg-slate-900 rounded-xl border border-slate-800 flex items-center justify-center">
                    <img :src="settings.header.logo_url" class="h-full object-contain" />
                  </div>
                  <input type="file" accept="image/*" @change="uploadLogoFile" class="text-[10px]" />
                </div>
              </div>

              <div class="space-y-1">
                <label class="font-bold text-slate-700 block">Header Layout</label>
                <select v-model="settings.header.layout" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900">
                  <option value="logo-left">Logo Left / Menu Center / Action Right (Default)</option>
                  <option value="center">Logo Centered</option>
                </select>
              </div>

              <div class="flex items-center gap-6 pt-1">
                <label class="flex items-center gap-2 font-bold text-slate-700 cursor-pointer">
                  <input v-model="settings.header.sticky" type="checkbox" class="rounded text-red-600" />
                  <span>Sticky Header on Scroll</span>
                </label>
                <label class="flex items-center gap-2 font-bold text-slate-700 cursor-pointer">
                  <input v-model="settings.header.show_search_icon" type="checkbox" class="rounded text-red-600" />
                  <span>Enable Search Icon</span>
                </label>
              </div>
            </div>

            <!-- Header Action Button (BUY ON WHATSAPP) -->
            <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
              <div class="font-black text-slate-900 flex justify-between items-center">
                <span>Header Action CTA Button</span>
                <label class="flex items-center gap-1.5 font-bold cursor-pointer">
                  <input v-model="settings.header.header_button.enabled" type="checkbox" class="rounded text-emerald-600" />
                  <span>Enabled</span>
                </label>
              </div>
              <div class="space-y-1">
                <label class="font-bold text-slate-700 block">Button Text</label>
                <input v-model="settings.header.header_button.text" type="text" placeholder="BUY ON WHATSAPP" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 font-bold text-slate-900" />
              </div>
              <div class="space-y-1">
                <label class="font-bold text-slate-700 block">Target Link / WhatsApp URL</label>
                <input v-model="settings.header.header_button.url" type="text" placeholder="https://wa.me/919735125112" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 font-mono text-slate-900" />
              </div>
            </div>
          </div>
        </div>

        <!-- Header Navigation Menu Builder -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-6">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
              <h3 class="font-black text-sm text-slate-900">Header Navigation Menu Builder</h3>
              <p class="text-xs text-slate-500 mt-0.5">Add, edit, enable/disable and reorder public header navigation items.</p>
            </div>
            <button @click="addMenuItem" class="px-3 py-1.5 bg-slate-900 text-white rounded-lg text-xs font-bold">
              ➕ Add Menu Item
            </button>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-wider">
                <tr>
                  <th class="px-4 py-3">Menu Label</th>
                  <th class="px-4 py-3">Target URL / Route</th>
                  <th class="px-4 py-3 text-center">Status</th>
                  <th class="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                <tr v-for="(item, idx) in settings.navigation" :key="item.id || idx" class="hover:bg-slate-50 transition-colors">
                  <td class="px-4 py-3">
                    <input v-model="item.label" type="text" class="bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-bold text-slate-900" />
                  </td>
                  <td class="px-4 py-3">
                    <input v-model="item.url" type="text" class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-mono text-slate-900" />
                  </td>
                  <td class="px-4 py-3 text-center">
                    <label class="inline-flex items-center gap-1.5 font-bold text-[11px] cursor-pointer">
                      <input v-model="item.enabled" type="checkbox" class="rounded text-red-600" />
                      <span>{{ item.enabled ? 'Enabled' : 'Disabled' }}</span>
                    </label>
                  </td>
                  <td class="px-4 py-3 text-right space-x-1">
                    <button @click="moveArrayItem(settings.navigation, idx, -1)" :disabled="idx === 0" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded font-bold disabled:opacity-30">↑</button>
                    <button @click="moveArrayItem(settings.navigation, idx, 1)" :disabled="idx === settings.navigation.length - 1" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded font-bold disabled:opacity-30">↓</button>
                    <button @click="deleteArrayItem(settings.navigation, idx)" class="px-2 py-1 bg-red-50 hover:bg-red-100 text-red-700 rounded font-bold">✕</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- FOOTER MANAGEMENT SECTION (Strict 4-Column Public-Only Schema) -->
      <div v-if="activeHfSection === 'footer'" class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-6">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="font-black text-sm text-slate-900 flex items-center gap-2">
              <span>🦶</span>
              <span>Footer Configuration (Strict 4-Column Layout)</span>
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">Manage column titles, links, social links, trust features and payment acceptance badges.</p>
          </div>
          <label class="flex items-center gap-2 font-bold text-xs text-slate-800 cursor-pointer bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-200">
            <input v-model="settings.footer.enabled" type="checkbox" class="rounded text-red-600" />
            <span>Enable Footer</span>
          </label>
        </div>

        <!-- 4 Columns Config Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 text-xs">
          <!-- Col 1: Shop -->
          <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
            <div class="font-black text-slate-900">Column 1 (Shop Links)</div>
            <input v-model="settings.footer.col1.title" type="text" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 font-bold text-slate-900" />
            <div class="space-y-1.5">
              <div v-for="(lnk, idx) in settings.footer.col1.links" :key="idx" class="flex gap-1">
                <input v-model="lnk.label" type="text" class="w-1/2 bg-white border border-slate-200 rounded-lg px-2 py-1 text-[11px] font-bold" />
                <input v-model="lnk.url" type="text" class="w-1/2 bg-white border border-slate-200 rounded-lg px-2 py-1 text-[11px] font-mono" />
              </div>
            </div>
          </div>

          <!-- Col 2: Brand & Social -->
          <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
            <div class="font-black text-slate-900">Column 2 (Brand & Social)</div>
            <input v-model="settings.footer.col2.title" type="text" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 font-bold text-slate-900" />
            <div class="space-y-1">
              <label class="font-bold text-slate-700 block">Social Links Header</label>
              <input v-model="settings.footer.col2.keep_in_touch_title" type="text" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 font-bold text-slate-900" />
            </div>
            <div class="space-y-1.5 pt-2 border-t border-slate-200">
              <div v-for="(soc, sIdx) in settings.footer.col2.social_links" :key="sIdx" class="space-y-1">
                <span class="font-bold text-[10px] uppercase text-slate-700">{{ soc.platform }} URL</span>
                <input v-model="soc.url" type="text" class="w-full bg-white border border-slate-200 rounded-lg px-2 py-1 text-[11px] font-mono" />
              </div>
            </div>
          </div>

          <!-- Col 3: Corporate -->
          <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
            <div class="font-black text-slate-900">Column 3 (Corporate)</div>
            <input v-model="settings.footer.col3.title" type="text" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 font-bold text-slate-900" />
            <div class="space-y-1.5">
              <div v-for="(lnk, idx) in settings.footer.col3.links" :key="idx" class="flex gap-1">
                <input v-model="lnk.label" type="text" class="w-1/2 bg-white border border-slate-200 rounded-lg px-2 py-1 text-[11px] font-bold" />
                <input v-model="lnk.url" type="text" class="w-1/2 bg-white border border-slate-200 rounded-lg px-2 py-1 text-[11px] font-mono" />
              </div>
            </div>
          </div>

          <!-- Col 4: Public Support -->
          <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
            <div class="font-black text-slate-900">Column 4 (Public Support)</div>
            <input v-model="settings.footer.col4.title" type="text" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 font-bold text-slate-900" />
            <div class="space-y-1.5">
              <div v-for="(lnk, idx) in settings.footer.col4.links" :key="idx" class="flex gap-1">
                <input v-model="lnk.label" type="text" class="w-1/2 bg-white border border-slate-200 rounded-lg px-2 py-1 text-[11px] font-bold" />
                <input v-model="lnk.url" type="text" class="w-1/2 bg-white border border-slate-200 rounded-lg px-2 py-1 text-[11px] font-mono" />
              </div>
            </div>
          </div>
        </div>

        <!-- Trust Features Edit -->
        <div class="space-y-3 pt-4 border-t border-slate-100 text-xs">
          <h4 class="font-black text-slate-900">Trust / Service Feature Strip (4 Features)</h4>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div v-for="(tf, idx) in settings.footer.trust_features" :key="idx" class="p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
              <div class="flex items-center gap-2">
                <input v-model="tf.icon" type="text" class="w-10 bg-white border border-slate-200 rounded-lg text-center font-bold text-sm py-1" />
                <input v-model="tf.title" type="text" class="flex-1 bg-white border border-slate-200 rounded-lg px-2 py-1 font-bold text-slate-900" />
              </div>
              <input v-model="tf.subtitle" type="text" class="w-full bg-white border border-slate-200 rounded-lg px-2 py-1 text-slate-600 text-[11px]" />
            </div>
          </div>
        </div>

        <!-- Copyright Text & Credit -->
        <div class="space-y-2 pt-4 border-t border-slate-100 text-xs">
          <label class="font-bold text-slate-700 block">Bottom Copyright Text Template</label>
          <input v-model="settings.footer.copyright_text" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-mono text-slate-900" />
          <span class="text-[10px] text-slate-400 font-medium">Developer credit "Developed By Tech Googly" is rendered dynamically.</span>
        </div>
      </div>

      <!-- FAVICON / SITE ICON MANAGEMENT SECTION -->
      <div v-if="activeHfSection === 'favicon'" class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-6">
        <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
          <div>
            <h3 class="font-black text-sm text-slate-900 flex items-center gap-2">
              <span>🔖</span>
              <span>Favicon / Site Icon Settings</span>
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">Upload, preview, replace or remove website browser tab favicon icon.</p>
          </div>
          <label class="flex items-center gap-2 font-bold text-xs text-slate-800 cursor-pointer bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-200">
            <input v-model="settings.general.favicon_enabled" type="checkbox" class="rounded text-red-600" />
            <span>Enable Favicon</span>
          </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 text-xs">
          <!-- Preview Box -->
          <div class="p-6 bg-slate-50 border border-slate-200 rounded-3xl space-y-4 text-center">
            <div class="font-black text-xs text-slate-900 uppercase tracking-wider">Current Favicon Preview</div>
            <div class="h-24 w-24 mx-auto bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex items-center justify-center p-3">
              <img v-if="settings.general.favicon_url" :src="settings.general.favicon_url" class="max-h-full max-w-full object-contain" />
              <span v-else class="text-3xl text-slate-300">👟</span>
            </div>
            
            <div class="space-y-1">
              <div class="font-bold text-slate-800 text-xs">Browser Tab Mockup</div>
              <div class="inline-flex items-center gap-2 bg-slate-200 px-4 py-1.5 rounded-t-xl border border-slate-300 max-w-xs text-[11px] font-bold text-slate-700">
                <img v-if="settings.general.favicon_url" :src="settings.general.favicon_url" class="h-4 w-4 object-contain" />
                <span v-else>👟</span>
                <span class="truncate">RUPSA PADUKALAYA — Quality Footwear</span>
              </div>
            </div>

            <div v-if="settings.general.favicon_url" class="pt-2">
              <button
                @click="settings.general.favicon_url = ''"
                class="px-4 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 rounded-xl text-xs font-bold transition-colors"
              >
                🗑️ Remove Favicon
              </button>
            </div>
          </div>

          <!-- Upload Controls -->
          <div class="space-y-5">
            <div class="space-y-2">
              <label class="font-black text-slate-900 text-xs block">Upload Favicon File</label>
              <div class="p-4 bg-slate-50 border border-dashed border-slate-300 rounded-2xl space-y-3">
                <input
                  type="file"
                  accept="image/png,image/x-icon,image/svg+xml,image/jpeg,image/webp"
                  @change="uploadFaviconFile"
                  class="w-full text-xs"
                />
                <p class="text-[11px] text-slate-500 font-medium">
                  Supported formats: <strong>PNG, ICO, SVG, WEBP, JPG</strong>.<br />
                  Recommended resolution: <strong>32×32, 180×180, 512×512 PNG</strong>.
                </p>
              </div>
            </div>

            <div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl text-[11px] text-amber-900 space-y-1 font-medium">
              <div class="font-black text-xs flex items-center gap-1">
                <span>💡</span>
                <span>Automatic Dynamic Favicon Sync</span>
              </div>
              <p>
                When you upload a new favicon and click <strong>Publish Changes</strong>, the new icon is automatically applied across the public website head with cache invalidation (`?v=timestamp`).
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- TAB 3: BUSINESS CONTACT INFORMATION        -->
    <!-- ========================================== -->
    <div v-if="activeTab === 'business'" class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-6">
      <div class="border-b border-slate-100 pb-3">
        <h3 class="font-black text-sm text-slate-900 flex items-center gap-2">
          <span>🏢</span>
          <span>Official Business Contact & Location Information</span>
        </h3>
        <p class="text-xs text-slate-500 mt-0.5">Manage centralized store address, phone numbers, WhatsApp line & Google Maps URL.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
        <div class="space-y-4">
          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">Official Business Name</label>
            <input v-model="settings.contact_info.business_name" type="text" placeholder="RUPSA PADUKALAYA" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 font-bold text-slate-900" />
          </div>

          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">Address Line</label>
            <input v-model="settings.contact_info.address_line" type="text" placeholder="DHANTALA BAZAR, DHANTALA" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 font-bold text-slate-900" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="font-bold text-slate-700 block">District</label>
              <input v-model="settings.contact_info.district" type="text" placeholder="NADIA" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 font-bold text-slate-900" />
            </div>
            <div class="space-y-1">
              <label class="font-bold text-slate-700 block">PIN Code</label>
              <input v-model="settings.contact_info.pin_code" type="text" placeholder="741202" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 font-mono font-bold text-slate-900" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="font-bold text-slate-700 block">State</label>
              <input v-model="settings.contact_info.state" type="text" placeholder="WEST BENGAL" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 font-bold text-slate-900" />
            </div>
            <div class="space-y-1">
              <label class="font-bold text-slate-700 block">Country</label>
              <input v-model="settings.contact_info.country" type="text" placeholder="INDIA" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 font-bold text-slate-900" />
            </div>
          </div>
        </div>

        <div class="space-y-4">
          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">Customer Helpline Phone Number</label>
            <input v-model="settings.contact_info.phone" type="text" placeholder="+91 9735125112" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 font-mono font-bold text-slate-900" />
          </div>

          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">Official WhatsApp Number</label>
            <input v-model="settings.contact_info.whatsapp_phone" type="text" placeholder="+91 9735125112" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 font-mono font-bold text-slate-900" />
          </div>

          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">Google Maps / Directions URL</label>
            <input v-model="settings.contact_info.maps_url" type="text" placeholder="https://maps.google.com/..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 font-mono text-slate-900" />
          </div>

          <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-1 font-mono text-[11px] text-slate-600">
            <div class="font-black text-slate-900 text-xs uppercase mb-1">Live Address Preview</div>
            <div>{{ settings.contact_info.business_name || 'RUPSA PADUKALAYA' }}</div>
            <div>{{ settings.contact_info.address_line }}, {{ settings.contact_info.district }}, {{ settings.contact_info.pin_code }}</div>
            <div>{{ settings.contact_info.state }}, {{ settings.contact_info.country }}</div>
            <div class="font-bold text-red-600">Phone: {{ settings.contact_info.phone }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- PRODUCT SELECTION MODAL -->
    <div v-if="showProductPicker" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl max-w-2xl w-full p-6 space-y-4 shadow-2xl border border-slate-200 max-h-[85vh] flex flex-col font-sans">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h3 class="font-black text-base text-slate-900">Select Products from Master Data</h3>
          <button @click="showProductPicker = false" class="text-slate-400 hover:text-slate-700 font-bold">✕</button>
        </div>

        <input
          v-model="productSearch"
          type="text"
          placeholder="Search by Product Name or Article Number..."
          class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-xs font-bold text-slate-900"
        />

        <div class="flex-1 overflow-y-auto space-y-2 pr-1">
          <div
            v-for="p in filteredMasterProducts"
            :key="p.id"
            @click="toggleProductForCurrentSection(p.id)"
            :class="[
              'p-3 rounded-xl border flex items-center justify-between cursor-pointer transition-colors text-xs',
              isProductInCurrentSection(p.id) ? 'bg-red-50 border-red-600 font-bold' : 'bg-slate-50 border-slate-200 hover:bg-slate-100'
            ]"
          >
            <div class="flex items-center gap-3">
              <div class="h-10 w-10 rounded bg-white border border-slate-200 overflow-hidden shrink-0">
                <img :src="p.primary_image_url || p.image_url" class="h-full w-full object-cover" />
              </div>
              <div>
                <span class="text-slate-900 block font-bold">{{ p.name }}</span>
                <span class="text-[10px] text-slate-400 font-mono">ART: {{ p.article_number || p.code }} | ₹{{ p.selling_price }}</span>
              </div>
            </div>
            <span :class="['px-2.5 py-1 rounded-md text-[10px] font-black', isProductInCurrentSection(p.id) ? 'bg-red-600 text-white' : 'bg-slate-200 text-slate-700']">
              {{ isProductInCurrentSection(p.id) ? 'Added' : '+ Add' }}
            </span>
          </div>
        </div>

        <div class="pt-3 border-t border-slate-100 flex justify-end">
          <button @click="showProductPicker = false" class="px-5 py-2 bg-slate-900 text-white font-bold rounded-xl text-xs">
            Done Selecting
          </button>
        </div>
      </div>
    </div>

    <!-- PREVIEW HOME PAGE MODAL -->
    <div v-if="showPreviewModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs flex flex-col items-center justify-center p-4">
      <div class="bg-white rounded-3xl w-full max-w-5xl h-[90vh] flex flex-col overflow-hidden shadow-2xl border border-slate-300">
        <div class="bg-slate-900 text-white px-6 py-4 flex items-center justify-between shrink-0">
          <div class="flex items-center gap-3">
            <span class="font-black text-sm">👁️ Public Website Live Preview</span>
            <div class="flex items-center gap-1.5 bg-slate-800 p-1 rounded-xl text-xs font-bold">
              <button @click="previewViewport = 'desktop'" :class="['px-3 py-1 rounded-lg', previewViewport === 'desktop' ? 'bg-red-600 text-white' : 'text-slate-400']">Desktop (1440px)</button>
              <button @click="previewViewport = 'tablet'" :class="['px-3 py-1 rounded-lg', previewViewport === 'tablet' ? 'bg-red-600 text-white' : 'text-slate-400']">Tablet (768px)</button>
              <button @click="previewViewport = 'mobile'" :class="['px-3 py-1 rounded-lg', previewViewport === 'mobile' ? 'bg-red-600 text-white' : 'text-slate-400']">Mobile (375px)</button>
            </div>
          </div>
          <button @click="showPreviewModal = false" class="text-slate-400 hover:text-white font-bold text-lg">✕</button>
        </div>

        <div class="flex-1 bg-slate-200 overflow-y-auto flex items-center justify-center p-4">
          <div :class="['bg-white shadow-2xl transition-all h-full rounded-2xl overflow-hidden border border-slate-300', previewViewport === 'mobile' ? 'w-[375px]' : previewViewport === 'tablet' ? 'w-[768px]' : 'w-full']">
            <iframe src="/" class="w-full h-full border-none"></iframe>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useAuth } from '../../composables/useAuth';
import api from '../../services/api';

const props = defineProps({
  initialTab: { type: String, default: 'homepage' },
  initialSubTab: { type: String, default: 'hero' }
});

const { hasPermission } = useAuth();

const activeTab = ref(props.initialTab || 'homepage');
const activeHpSubTab = ref(props.initialSubTab || 'hero');
const activeHfSection = ref('header');
const saving = ref(false);
const saveSuccess = ref(false);

const masterCategories = ref([]);
const masterBrands = ref([]);
const masterProducts = ref([]);

const showProductPicker = ref(false);
const pickerSectionIndex = ref(0);
const productSearch = ref('');

const showPreviewModal = ref(false);
const previewViewport = ref('desktop');

const tabs = [
  { id: 'homepage', label: 'Home Page Content', icon: '🏠' },
  { id: 'header_footer', label: 'Header & Footer', icon: '🎨' },
  { id: 'business', label: 'Contact & Business Info', icon: '🏢' },
];

const hpSubTabs = [
  { id: 'hero', label: 'Hero Banners' },
  { id: 'small_banners', label: '4 Small Banners' },
  { id: 'categories', label: 'Shop By Category' },
  { id: 'brands', label: 'Our Partners' },
  { id: 'product_sections', label: 'Product Sections' },
  { id: 'full_banners', label: 'Full-Width Banners' },
  { id: 'reels', label: 'Reels / Short Videos' },
];

const settings = reactive({
  general: { site_title: 'RUPSA PADUKALAYA', tagline: 'STEP INTO COMFORT', favicon_url: '', favicon_enabled: true },
  header: {
    logo_url: '',
    layout: 'logo-left',
    sticky: true,
    show_search_icon: true,
    header_button: { enabled: true, text: 'BUY ON WHATSAPP', url: 'https://wa.me/919735125112' }
  },
  navigation: [],
  contact_info: {
    business_name: 'RUPSA PADUKALAYA',
    address_line: 'DHANTALA BAZAR, DHANTALA',
    district: 'NADIA',
    pin_code: '741202',
    state: 'WEST BENGAL',
    country: 'INDIA',
    phone: '+91 9735125112',
    whatsapp_phone: '+91 9735125112',
    email: 'support@rupsapadukalaya.com',
    maps_url: 'https://maps.google.com/?q=RUPSA+PADUKALAYA+DHANTALA+BAZAR+DHANTALA+NADIA+741202+WEST+BENGAL',
  },
  homepage_content: {
    hero_slides: [
      { id: 1, tag: 'FESTIVE COLLECTION 2026', title: 'Executive Genuine Leather & Ergonomic Arch Comfort', subtitle: 'Hand-stitched oxfords & brogues.', cta: 'Shop Men Collection', link: '/categories/men', image: 'https://images.unsplash.com/photo-1549298916-b41d501d3772?auto=format&fit=crop&w=1600&q=80', enabled: true },
    ],
    small_banners: [],
    category_ids: [],
    brand_ids: [],
    product_sections: [
      { id: 1, title: 'New Footwear Arrivals', enabled: true, product_ids: [1, 2, 3, 4, 5] },
      { id: 2, title: 'Trending Footwear', enabled: true, product_ids: [6, 7, 8, 9, 10] },
    ],
    full_banner1: { image_url: '', title: '', subtitle: '', url: '', enabled: true },
    full_banner2: { image_url: '', title: '', subtitle: '', url: '', enabled: true },
    reels: [],
    whatsapp_settings: { enabled: true, phone_number: '919735125112' },
    section_visibility: { our_partners: true, small_banners: true, category_carousel: true, hero: true },
  },
  footer: {
    enabled: true,
    col1: { title: 'SHOP AT RUPSA PADUKALAYA', links: [] },
    col2: { title: "IT'S WOW! IT'S RUPSA", keep_in_touch_title: 'KEEP IN TOUCH', social_links: [] },
    col3: { title: 'CORPORATE', links: [] },
    col4: { title: 'SUPPORT', links: [] },
    trust_features: [],
    payment_methods: [],
    copyright_text: '© {CURRENT_YEAR}, Rupsa Padukalaya. All rights reserved.'
  },
});

watch(() => props.initialTab, (newTab) => {
  if (newTab) activeTab.value = newTab;
}, { immediate: true });

watch(() => props.initialSubTab, (newSubTab) => {
  if (newSubTab) activeHpSubTab.value = newSubTab;
}, { immediate: true });

const filteredMasterProducts = computed(() => {
  if (!productSearch.value) return masterProducts.value;
  const q = productSearch.value.toLowerCase();
  return masterProducts.value.filter(p => (p.name && p.name.toLowerCase().includes(q)) || (p.article_number && p.article_number.toLowerCase().includes(q)));
});

function addHeroSlide() {
  if (!settings.homepage_content.hero_slides) settings.homepage_content.hero_slides = [];
  settings.homepage_content.hero_slides.push({
    id: Date.now(),
    tag: 'NEW ARRIVAL',
    title: 'New Footwear Collection 2026',
    subtitle: 'Crafted for Indian ergonomics.',
    cta: 'Explore Collection',
    link: '/categories/men',
    image: '',
    enabled: true,
  });
}

function addMenuItem() {
  if (!settings.navigation) settings.navigation = [];
  settings.navigation.push({
    id: Date.now(),
    label: 'NEW ITEM',
    url: '/products',
    enabled: true,
    target: '_self',
  });
}

function addProductSection() {
  if (!settings.homepage_content.product_sections) settings.homepage_content.product_sections = [];
  settings.homepage_content.product_sections.push({
    id: Date.now(),
    title: 'Featured Collection',
    enabled: true,
    product_ids: [],
  });
}

function openProductPicker(sectionIdx) {
  pickerSectionIndex.value = sectionIdx;
  showProductPicker.value = true;
}

function toggleProductForCurrentSection(productId) {
  const sec = settings.homepage_content.product_sections[pickerSectionIndex.value];
  if (!sec.product_ids) sec.product_ids = [];
  const pos = sec.product_ids.indexOf(productId);
  if (pos >= 0) sec.product_ids.splice(pos, 1);
  else sec.product_ids.push(productId);
}

function isProductInCurrentSection(productId) {
  const sec = settings.homepage_content.product_sections[pickerSectionIndex.value];
  return sec?.product_ids?.includes(productId);
}

function removeProductFromSection(sectionIdx, productIdx) {
  settings.homepage_content.product_sections[sectionIdx].product_ids.splice(productIdx, 1);
}

function getProductById(pId) {
  return masterProducts.value.find(p => p.id === pId);
}

function toggleCategorySelection(catId) {
  if (!settings.homepage_content.category_ids) settings.homepage_content.category_ids = [];
  const idx = settings.homepage_content.category_ids.indexOf(catId);
  if (idx >= 0) settings.homepage_content.category_ids.splice(idx, 1);
  else settings.homepage_content.category_ids.push(catId);
}

function isCategorySelected(catId) {
  return settings.homepage_content.category_ids?.includes(catId);
}

function toggleBrandSelection(brandId) {
  if (!settings.homepage_content.brand_ids) settings.homepage_content.brand_ids = [];
  const idx = settings.homepage_content.brand_ids.indexOf(brandId);
  if (idx >= 0) settings.homepage_content.brand_ids.splice(idx, 1);
  else settings.homepage_content.brand_ids.push(brandId);
}

function isBrandSelected(brandId) {
  return settings.homepage_content.brand_ids?.includes(brandId);
}

function moveArrayItem(arr, idx, delta) {
  if (!arr) return;
  const target = idx + delta;
  if (target >= 0 && target < arr.length) {
    const temp = arr[idx];
    arr[idx] = arr[target];
    arr[target] = temp;
  }
}

function deleteArrayItem(arr, idx) {
  if (arr) arr.splice(idx, 1);
}

async function uploadLogoFile(e) {
  const files = e.target.files;
  if (!files || !files[0]) return;
  try {
    const formData = new FormData();
    formData.append('logo', files[0]);
    const res = await api.post('/website-settings/logo', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    settings.header.logo_url = res.logo_url;
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to upload logo.');
  }
}

async function uploadFaviconFile(e) {
  const files = e.target.files;
  if (!files || !files[0]) return;
  try {
    const formData = new FormData();
    formData.append('favicon', files[0]);
    const res = await api.post('/website-settings/favicon', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    settings.general.favicon_url = res.favicon_url;
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to upload favicon.');
  }
}

async function uploadMediaFile(e, callback) {
  const files = e.target.files;
  if (!files || !files[0]) return;
  try {
    const formData = new FormData();
    formData.append('media', files[0]);
    const res = await api.post('/website-settings/media', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    if (res.media_url && typeof callback === 'function') callback(res.media_url);
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to upload media.');
  }
}

async function fetchMasterData() {
  try {
    const [cRes, bRes, pRes] = await Promise.all([
      api.get('/public/categories'),
      api.get('/public/brands'),
      api.get('/public/products'),
    ]);
    masterCategories.value = Array.isArray(cRes.data || cRes) ? (cRes.data || cRes) : [];
    masterBrands.value = Array.isArray(bRes.data || bRes) ? (bRes.data || bRes) : [];
    masterProducts.value = Array.isArray(pRes.data || pRes) ? (pRes.data || pRes) : [];
  } catch (err) {
    console.error('Failed to load master data:', err);
  }
}

async function fetchSettings() {
  try {
    const res = await api.get('/website-settings');
    const data = res.data || res;
    if (data && typeof data === 'object') {
      if (data.general) Object.assign(settings.general, data.general);
      if (data.header) {
        Object.assign(settings.header, data.header);
        if (data.header.header_button) settings.header.header_button = { ...settings.header.header_button, ...data.header.header_button };
      }
      if (data.navigation) settings.navigation = data.navigation;
      if (data.contact_info) Object.assign(settings.contact_info, data.contact_info);
      if (data.homepage_content) settings.homepage_content = { ...settings.homepage_content, ...data.homepage_content };
      if (data.footer) settings.footer = { ...settings.footer, ...data.footer };
    }
  } catch (err) {
    console.error('Failed to fetch settings:', err);
  }
}

async function publishSettings() {
  saving.value = true;
  saveSuccess.value = false;
  try {
    await api.put('/website-settings', { settings });
    saveSuccess.value = true;
    setTimeout(() => { saveSuccess.value = false; }, 4000);
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to publish settings.');
  } finally {
    saving.value = false;
  }
}

onMounted(() => {
  fetchSettings();
  fetchMasterData();
});
</script>
