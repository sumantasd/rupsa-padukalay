<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <!-- Header -->
    <div class="border-b border-slate-200 pb-4">
      <h1 class="text-2xl font-black text-slate-900">RUPSA PADUKALAYA Store Locator</h1>
      <p class="text-xs text-slate-500 mt-1">Locate our physical footwear retail outlets & customer service centers</p>
    </div>

    <!-- Search Input -->
    <div class="max-w-md">
      <input
        type="text"
        v-model="search"
        placeholder="Search by store name, city or pincode..."
        class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-red-600"
      />
    </div>

    <!-- Stores List Grid -->
    <div v-if="loading" class="p-8 text-center text-xs text-slate-400 bg-white rounded-2xl border border-slate-200">
      Loading store outlets...
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div
        v-for="st in filteredStores"
        :key="st.id || st.name"
        class="p-6 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-3 hover:border-red-600 transition-all flex flex-col justify-between"
      >
        <div class="space-y-2">
          <span class="text-[10px] font-mono font-bold text-red-700 bg-red-50 border border-red-200 px-2 py-0.5 rounded">
            {{ st.code || 'STORE' }}
          </span>
          <h3 class="font-extrabold text-base text-slate-900">{{ st.name }}</h3>
          <p class="text-xs text-slate-600 leading-relaxed">
            📍 {{ st.address || 'Central Market Area' }}, {{ st.city || 'Kolkata' }} {{ st.pincode }}
          </p>
          <p class="text-xs text-slate-500 font-mono">📞 Phone: {{ st.phone || '+91 9876543210' }}</p>
          <p class="text-xs text-slate-500 font-mono">✉️ Email: {{ st.email || 'store@rupsapadukalaya.com' }}</p>
        </div>

        <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
          <span class="font-bold text-emerald-600">Open 10:00 AM - 08:30 PM</span>
          <span class="text-red-700 font-bold hover:underline cursor-pointer">Get Directions ↗</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../services/api';

const stores = ref([]);
const loading = ref(false);
const search = ref('');

const filteredStores = computed(() => {
  if (!search.value) return stores.value;
  const q = search.value.toLowerCase();
  return stores.value.filter(s =>
    s.name?.toLowerCase().includes(q) ||
    s.city?.toLowerCase().includes(q) ||
    s.code?.toLowerCase().includes(q)
  );
});

async function loadStores() {
  loading.value = true;
  try {
    const res = await api.get('/stores');
    if (res.success && res.data && res.data.length > 0) {
      stores.value = res.data;
    } else {
      stores.value = [
        { code: 'ST-001', name: 'Main College Street Branch', address: '123 Footwear Market Road', city: 'Kolkata', pincode: '700001', phone: '+91 98765 43210', email: 'collegestreet@rupsapadukalaya.com' },
        { code: 'ST-002', name: 'Station Square Outlet', address: '45 Railway Station Road', city: 'Kolkata', pincode: '700005', phone: '+91 98765 43211', email: 'stationsquare@rupsapadukalaya.com' },
        { code: 'ST-003', name: 'Metro Plaza Store', address: '88 Commercial Hub', city: 'Kolkata', pincode: '700016', phone: '+91 98765 43212', email: 'metroplaza@rupsapadukalaya.com' },
      ];
    }
  } catch (e) {
    stores.value = [
      { code: 'ST-001', name: 'Main College Street Branch', address: '123 Footwear Market Road', city: 'Kolkata', pincode: '700001', phone: '+91 98765 43210', email: 'collegestreet@rupsapadukalaya.com' },
      { code: 'ST-002', name: 'Station Square Outlet', address: '45 Railway Station Road', city: 'Kolkata', pincode: '700005', phone: '+91 98765 43211', email: 'stationsquare@rupsapadukalaya.com' },
      { code: 'ST-003', name: 'Metro Plaza Store', address: '88 Commercial Hub', city: 'Kolkata', pincode: '700016', phone: '+91 98765 43212', email: 'metroplaza@rupsapadukalaya.com' },
    ];
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  loadStores();
});
</script>
