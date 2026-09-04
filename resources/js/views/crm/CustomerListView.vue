<template>
  <div class="space-y-6 antialiased font-sans max-w-7xl mx-auto pb-16">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Customer Directory</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Manage registered footwear customers, purchase history, reward points & store credit balances.
        </p>
      </div>
      <router-link
        v-if="hasPermission('customers.create')"
        to="/admin/customers/create"
        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white font-black text-xs rounded-xl shadow-md shadow-red-600/20 transition-all uppercase tracking-wider shrink-0"
      >
        <span>➕</span>
        <span>Add Customer</span>
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
          placeholder="Search by customer name, mobile, email or city..."
          class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-4 py-2.5 text-xs text-slate-900 font-medium focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
        />
      </div>

      <div class="flex items-center gap-3 w-full md:w-auto overflow-x-auto">
        <select
          v-model="cityFilter"
          @change="fetchCustomers(1)"
          class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-red-600"
        >
          <option value="">All Cities</option>
          <option v-for="c in availableCities" :key="c" :value="c">{{ c }}</option>
        </select>

        <button
          @click="fetchCustomers(1)"
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
      <h3 class="font-black text-sm text-red-900">Unable to load customer directory</h3>
      <p class="text-xs text-red-700 font-medium max-w-md mx-auto">{{ error }}</p>
      <button @click="fetchCustomers(1)" class="px-4 py-2 bg-red-600 text-white font-bold rounded-xl text-xs shadow-xs">
        Try Again
      </button>
    </div>

    <!-- Empty State -->
    <div v-else-if="customers.length === 0" class="p-12 bg-white rounded-2xl border border-slate-200/90 text-center space-y-4 shadow-xs">
      <div class="h-16 w-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto text-3xl">
        👥
      </div>
      <div class="space-y-1">
        <h3 class="font-black text-base text-slate-900">No Customers Found</h3>
        <p class="text-xs text-slate-500 max-w-sm mx-auto">
          No registered customer records match your current search or filter query.
        </p>
      </div>
      <router-link
        v-if="hasPermission('customers.create')"
        to="/admin/customers/create"
        class="inline-block px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl shadow-xs"
      >
        ➕ Register First Customer
      </router-link>
    </div>

    <!-- DataTable View (Desktop & Tablet) -->
    <div v-else class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
              <th class="py-3.5 px-4">Customer Name</th>
              <th class="py-3.5 px-4">Mobile Number</th>
              <th class="py-3.5 px-4">Email</th>
              <th class="py-3.5 px-4">City / Address</th>
              <th class="py-3.5 px-4 text-center">Reward Points</th>
              <th class="py-3.5 px-4 text-right">Total Spent</th>
              <th class="py-3.5 px-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
            <tr v-for="c in customers" :key="c.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="py-3.5 px-4">
                <router-link :to="'/admin/customers/' + c.id" class="font-black text-slate-900 hover:text-red-600 transition-colors block">
                  {{ c.name || 'Walk-in Customer' }}
                </router-link>
                <span class="text-[10px] text-slate-400 font-mono">CUST-{{ String(c.id).padStart(4, '0') }}</span>
              </td>

              <td class="py-3.5 px-4">
                <a :href="'tel:' + (c.mobile_number || c.phone || '').replace(/\s+/g, '')" class="font-mono font-bold text-slate-800 hover:text-red-600">
                  {{ c.mobile_number || c.phone || 'N/A' }}
                </a>
              </td>

              <td class="py-3.5 px-4 text-slate-500">
                {{ c.email || '—' }}
              </td>

              <td class="py-3.5 px-4 text-slate-600">
                {{ c.city || 'Dhantala' }}
              </td>

              <td class="py-3.5 px-4 text-center">
                <span class="px-2.5 py-1 rounded-full bg-purple-50 text-purple-700 border border-purple-200 font-black text-[10px]">
                  ⭐ {{ c.reward_points || c.loyalty_points || 0 }} pts
                </span>
              </td>

              <td class="py-3.5 px-4 text-right font-mono font-black text-slate-900">
                ₹{{ Number(c.total_spent_amount || c.total_spent || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
              </td>

              <td class="py-3.5 px-4 text-right space-x-1.5 whitespace-nowrap">
                <router-link
                  :to="'/admin/customers/' + c.id"
                  class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-bold text-[11px] transition-colors"
                >
                  👁️ View
                </router-link>

                <router-link
                  v-if="hasPermission('customers.edit')"
                  :to="'/admin/customers/' + c.id + '/edit'"
                  class="px-2.5 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 rounded-lg font-bold text-[11px] border border-amber-200 transition-colors"
                >
                  ✏️ Edit
                </router-link>

                <button
                  v-if="hasPermission('customers.delete')"
                  @click="deleteCustomer(c.id, c.name)"
                  class="px-2 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 rounded-lg font-bold text-[11px] border border-red-200 transition-colors"
                >
                  🗑️
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs font-bold text-slate-600">
        <div>Showing Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} Total Customers)</div>
        <div class="flex items-center gap-2">
          <button
            @click="fetchCustomers(pagination.current_page - 1)"
            :disabled="pagination.current_page === 1"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg disabled:opacity-30 hover:bg-slate-100"
          >
            ← Previous
          </button>
          <button
            @click="fetchCustomers(pagination.current_page + 1)"
            :disabled="pagination.current_page === pagination.last_page"
            class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg disabled:opacity-30 hover:bg-slate-100"
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

const customers = ref([]);
const loading = ref(false);
const error = ref(null);
const searchQuery = ref('');
const cityFilter = ref('');

const pagination = reactive({
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1,
});

const availableCities = computed(() => {
  const set = new Set();
  customers.value.forEach(c => {
    if (c.city) set.add(c.city);
  });
  return Array.from(set);
});

let debounceTimer = null;
function debouncedSearch() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchCustomers(1);
  }, 350);
}

async function fetchCustomers(page = 1) {
  loading.value = true;
  error.value = null;
  try {
    const params = {
      page,
      per_page: pagination.per_page,
    };
    if (searchQuery.value) params.search = searchQuery.value;
    if (cityFilter.value) params.city = cityFilter.value;

    const res = await api.get('/customers', { params });
    const payload = res.data || res;

    if (payload.items && Array.isArray(payload.items)) {
      customers.value = payload.items;
      if (payload.pagination) Object.assign(pagination, payload.pagination);
    } else if (Array.isArray(payload)) {
      customers.value = payload;
    } else if (payload.data && Array.isArray(payload.data)) {
      customers.value = payload.data;
    } else {
      customers.value = [];
    }
  } catch (err) {
    console.error('Failed to load customers:', err);
    error.value = err.response?.data?.message || 'Unable to connect to customer database. Please try again.';
  } finally {
    loading.value = false;
  }
}

async function deleteCustomer(id, name) {
  if (!confirm(`Are you sure you want to delete customer "${name || id}"?`)) return;
  try {
    await api.delete(`/customers/${id}`);
    fetchCustomers(pagination.current_page);
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to delete customer.');
  }
}

onMounted(() => {
  fetchCustomers(1);
});
</script>
