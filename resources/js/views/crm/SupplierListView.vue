<template>
  <div class="space-y-6 antialiased font-sans max-w-7xl mx-auto pb-16">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Suppliers & Manufacturers</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Footwear wholesale vendors, GST numbers, lead times, procurement payables & ledger positions.
        </p>
      </div>
      <router-link
        v-if="hasPermission('suppliers.create')"
        to="/admin/suppliers/create"
        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white font-black text-xs rounded-xl shadow-md shadow-red-600/20 transition-all uppercase tracking-wider shrink-0"
      >
        <span>➕</span>
        <span>Add Supplier</span>
      </router-link>
    </div>

    <!-- Filters & Search Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex flex-col md:flex-row items-center justify-between gap-4">
      <div class="relative w-full md:w-96">
        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
        <input
          type="text"
          v-model="searchQuery"
          @input="debouncedSearch"
          placeholder="Search supplier code, name, company, GSTIN, phone..."
          class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-4 py-2.5 text-xs text-slate-900 font-medium focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
        />
      </div>

      <div class="flex items-center gap-3 w-full md:w-auto overflow-x-auto">
        <!-- Status Filter -->
        <select
          v-model="statusFilter"
          @change="fetchSuppliers(1)"
          class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-red-600"
        >
          <option value="">All Statuses</option>
          <option value="active">Active Vendors</option>
          <option value="inactive">Deactivated</option>
        </select>

        <!-- City Filter -->
        <select
          v-model="cityFilter"
          @change="fetchSuppliers(1)"
          class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-red-600"
        >
          <option value="">All Cities</option>
          <option v-for="c in availableCities" :key="c" :value="c">{{ c }}</option>
        </select>

        <button
          @click="fetchSuppliers(1)"
          class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition-colors shrink-0"
        >
          🔄 Refresh
        </button>
      </div>
    </div>

    <!-- Skeleton Loading State -->
    <div v-if="loading" class="bg-white rounded-2xl border border-slate-200/90 p-6 space-y-4 shadow-xs">
      <div v-for="i in 5" :key="i" class="animate-pulse flex items-center justify-between gap-4">
        <div class="h-4 bg-slate-200 rounded w-1/4"></div>
        <div class="h-4 bg-slate-200 rounded w-1/6"></div>
        <div class="h-4 bg-slate-200 rounded w-1/6"></div>
        <div class="h-4 bg-slate-200 rounded w-1/8"></div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="p-6 bg-red-50 border border-red-200 rounded-2xl text-center space-y-3">
      <span class="text-2xl">⚠️</span>
      <h3 class="font-black text-sm text-red-900">Unable to load supplier directory</h3>
      <p class="text-xs text-red-700 font-medium max-w-md mx-auto">{{ error }}</p>
      <button @click="fetchSuppliers(1)" class="px-4 py-2 bg-red-600 text-white font-bold rounded-xl text-xs shadow-xs">
        Try Again
      </button>
    </div>

    <!-- Empty State -->
    <div v-else-if="suppliers.length === 0" class="p-12 bg-white rounded-2xl border border-slate-200/90 text-center space-y-4 shadow-xs">
      <div class="h-16 w-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto text-3xl">
        🏢
      </div>
      <div class="space-y-1">
        <h3 class="font-black text-base text-slate-900">No Suppliers Found</h3>
        <p class="text-xs text-slate-500 max-w-sm mx-auto">
          No registered footwear supplier or vendor records match your query.
        </p>
      </div>
      <router-link
        v-if="hasPermission('suppliers.create')"
        to="/admin/suppliers/create"
        class="inline-block px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl shadow-xs"
      >
        ➕ Register First Supplier
      </router-link>
    </div>

    <!-- DataTable View (Desktop & Mobile Scrollable Container) -->
    <div v-else class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
              <th class="py-3.5 px-4">Supplier Code</th>
              <th class="py-3.5 px-4">Company & Vendor</th>
              <th class="py-3.5 px-4">Phone / GSTIN</th>
              <th class="py-3.5 px-4">City / State</th>
              <th class="py-3.5 px-4 text-right">Total Purchase</th>
              <th class="py-3.5 px-4 text-right">Total Paid</th>
              <th class="py-3.5 px-4 text-right">Current Due</th>
              <th class="py-3.5 px-4 text-center">Status</th>
              <th class="py-3.5 px-4 text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
            <tr v-for="s in suppliers" :key="s.id" class="hover:bg-slate-50/80 transition-colors">
              <!-- Supplier Code -->
              <td class="py-3.5 px-4 font-mono font-bold text-red-600">
                {{ s.code || ('SUP-' + String(s.id).padStart(4, '0')) }}
              </td>

              <!-- Company & Vendor Name -->
              <td class="py-3.5 px-4">
                <router-link :to="'/admin/suppliers/' + s.id" class="font-black text-slate-900 hover:text-red-600 transition-colors block">
                  {{ s.company_name || s.name }}
                </router-link>
                <span v-if="s.company_name && s.name" class="text-[10px] text-slate-400 font-medium">{{ s.name }}</span>
              </td>

              <!-- Phone & GSTIN -->
              <td class="py-3.5 px-4 space-y-0.5">
                <a :href="'tel:' + (s.phone || '').replace(/\s+/g, '')" class="font-mono font-bold text-slate-800 hover:text-red-600 block">
                  {{ s.phone || 'N/A' }}
                </a>
                <span class="text-[10px] font-mono text-slate-400 block">{{ s.gstin || 'Unregistered' }}</span>
              </td>

              <!-- City & State -->
              <td class="py-3.5 px-4 text-slate-600">
                <div>{{ s.city || 'Kolkata' }}</div>
                <div class="text-[10px] text-slate-400">{{ s.state || 'West Bengal' }}</div>
              </td>

              <!-- Total Purchase -->
              <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-800">
                ₹{{ formatCurrency(s.total_purchases_amount) }}
              </td>

              <!-- Total Paid -->
              <td class="py-3.5 px-4 text-right font-mono font-bold text-emerald-700">
                ₹{{ formatCurrency(s.total_paid_amount) }}
              </td>

              <!-- Current Due -->
              <td class="py-3.5 px-4 text-right font-mono font-black text-red-600">
                ₹{{ formatCurrency(s.current_due_amount || s.current_balance) }}
              </td>

              <!-- Status Badge -->
              <td class="py-3.5 px-4 text-center">
                <span
                  :class="[
                    'px-2 py-0.5 rounded text-[9px] font-black uppercase border',
                    s.is_active ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'
                  ]"
                >
                  {{ s.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>

              <!-- Actions -->
              <td class="py-3.5 px-4 text-center">
                <div class="flex items-center justify-center gap-1.5 whitespace-nowrap">
                  <router-link
                    :to="'/admin/suppliers/' + s.id"
                    class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-bold text-[11px] transition-colors"
                  >
                    👁️ View
                  </router-link>

                  <router-link
                    v-if="hasPermission('suppliers.edit')"
                    :to="'/admin/suppliers/' + s.id + '/edit'"
                    class="px-2.5 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 rounded-lg font-bold text-[11px] border border-amber-200 transition-colors"
                  >
                    ✏️ Edit
                  </router-link>

                  <button
                    v-if="hasPermission('suppliers.delete')"
                    @click="deleteSupplier(s)"
                    class="px-2 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 rounded-lg font-bold text-[11px] border border-red-200 transition-colors"
                    title="Delete Supplier"
                  >
                    🗑️
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="p-4 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs font-bold text-slate-600">
        <div class="flex items-center gap-3">
          <span>Rows per page:</span>
          <select
            v-model="pagination.per_page"
            @change="fetchSuppliers(1)"
            class="bg-white border border-slate-200 rounded-lg px-2 py-1 text-xs text-slate-800 font-bold focus:ring-2 focus:ring-red-600"
          >
            <option :value="10">10 per page</option>
            <option :value="15">15 per page</option>
            <option :value="25">25 per page</option>
            <option :value="50">50 per page</option>
          </select>
          <span>Showing Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} Total Vendors)</span>
        </div>

        <div class="flex items-center gap-2">
          <button
            @click="fetchSuppliers(pagination.current_page - 1)"
            :disabled="pagination.current_page === 1 || loading"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg disabled:opacity-30 hover:bg-slate-100 transition-colors"
          >
            ← Previous
          </button>
          <span class="px-3 py-1.5 font-mono font-bold bg-slate-100 rounded-lg border border-slate-200 text-slate-900">
            {{ pagination.current_page }} / {{ pagination.last_page }}
          </span>
          <button
            @click="fetchSuppliers(pagination.current_page + 1)"
            :disabled="pagination.current_page === pagination.last_page || loading"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg disabled:opacity-30 hover:bg-slate-100 transition-colors"
          >
            Next →
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useAuth } from '../../composables/useAuth';
import api from '../../services/api';

const { hasPermission } = useAuth();

const suppliers = ref([]);
const loading = ref(false);
const error = ref(null);
const searchQuery = ref('');
const statusFilter = ref('');
const cityFilter = ref('');

const pagination = reactive({
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1,
});

const availableCities = computed(() => {
  const set = new Set();
  suppliers.value.forEach(s => {
    if (s.city) set.add(s.city);
  });
  return Array.from(set);
});

function formatCurrency(val) {
  return Number(val || 0).toLocaleString('en-IN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

let debounceTimer = null;
function debouncedSearch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchSuppliers(1);
  }, 350);
}

async function fetchSuppliers(page = 1) {
  loading.value = true;
  error.value = null;
  try {
    const params = {
      page,
      per_page: pagination.per_page,
    };
    if (searchQuery.value) params.search = searchQuery.value;
    if (statusFilter.value) params.status = statusFilter.value;
    if (cityFilter.value) params.city = cityFilter.value;

    const res = await api.get('/suppliers', { params });
    const payload = res.data || res;

    if (payload.items && Array.isArray(payload.items)) {
      suppliers.value = payload.items;
      if (payload.pagination) Object.assign(pagination, payload.pagination);
    } else if (Array.isArray(payload)) {
      suppliers.value = payload;
    } else {
      suppliers.value = [];
    }
  } catch (err) {
    console.error('Failed to load suppliers:', err);
    error.value = err.response?.data?.message || 'Unable to connect to supplier database. Please try again.';
  } finally {
    loading.value = false;
  }
}

async function deleteSupplier(s) {
  const name = s.company_name || s.name || `SUP-${s.id}`;
  if (!confirm(`Are you sure you want to delete supplier "${name}"?`)) return;
  try {
    await api.delete(`/suppliers/${s.id}`);
    fetchSuppliers(pagination.current_page);
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to delete supplier.');
  }
}

onMounted(() => {
  fetchSuppliers(1);
});
</script>
