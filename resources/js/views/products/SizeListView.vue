<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-12 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
          {{ activeTab === 'master' ? 'Footwear Size Master' : 'Size Chart Configuration' }}
        </h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          {{ activeTab === 'master' ? 'Manage shoe sizing systems (UK/IND, EU, US, CM) and size sorting sequences.' : 'Manage dynamic size chart matrices, conversions, category defaults & product sizing guidelines.' }}
        </p>
      </div>

      <div class="flex items-center gap-2 self-start sm:self-auto">
        <!-- Tab Switcher -->
        <div class="bg-slate-100 p-1 rounded-2xl flex items-center border border-slate-200 text-xs font-bold mr-2">
          <button
            @click="activeTab = 'master'"
            :class="activeTab === 'master' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-900'"
            class="px-3.5 py-1.5 rounded-xl transition-all"
          >
            📏 Size Master
          </button>
          <button
            @click="activeTab = 'config'"
            :class="activeTab === 'config' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-900'"
            class="px-3.5 py-1.5 rounded-xl transition-all"
          >
            ⚙️ Configuration
          </button>
        </div>

        <button
          v-if="activeTab === 'master' && hasPermission('products.create')"
          @click="openModal()"
          class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-black shadow-md shadow-red-600/20 transition-all flex items-center gap-2"
        >
          <span>📏</span>
          <span>Add Size</span>
        </button>
      </div>
    </div>

    <!-- TAB 1: SIZE MASTER -->
    <div v-if="activeTab === 'master'" class="space-y-6">
      <!-- Search & System Filter -->
      <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="flex items-center gap-3 w-full sm:w-auto">
          <div class="relative w-full sm:w-64">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
            <input
              v-model="searchQuery"
              @input="fetchSizes"
              type="text"
              placeholder="Search size number (e.g. 7, 8, 42)..."
              class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white transition-all"
            />
          </div>

          <select
            v-model="systemFilter"
            @change="fetchSizes"
            class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600"
          >
            <option value="">All Size Systems</option>
            <option value="IND">IND (India)</option>
            <option value="UK/IND">IND / UK</option>
            <option value="EU">EU</option>
            <option value="US">US</option>
            <option value="CM">CM</option>
          </select>
        </div>

        <div class="text-xs font-black text-slate-700">
          {{ sizes.length }} Sizes Available
        </div>
      </div>

      <!-- Data Table -->
      <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
        <div class="w-full overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-wider">
              <tr>
                <th class="px-5 py-3.5">Size Number</th>
                <th class="px-5 py-3.5">Sizing System</th>
                <th class="px-5 py-3.5">Sort Order</th>
                <th class="px-5 py-3.5">Status</th>
                <th class="px-5 py-3.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
              <tr v-if="loading">
                <td colspan="5" class="px-5 py-8 text-center text-slate-400">
                  <span class="animate-pulse font-bold">Loading footwear sizes...</span>
                </td>
              </tr>
              <tr v-else-if="sizes.length === 0">
                <td colspan="5" class="px-5 py-8 text-center text-slate-400">
                  No sizes found. Click "Add Size" to create one.
                </td>
              </tr>
              <tr v-for="sz in sizes" :key="sz.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="px-5 py-3.5 font-black text-slate-900 text-sm flex items-center gap-2">
                  <span class="h-8 w-8 rounded-lg bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-900 font-black text-xs">
                    {{ sz.size_number }}
                  </span>
                  <span>Size {{ sz.size_number }}</span>
                </td>
                <td class="px-5 py-3.5">
                  <span class="px-2.5 py-1 rounded-md bg-red-50 text-red-700 font-extrabold text-[10px] uppercase border border-red-100">
                    {{ sz.size_system || 'UK/IND' }}
                  </span>
                </td>
                <td class="px-5 py-3.5 font-mono text-slate-500 font-bold">
                  {{ sz.sort_order || 0 }}
                </td>
                <td class="px-5 py-3.5">
                  <button
                    @click="toggleSizeStatus(sz)"
                    :class="sz.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'"
                    class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase border transition-colors"
                  >
                    {{ sz.is_active ? 'Active' : 'Inactive' }}
                  </button>
                </td>
                <td class="px-5 py-3.5 text-right space-x-2">
                  <button
                    v-if="hasPermission('products.edit')"
                    @click="openModal(sz)"
                    class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg font-bold text-[11px] transition-colors"
                  >
                    Edit
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB 2: SIZE CHART CONFIGURATION -->
    <div v-else-if="activeTab === 'config'">
      <SizeChartManagement />
    </div>

    <!-- Add/Edit Size Master Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4">
      <div class="bg-white rounded-3xl border border-slate-200/90 shadow-2xl max-w-md w-full p-6 space-y-5">
        <div class="flex items-center justify-between">
          <h3 class="text-base font-black text-slate-900">
            {{ isEditing ? 'Edit Footwear Size' : 'Add Footwear Size' }}
          </h3>
          <button @click="closeModal" class="text-slate-400 hover:text-slate-600 font-bold text-sm">✕</button>
        </div>

        <form @submit.prevent="saveSize" class="space-y-4">
          <div>
            <label class="block text-xs font-black text-slate-700 mb-1">Size Number *</label>
            <input
              v-model="form.size_number"
              type="text"
              required
              placeholder="e.g. 6, 7, 8, 42, 43..."
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <div>
            <label class="block text-xs font-black text-slate-700 mb-1">Sizing System</label>
            <select
              v-model="form.size_system"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600"
            >
              <option value="IND">IND (India)</option>
              <option value="UK/IND">IND / UK</option>
              <option value="EU">EU</option>
              <option value="US">US</option>
              <option value="CM">CM</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-black text-slate-700 mb-1">Sort Order</label>
            <input
              v-model.number="form.sort_order"
              type="number"
              placeholder="0"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600"
            />
          </div>

          <div class="flex items-center gap-2 pt-1">
            <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-black text-slate-700">
              <input v-model="form.is_active" type="checkbox" class="w-4 h-4 text-red-600 rounded focus:ring-red-500" />
              <span>Active Status</span>
            </label>
          </div>

          <div class="flex items-center justify-end gap-2 pt-2">
            <button
              type="button"
              @click="closeModal"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="submitting"
              class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md shadow-red-600/20 disabled:opacity-50"
            >
              {{ submitting ? 'Saving...' : 'Save Size' }}
            </button>
          </div>
        </form>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '../../services/api';
import { useAuth } from '../../composables/useAuth';
import { useToast } from '../../composables/useToast';
import SizeChartManagement from '../../components/size-charts/SizeChartManagement.vue';

const { hasPermission } = useAuth();
const toast = useToast();

const activeTab = ref('master');
const sizes = ref([]);
const loading = ref(false);
const submitting = ref(false);
const searchQuery = ref('');
const systemFilter = ref('');

const showModal = ref(false);
const isEditing = ref(false);

const form = reactive({
  id: null,
  size_number: '',
  size_system: 'IND',
  sort_order: 0,
  is_active: true,
});

async function fetchSizes() {
  loading.value = true;
  try {
    const params = {};
    if (searchQuery.value) params.search = searchQuery.value;
    if (systemFilter.value) params.system = systemFilter.value;

    const response = await api.get('/sizes', { params });
    sizes.value = response.data || [];
  } catch (err) {
    console.error('Failed to fetch sizes:', err);
    toast.error(err.message || 'Failed to fetch sizes.');
  } finally {
    loading.value = false;
  }
}

function openModal(size = null) {
  if (size) {
    isEditing.value = true;
    form.id = size.id;
    form.size_number = size.size_number;
    form.size_system = size.size_system || 'IND';
    form.sort_order = size.sort_order || 0;
    form.is_active = size.is_active ?? true;
  } else {
    isEditing.value = false;
    form.id = null;
    form.size_number = '';
    form.size_system = 'IND';
    form.sort_order = 0;
    form.is_active = true;
  }

  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
}

async function saveSize() {
  submitting.value = true;
  try {
    let res;
    if (isEditing.value) {
      res = await api.put(`/sizes/${form.id}`, form);
    } else {
      res = await api.post('/sizes', form);
    }
    toast.success(res.message || (isEditing.value ? 'Size updated.' : 'Size created.'));
    closeModal();
    fetchSizes();
  } catch (err) {
    toast.error(err.message || 'Failed to save size.');
  } finally {
    submitting.value = false;
  }
}

async function toggleSizeStatus(sz) {
  try {
    const res = await api.patch(`/sizes/${sz.id}/status`);
    toast.success(res.message || 'Size status updated.');
    fetchSizes();
  } catch (err) {
    toast.error(err.message || 'Failed to update status.');
  }
}

onMounted(() => {
  fetchSizes();
});
</script>
