<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-12 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Footwear Brands</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Manage retail brand partners, logo images and featured status.
        </p>
      </div>
      <button
        v-if="hasPermission('products.create')"
        @click="openModal()"
        class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-black shadow-md shadow-red-600/20 transition-all flex items-center gap-2 self-start sm:self-auto"
      >
        <span>🏅</span>
        <span>Add Brand</span>
      </button>
    </div>

    <!-- Search & Filters -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
      <div class="relative w-full sm:w-80">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
        <input
          v-model="searchQuery"
          @input="fetchBrands"
          type="text"
          placeholder="Search brand name..."
          class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white transition-all"
        />
      </div>

      <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
        <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer">
          <input
            v-model="activeOnly"
            @change="fetchBrands"
            type="checkbox"
            class="rounded text-red-600 focus:ring-red-600"
          />
          <span>Active Only</span>
        </label>
        <span class="text-xs font-extrabold text-slate-400">|</span>
        <span class="text-xs font-black text-slate-700">{{ brands.length }} Brands</span>
      </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
      <div class="w-full overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-wider">
            <tr>
              <th class="px-5 py-3.5">Brand Logo & Name</th>
              <th class="px-5 py-3.5">Slug</th>
              <th class="px-5 py-3.5">Product Count</th>
              <th class="px-5 py-3.5">Web Featured</th>
              <th class="px-5 py-3.5">Status</th>
              <th class="px-5 py-3.5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
            <tr v-if="loading">
              <td colspan="6" class="px-5 py-8 text-center text-slate-400">
                <span class="animate-pulse font-bold">Loading footwear brands...</span>
              </td>
            </tr>
            <tr v-else-if="brands.length === 0">
              <td colspan="6" class="px-5 py-8 text-center text-slate-400">
                No brands found. Click "Add Brand" to create one.
              </td>
            </tr>
            <tr v-for="b in brands" :key="b.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="px-5 py-3.5 font-bold text-slate-900 flex items-center gap-3">
                <div class="h-9 w-9 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center overflow-hidden shrink-0">
                  <img v-if="b.logo_url" :src="b.logo_url" class="h-full w-full object-cover" />
                  <span v-else class="text-base">🏅</span>
                </div>
                <span>{{ b.name }}</span>
              </td>
              <td class="px-5 py-3.5 font-mono text-[11px] text-slate-500">{{ b.slug }}</td>
              <td class="px-5 py-3.5 font-bold text-slate-800">
                {{ b.products_count !== undefined ? b.products_count : (b.products?.length || 0) }} Articles
              </td>
              <td class="px-5 py-3.5">
                <span
                  :class="[
                    'px-2 py-0.5 rounded text-[10px] font-extrabold uppercase',
                    b.is_featured_on_web ? 'bg-purple-50 text-purple-700 border border-purple-200' : 'bg-slate-100 text-slate-500'
                  ]"
                >
                  {{ b.is_featured_on_web ? 'Featured' : 'Standard' }}
                </span>
              </td>
              <td class="px-5 py-3.5">
                <span
                  :class="[
                    'px-2 py-0.5 rounded text-[10px] font-extrabold uppercase',
                    b.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'
                  ]"
                >
                  {{ b.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-5 py-3.5 text-right space-x-2">
                <button
                  v-if="hasPermission('products.edit')"
                  @click="openModal(b)"
                  class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg font-bold text-[11px] transition-colors"
                >
                  Edit
                </button>
                <button
                  v-if="hasPermission('products.edit')"
                  @click="toggleStatus(b)"
                  :class="[
                    'px-2.5 py-1 rounded-lg font-bold text-[11px] transition-colors',
                    b.is_active ? 'bg-red-50 hover:bg-red-100 text-red-700' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700'
                  ]"
                >
                  {{ b.is_active ? 'Deactivate' : 'Activate' }}
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
          <h3 class="font-black text-sm">{{ isEditing ? 'Edit Brand' : 'Create New Brand' }}</h3>
          <button @click="closeModal" class="text-slate-400 hover:text-white text-lg">✕</button>
        </div>

        <form @submit.prevent="saveBrand" class="p-6 space-y-4 text-xs">
          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">Brand Name *</label>
            <input
              v-model="form.name"
              type="text"
              required
              placeholder="e.g. Bata, Paragon, Woodland"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <!-- Real Logo Image File Upload Area -->
          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">Brand Logo Image (File Upload Only)</label>
            <div
              @dragover.prevent="isDragging = true"
              @dragleave.prevent="isDragging = false"
              @drop.prevent="handleDrop"
              :class="[
                'border-2 border-dashed rounded-xl p-4 text-center cursor-pointer transition-all flex flex-col items-center justify-center',
                isDragging ? 'border-red-600 bg-red-50/50' : 'border-slate-300 bg-slate-50 hover:bg-slate-100'
              ]"
              @click="triggerFileInput"
            >
              <input
                ref="fileInputRef"
                type="file"
                accept="image/jpeg,image/jpg,image/png,image/webp"
                class="hidden"
                @change="handleFileSelect"
              />

              <div v-if="!imagePreviewUrl" class="space-y-1">
                <span class="text-xl block">🏅</span>
                <span class="font-bold text-slate-800 text-[11px] block">Click or drag brand logo file here</span>
                <span class="text-[10px] text-slate-400 block font-bold">JPG, PNG, WEBP (Max 5MB)</span>
              </div>

              <div v-else class="flex flex-col items-center">
                <img :src="imagePreviewUrl" class="h-24 w-24 object-cover rounded-xl border border-slate-200 shadow-xs bg-white p-0.5" />
                <div class="mt-2 flex items-center gap-2">
                  <button type="button" @click.stop="triggerFileInput" class="px-2.5 py-1 bg-slate-900 text-white font-bold rounded-md text-[10px]">Change</button>
                  <button type="button" @click.stop="removeImage" class="px-2.5 py-1 bg-red-50 text-red-700 font-bold rounded-md text-[10px]">Remove</button>
                </div>
              </div>
            </div>
            <p v-if="fileError" class="text-[10px] text-red-600 font-bold mt-1">⚠️ {{ fileError }}</p>
          </div>

          <div class="flex items-center gap-6 pt-2">
            <label class="flex items-center gap-2 font-bold text-slate-700 cursor-pointer">
              <input v-model="form.is_featured_on_web" type="checkbox" class="rounded text-red-600 focus:ring-red-600" />
              <span>Featured Brand on Web</span>
            </label>
            <label class="flex items-center gap-2 font-bold text-slate-700 cursor-pointer">
              <input v-model="form.is_active" type="checkbox" class="rounded text-red-600 focus:ring-red-600" />
              <span>Active</span>
            </label>
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
              {{ submitting ? 'Uploading & Saving...' : (isEditing ? 'Update Brand' : 'Create Brand') }}
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

const brands = ref([]);
const loading = ref(false);
const submitting = ref(false);
const searchQuery = ref('');
const activeOnly = ref(false);

const showModal = ref(false);
const isEditing = ref(false);

const fileInputRef = ref(null);
const selectedFile = ref(null);
const imagePreviewUrl = ref('');
const fileError = ref('');
const isDragging = ref(false);

const form = reactive({
  id: null,
  name: '',
  is_featured_on_web: false,
  is_active: true,
});

function triggerFileInput() {
  fileInputRef.value?.click();
}

function validateAndSetFile(file) {
  fileError.value = '';
  if (!file) return;

  const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!validTypes.includes(file.type)) {
    fileError.value = 'Invalid logo format. Allowed: JPG, JPEG, PNG, WEBP.';
    return;
  }

  if (file.size > 5 * 1024 * 1024) {
    fileError.value = 'File size exceeds 5MB limit.';
    return;
  }

  selectedFile.value = file;
  imagePreviewUrl.value = URL.createObjectURL(file);
}

function handleFileSelect(e) {
  const files = e.target.files;
  if (files && files[0]) validateAndSetFile(files[0]);
}

function handleDrop(e) {
  isDragging.value = false;
  const files = e.dataTransfer?.files;
  if (files && files[0]) validateAndSetFile(files[0]);
}

function removeImage() {
  selectedFile.value = null;
  imagePreviewUrl.value = '';
  fileError.value = '';
  if (fileInputRef.value) fileInputRef.value.value = '';
}

async function fetchBrands() {
  loading.value = true;
  try {
    const params = {};
    if (searchQuery.value) params.search = searchQuery.value;
    if (activeOnly.value) params.active_only = true;

    const response = await api.get('/brands', { params });
    brands.value = response.data || [];
  } catch (err) {
    console.error('Failed to fetch brands:', err);
  } finally {
    loading.value = false;
  }
}

function openModal(brand = null) {
  removeImage();
  if (brand) {
    isEditing.value = true;
    form.id = brand.id;
    form.name = brand.name;
    form.is_featured_on_web = brand.is_featured_on_web === true;
    form.is_active = brand.is_active !== false;
    if (brand.logo_url) {
      imagePreviewUrl.value = brand.logo_url;
    }
  } else {
    isEditing.value = false;
    form.id = null;
    form.name = '';
    form.is_featured_on_web = false;
    form.is_active = true;
  }
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
}

async function saveBrand() {
  if (fileError.value) return;

  submitting.value = true;
  try {
    const formData = new FormData();
    formData.append('name', form.name);
    formData.append('is_featured_on_web', form.is_featured_on_web ? '1' : '0');
    formData.append('is_active', form.is_active ? '1' : '0');

    if (selectedFile.value) {
      formData.append('logo', selectedFile.value);
    }

    if (isEditing.value) {
      formData.append('_method', 'PUT');
      await api.post(`/brands/${form.id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
    } else {
      await api.post('/brands', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
    }
    closeModal();
    fetchBrands();
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to save brand.');
  } finally {
    submitting.value = false;
  }
}

async function toggleStatus(brand) {
  try {
    await api.patch(`/brands/${brand.id}/status`);
    fetchBrands();
  } catch (err) {
    alert('Failed to toggle brand status.');
  }
}

onMounted(() => {
  fetchBrands();
});
</script>
