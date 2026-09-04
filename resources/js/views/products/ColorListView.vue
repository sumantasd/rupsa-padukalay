<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-12 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Footwear Colorways</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Manage product color names, color codes (BLK, RED, WHT) and HEX swatches.
        </p>
      </div>
      <button
        v-if="hasPermission('products.create')"
        @click="openModal()"
        class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-black shadow-md shadow-red-600/20 transition-all flex items-center gap-2 self-start sm:self-auto"
      >
        <span>🎨</span>
        <span>Add Color</span>
      </button>
    </div>

    <!-- Search & Filters -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
      <div class="relative w-full sm:w-80">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
        <input
          v-model="searchQuery"
          @input="fetchColors"
          type="text"
          placeholder="Search color name or code (e.g. Black, BLK)..."
          class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white transition-all"
        />
      </div>

      <div class="text-xs font-black text-slate-700">
        {{ colors.length }} Colors Available
      </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
      <div class="w-full overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-wider">
            <tr>
              <th class="px-5 py-3.5">Swatch</th>
              <th class="px-5 py-3.5">Color Name</th>
              <th class="px-5 py-3.5">Color Code</th>
              <th class="px-5 py-3.5">HEX Value</th>
              <th class="px-5 py-3.5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
            <tr v-if="loading">
              <td colspan="5" class="px-5 py-8 text-center text-slate-400">
                <span class="animate-pulse font-bold">Loading colorways...</span>
              </td>
            </tr>
            <tr v-else-if="colors.length === 0">
              <td colspan="5" class="px-5 py-8 text-center text-slate-400">
                No colors found. Click "Add Color" to create one.
              </td>
            </tr>
            <tr v-for="c in colors" :key="c.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="px-5 py-3.5">
                <div
                  class="h-7 w-7 rounded-full border border-slate-300 shadow-xs"
                  :style="{ backgroundColor: c.hex_code || '#000000' }"
                ></div>
              </td>
              <td class="px-5 py-3.5 font-bold text-slate-900 text-sm">
                {{ c.name }}
              </td>
              <td class="px-5 py-3.5 font-mono text-[11px] font-bold text-slate-600">
                <span class="px-2 py-0.5 rounded bg-slate-100 uppercase">
                  {{ c.code }}
                </span>
              </td>
              <td class="px-5 py-3.5 font-mono text-[11px] text-slate-500">
                {{ c.hex_code || '#000000' }}
              </td>
              <td class="px-5 py-3.5 text-right space-x-2">
                <button
                  v-if="hasPermission('products.edit')"
                  @click="openModal(c)"
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

    <!-- Create / Edit Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
      <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
          <h3 class="font-black text-sm">{{ isEditing ? 'Edit Color' : 'Create New Color' }}</h3>
          <button @click="closeModal" class="text-slate-400 hover:text-white text-lg">✕</button>
        </div>

        <form @submit.prevent="saveColor" class="p-6 space-y-4 text-xs">
          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">Color Name *</label>
            <input
              v-model="form.name"
              type="text"
              required
              placeholder="e.g. Jet Black, Cherry Red, Tan Brown"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">Color Code (3-4 Chars for SKU) *</label>
            <input
              v-model="form.code"
              type="text"
              required
              maxlength="5"
              placeholder="e.g. BLK, RED, BRN, WHT"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 font-mono uppercase focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">HEX Swatch Picker</label>
            <div class="flex items-center gap-3">
              <input
                v-model="form.hex_code"
                type="color"
                class="h-9 w-12 rounded-lg border border-slate-200 cursor-pointer p-0.5"
              />
              <input
                v-model="form.hex_code"
                type="text"
                placeholder="#000000"
                class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600"
              />
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
            <button
              type="button"
              @click="closeModal"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="submitting"
              class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold transition-colors shadow-md shadow-red-600/20"
            >
              {{ submitting ? 'Saving...' : (isEditing ? 'Update Color' : 'Create Color') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useAuth } from '../../composables/useAuth';
import api from '../../services/api';

const { hasPermission } = useAuth();

const colors = ref([]);
const loading = ref(false);
const submitting = ref(false);
const searchQuery = ref('');

const showModal = ref(false);
const isEditing = ref(false);

const form = reactive({
  id: null,
  name: '',
  code: '',
  hex_code: '#000000',
});

async function fetchColors() {
  loading.value = true;
  try {
    const params = {};
    if (searchQuery.value) params.search = searchQuery.value;

    const response = await api.get('/colors', { params });
    colors.value = response.data || [];
  } catch (err) {
    console.error('Failed to fetch colors:', err);
  } finally {
    loading.value = false;
  }
}

function openModal(color = null) {
  if (color) {
    isEditing.value = true;
    form.id = color.id;
    form.name = color.name;
    form.code = color.code;
    form.hex_code = color.hex_code || '#000000';
  } else {
    isEditing.value = false;
    form.id = null;
    form.name = '';
    form.code = '';
    form.hex_code = '#000000';
  }
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
}

async function saveColor() {
  submitting.value = true;
  try {
    if (isEditing.value) {
      await api.put(`/colors/${form.id}`, form);
    } else {
      await api.post('/colors', form);
    }
    closeModal();
    fetchColors();
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to save color.');
  } finally {
    submitting.value = false;
  }
}

onMounted(() => {
  fetchColors();
});
</script>
