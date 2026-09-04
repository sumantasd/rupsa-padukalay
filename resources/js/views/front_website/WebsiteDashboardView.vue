<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-16 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Front Website Management</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Overview of public RUPSA PADUKALAYA website content, banners, pages and quick actions.
        </p>
      </div>
      <div class="flex items-center gap-2">
        <a
          href="/"
          target="_blank"
          class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-black rounded-xl text-xs flex items-center gap-1.5 shadow-md"
        >
          <span>🌐</span>
          <span>Open Live Website</span>
          <span>↗</span>
        </a>
      </div>
    </div>

    <!-- Status Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-2xs space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Website Status</span>
          <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
        </div>
        <div class="text-xl font-black text-emerald-600">Online & Live</div>
        <p class="text-[10px] text-slate-500 font-medium">Public customer routes responding</p>
      </div>

      <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-2xs space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Hero Banners</span>
          <span class="text-lg">🖼️</span>
        </div>
        <div class="text-xl font-black text-slate-900">4 Active Slides</div>
        <p class="text-[10px] text-slate-500 font-medium">Auto-rotating promotional slider</p>
      </div>

      <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-2xs space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Published Pages</span>
          <span class="text-lg">📄</span>
        </div>
        <div class="text-xl font-black text-slate-900">7 Active Pages</div>
        <p class="text-[10px] text-slate-500 font-medium">Home, About, Men, Women, Kids, Others, Contact</p>
      </div>

      <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-2xs space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Navigation Menu</span>
          <span class="text-lg">🧭</span>
        </div>
        <div class="text-xl font-black text-slate-900">7 Active Items</div>
        <p class="text-[10px] text-slate-500 font-medium">Customizable public header nav</p>
      </div>
    </div>

    <!-- Quick Actions Grid -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-4">
      <h3 class="font-black text-sm text-slate-900">Quick Website Actions</h3>
      
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <router-link
          to="/admin/front-website/home"
          class="p-4 rounded-xl bg-slate-50 border border-slate-200 hover:border-red-600 hover:bg-red-50/50 transition-all text-center space-y-2 group"
        >
          <div class="text-3xl group-hover:scale-110 transition-transform">🏠</div>
          <div class="font-black text-xs text-slate-900 group-hover:text-red-700">Edit Home Page</div>
          <div class="text-[10px] text-slate-500">Banners, category grids & promo sections</div>
        </router-link>

        <router-link
          to="/admin/front-website/banners"
          class="p-4 rounded-xl bg-slate-50 border border-slate-200 hover:border-red-600 hover:bg-red-50/50 transition-all text-center space-y-2 group"
        >
          <div class="text-3xl group-hover:scale-110 transition-transform">🖼️</div>
          <div class="font-black text-xs text-slate-900 group-hover:text-red-700">Manage Banners</div>
          <div class="text-[10px] text-slate-500">Hero slides & campaign banners</div>
        </router-link>

        <router-link
          to="/admin/front-website/header"
          class="p-4 rounded-xl bg-slate-50 border border-slate-200 hover:border-red-600 hover:bg-red-50/50 transition-all text-center space-y-2 group"
        >
          <div class="text-3xl group-hover:scale-110 transition-transform">🧭</div>
          <div class="font-black text-xs text-slate-900 group-hover:text-red-700">Header & Nav</div>
          <div class="text-[10px] text-slate-500">Logo, menu links & top bar</div>
        </router-link>

        <router-link
          to="/admin/front-website/footer"
          class="p-4 rounded-xl bg-slate-50 border border-slate-200 hover:border-red-600 hover:bg-red-50/50 transition-all text-center space-y-2 group"
        >
          <div class="text-3xl group-hover:scale-110 transition-transform">🦶</div>
          <div class="font-black text-xs text-slate-900 group-hover:text-red-700">Manage Footer</div>
          <div class="text-[10px] text-slate-500">Footer columns, links & copyright</div>
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
</script>
