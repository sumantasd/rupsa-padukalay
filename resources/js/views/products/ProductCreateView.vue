<template>
  <div class="space-y-6 max-w-5xl mx-auto pb-16 antialiased font-sans">
    <!-- Top Header & Navigation -->
    <div class="flex items-center justify-between border-b border-slate-200/80 pb-4">
      <div class="flex items-center gap-3">
        <router-link
          to="/admin/products"
          class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-extrabold text-xs transition-colors"
          title="Back to Product List"
        >
          ← Back
        </router-link>
        <div>
          <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Create Footwear Article</h1>
          <p class="text-xs text-slate-500 font-medium mt-0.5">
            Add new footwear product, file upload image, color variants, size chart SKUs and initial opening stock.
          </p>
        </div>
      </div>
    </div>

    <form @submit.prevent="submitProduct" class="space-y-6">
      <!-- 1. PRODUCT BASIC INFORMATION -->
      <div class="bg-white rounded-2xl border border-slate-200/90 p-6 shadow-xs space-y-4">
        <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
          <span class="text-base">👟</span>
          <h2 class="text-xs font-black text-slate-900 uppercase tracking-wider">Product Information</h2>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">
          <!-- Article Number -->
          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">Article Number / Code *</label>
            <input
              v-model="form.article_number"
              type="text"
              required
              placeholder="e.g. RP-MEN-001"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 font-mono text-xs uppercase text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
            <p class="text-[10px] text-slate-400">Unique article code across inventory.</p>
          </div>

          <!-- Product Name -->
          <div class="space-y-1 sm:col-span-2">
            <label class="font-bold text-slate-700 block">Product Name *</label>
            <input
              v-model="form.name"
              type="text"
              required
              placeholder="e.g. Executive Classic Leather Oxford"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <!-- Category -->
          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">Category *</label>
            <select
              v-model="form.category_id"
              @change="onCategoryChange"
              required
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            >
              <option value="">-- Select Category --</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>

          <!-- Size Chart Selection -->
          <div class="space-y-1 sm:col-span-2">
            <label class="font-bold text-slate-700 block flex items-center justify-between">
              <span>Size Chart Configuration</span>
              <span v-if="suggestedChart" class="text-[10px] text-red-600 font-black">⭐ Category Default Suggested</span>
            </label>
            <select
              v-model="form.size_chart_id"
              @change="onSizeChartSelect"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white font-bold"
            >
              <option :value="null">-- None / All Master Sizes --</option>
              <option v-for="chart in sizeCharts" :key="chart.id" :value="chart.id">
                {{ chart.name }} ({{ chart.row_count }} sizes • {{ chart.gender || 'Unisex' }})
              </option>
            </select>
          </div>

          <!-- Brand -->
          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">Brand *</label>
            <select
              v-model="form.brand_id"
              required
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            >
              <option value="">-- Select Brand --</option>
              <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
          </div>

          <!-- Gender -->
          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">Gender Target *</label>
            <select
              v-model="form.gender"
              required
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            >
              <option value="men">Men</option>
              <option value="women">Women</option>
              <option value="boys">Boys</option>
              <option value="girls">Girls</option>
              <option value="unisex">Unisex</option>
            </select>
          </div>

          <!-- Upper Material -->
          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">Upper Material</label>
            <input
              v-model="form.upper_material"
              type="text"
              placeholder="e.g. Full Grain Genuine Leather"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <!-- Sole Material -->
          <div class="space-y-1 sm:col-span-2">
            <label class="font-bold text-slate-700 block">Sole Material</label>
            <input
              v-model="form.sole_material"
              type="text"
              placeholder="e.g. TPR / Rubber Anti-Skid"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>
        </div>
      </div>

      <!-- 2. REAL PRODUCT IMAGE FILE UPLOAD SECTION -->
      <div class="bg-white rounded-2xl border border-slate-200/90 p-6 shadow-xs space-y-4">
        <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
          <span class="text-base">📸</span>
          <h2 class="text-xs font-black text-slate-900 uppercase tracking-wider">Upload Product Image (File Upload Only)</h2>
        </div>

        <div class="space-y-3">
          <!-- Drag and Drop / Select Area -->
          <div
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="handleDrop"
            :class="[
              'border-2 border-dashed rounded-2xl p-6 transition-all text-center flex flex-col items-center justify-center cursor-pointer',
              isDragging ? 'border-red-600 bg-red-50/50' : 'border-slate-300 bg-slate-50 hover:bg-slate-100/80'
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

            <div v-if="!imagePreviewUrl" class="space-y-2">
              <div class="h-12 w-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center mx-auto text-xl shadow-xs">
                📷
              </div>
              <p class="font-black text-slate-800 text-xs">
                Click to browse or drag & drop product image here
              </p>
              <p class="text-[10px] text-slate-400 font-bold">
                Supported Formats: JPG, JPEG, PNG, WEBP (Max File Size: 5 MB)
              </p>
            </div>

            <!-- Image Preview Box -->
            <div v-else class="relative group flex flex-col items-center">
              <img
                :src="imagePreviewUrl"
                class="max-h-48 rounded-xl object-contain border border-slate-200 shadow-md bg-white p-1"
              />
              <div class="mt-3 flex items-center gap-3">
                <button
                  type="button"
                  @click.stop="triggerFileInput"
                  class="px-3 py-1.5 bg-slate-900 text-white rounded-lg text-xs font-bold hover:bg-slate-800 transition-colors"
                >
                  Change Image
                </button>
                <button
                  type="button"
                  @click.stop="removeImage"
                  class="px-3 py-1.5 bg-red-50 text-red-700 hover:bg-red-100 rounded-lg text-xs font-bold transition-colors"
                >
                  Remove Image
                </button>
              </div>
            </div>
          </div>

          <p v-if="fileValidationError" class="text-xs font-bold text-red-600">
            ⚠️ {{ fileValidationError }}
          </p>
        </div>
      </div>

      <!-- 3. DEFAULT PRICING & HSN -->
      <div class="bg-white rounded-2xl border border-slate-200/90 p-6 shadow-xs space-y-4">
        <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
          <span class="text-base">🏷️</span>
          <h2 class="text-xs font-black text-slate-900 uppercase tracking-wider">Default Pricing & HSN Tax</h2>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 text-xs">
          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">MRP (₹) *</label>
            <input
              v-model.number="form.mrp"
              type="number"
              step="0.01"
              required
              min="0"
              placeholder="1299.00"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-900 font-bold focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">Selling Price (₹) *</label>
            <input
              v-model.number="form.selling_price"
              type="number"
              step="0.01"
              required
              min="0"
              placeholder="999.00"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-900 font-bold focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">Cost Price (₹)</label>
            <input
              v-model.number="form.cost_price"
              type="number"
              step="0.01"
              min="0"
              placeholder="e.g. 500.00"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-900 font-bold focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <div class="space-y-1">
            <label class="font-bold text-slate-700 block">HSN / Tax Code</label>
            <select
              v-model="form.hsn_code_id"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            >
              <option value="">-- Select HSN Code --</option>
              <option v-for="hsn in hsnCodes" :key="hsn.id" :value="hsn.id">
                {{ hsn.code }} (GST {{ hsn.gst_rate }}%)
              </option>
            </select>
          </div>
        </div>
      </div>

      <!-- 4. DYNAMIC AVAILABLE SIZES SELECTION -->
      <div class="bg-white rounded-2xl border border-slate-200/90 p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div class="flex items-center gap-2">
            <span class="text-base">📏</span>
            <div>
              <h2 class="text-xs font-black text-slate-900 uppercase tracking-wider">Available Sizes Dynamic Selection</h2>
              <p class="text-[10px] text-slate-400 font-medium">
                {{ activeChartName ? `Loaded from Size Chart: '${activeChartName}'` : 'Loaded from Central Size Master' }}
              </p>
            </div>
          </div>
          <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase bg-emerald-50 text-emerald-800 border border-emerald-200">
            {{ availableSizes.length }} Size(s) Available
          </span>
        </div>

        <!-- Checkbox Grid for Available Sizes -->
        <div class="space-y-2">
          <label class="block text-xs font-bold text-slate-700">Select Applicable Sizes for this Article:</label>
          <div class="flex flex-wrap items-center gap-2">
            <label
              v-for="sz in availableSizes"
              :key="sz.id || sz.size_number"
              :class="[
                'px-3 py-1.5 rounded-xl border text-xs font-black cursor-pointer transition-all flex items-center gap-1.5 select-none',
                selectedSizes.includes(sz.size_number)
                  ? 'bg-red-600 text-white border-red-600 shadow-xs'
                  : 'bg-slate-50 text-slate-800 border-slate-200 hover:bg-slate-100'
              ]"
            >
              <input
                type="checkbox"
                :value="sz.size_number"
                v-model="selectedSizes"
                @change="onSelectedSizesChange"
                class="hidden"
              />
              <span>Size {{ sz.size_number }}</span>
            </label>
          </div>
        </div>
      </div>

      <!-- 5. COLOR -> SIZE CHART -> STOCK WORKFLOW MATRIX -->
      <div class="bg-white rounded-2xl border border-slate-200/90 p-6 shadow-xs space-y-6">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div class="flex items-center gap-2">
            <span class="text-base">🎨</span>
            <div>
              <h2 class="text-xs font-black text-slate-900 uppercase tracking-wider">Color Variants & Size Stock Entry</h2>
              <p class="text-[10px] text-slate-400 font-medium">Select colors and enter opening stock for selected size rows.</p>
            </div>
          </div>
          <button
            type="button"
            @click="addColorBlock"
            class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold text-xs transition-colors flex items-center gap-1.5"
          >
            <span>➕</span>
            <span>Add Another Color</span>
          </button>
        </div>

        <div v-for="(colorBlock, colorIndex) in form.color_blocks" :key="colorIndex" class="border border-slate-200 rounded-2xl p-5 space-y-4 bg-slate-50/50">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200/60 pb-3">
            <div class="flex items-center gap-3 w-full sm:w-80">
              <span class="font-black text-slate-800 text-xs shrink-0">Colorway #{{ colorIndex + 1 }}:</span>
              <select
                v-model="colorBlock.color_id"
                required
                class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 font-bold focus:ring-2 focus:ring-red-600"
              >
                <option value="">-- Select Colorway --</option>
                <option v-for="c in colors" :key="c.id" :value="c.id">
                  {{ c.name }} ({{ c.code }})
                </option>
              </select>
            </div>

            <button
              v-if="form.color_blocks.length > 1"
              type="button"
              @click="removeColorBlock(colorIndex)"
              class="text-red-600 hover:text-red-800 font-bold text-xs self-end sm:self-auto"
            >
              Remove Color
            </button>
          </div>

          <!-- Generated Size Chart Stock Table -->
          <div class="overflow-x-auto bg-white rounded-xl border border-slate-200">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-100 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase">
                <tr>
                  <th class="px-4 py-2.5">Footwear Size</th>
                  <th class="px-4 py-2.5">Auto SKU Preview</th>
                  <th class="px-4 py-2.5">MRP (₹)</th>
                  <th class="px-4 py-2.5">Selling Price (₹)</th>
                  <th class="px-4 py-2.5">Opening Stock Qty</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                <tr v-if="colorBlock.size_rows.length === 0">
                  <td colspan="5" class="px-4 py-6 text-center text-slate-400">
                    No sizes selected. Check sizes above to generate stock rows.
                  </td>
                </tr>
                <tr v-for="sizeRow in colorBlock.size_rows" :key="sizeRow.size_id || sizeRow.size_number">
                  <td class="px-4 py-2 font-black text-slate-900">
                    <span class="px-2 py-0.5 rounded bg-slate-100 border border-slate-200 text-slate-900 font-bold">
                      Size {{ sizeRow.size_number }}
                    </span>
                  </td>

                  <td class="px-4 py-2 font-mono text-[10px] font-bold text-red-600">
                    {{ generateSkuPreview(colorBlock.color_id, sizeRow.size_number) }}
                  </td>
                  <td class="px-4 py-2">
                    <input
                      v-model.number="sizeRow.mrp"
                      type="number"
                      step="0.01"
                      class="w-24 bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 text-xs font-bold"
                    />
                  </td>
                  <td class="px-4 py-2">
                    <input
                      v-model.number="sizeRow.selling_price"
                      type="number"
                      step="0.01"
                      class="w-24 bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 text-xs font-bold text-slate-900"
                    />
                  </td>
                  <td class="px-4 py-2">
                    <input
                      v-model.number="sizeRow.opening_stock"
                      type="number"
                      min="0"
                      placeholder="0"
                      class="w-24 bg-amber-50 border border-amber-300 rounded-lg px-2 py-1 text-xs font-black text-slate-900 focus:bg-white"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Submit Controls -->
      <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
        <router-link
          to="/admin/products"
          class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs transition-colors"
        >
          Cancel
        </router-link>
        <button
          type="submit"
          :disabled="submitting"
          class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs transition-all shadow-md shadow-red-600/20 flex items-center gap-2"
        >
          <span>💾</span>
          <span>{{ submitting ? 'Creating Article...' : 'Save Product & Create SKUs' }}</span>
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../services/api';
import { useToast } from '../../composables/useToast';

const router = useRouter();
const toast = useToast();

const categories = ref([]);
const brands = ref([]);
const sizesMaster = ref([]);
const sizeCharts = ref([]);
const colors = ref([]);
const hsnCodes = ref([]);
const stores = ref([]);
const suggestedChart = ref(null);
const activeChartName = ref('');

const availableSizes = ref([]);
const selectedSizes = ref([]);

const submitting = ref(false);
const isDragging = ref(false);
const fileInputRef = ref(null);
const selectedFile = ref(null);
const imagePreviewUrl = ref('');
const fileValidationError = ref('');

const form = reactive({
  article_number: '',
  name: '',
  brand_id: '',
  category_id: '',
  gender: 'men',
  upper_material: '',
  sole_material: '',
  description: '',
  mrp: null,
  selling_price: null,
  cost_price: null,
  hsn_code_id: '',
  size_chart_id: null,
  store_id: '',
  color_blocks: [],
});

function triggerFileInput() {
  fileInputRef.value?.click();
}

function validateAndProcessFile(file) {
  fileValidationError.value = '';
  if (!file) return;

  const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!validTypes.includes(file.type)) {
    fileValidationError.value = 'Invalid image format. Supported formats: JPG, JPEG, PNG, WEBP.';
    return;
  }

  const maxSizeInBytes = 5 * 1024 * 1024; // 5MB
  if (file.size > maxSizeInBytes) {
    fileValidationError.value = 'File size exceeds 5 MB limit. Please select a smaller image.';
    return;
  }

  selectedFile.value = file;
  imagePreviewUrl.value = URL.createObjectURL(file);
}

function handleFileSelect(e) {
  const files = e.target.files;
  if (files && files[0]) {
    validateAndProcessFile(files[0]);
  }
}

function handleDrop(e) {
  isDragging.value = false;
  const files = e.dataTransfer?.files;
  if (files && files[0]) {
    validateAndProcessFile(files[0]);
  }
}

function removeImage() {
  selectedFile.value = null;
  imagePreviewUrl.value = '';
  fileValidationError.value = '';
  if (fileInputRef.value) {
    fileInputRef.value.value = '';
  }
}

async function fetchMasterData() {
  try {
    const [cRes, bRes, sRes, colRes, hsnRes, stRes, scRes] = await Promise.all([
      api.get('/categories'),
      api.get('/brands'),
      api.get('/sizes'),
      api.get('/colors'),
      api.get('/hsn-codes'),
      api.get('/stores'),
      api.get('/size-charts'),
    ]);

    categories.value = cRes.data || [];
    brands.value = bRes.data || [];
    sizesMaster.value = sRes.data || [];
    colors.value = colRes.data || [];
    hsnCodes.value = hsnRes.data || [];
    stores.value = stRes.data || [];
    sizeCharts.value = scRes.data || [];

    if (categories.value.length) {
      form.category_id = categories.value[0].id;
      await onCategoryChange();
    } else {
      updateAvailableSizes();
    }
    if (brands.value.length) form.brand_id = brands.value[0].id;
    if (stores.value.length) form.store_id = stores.value[0].id;

  } catch (err) {
    console.error('Failed to load master data for product create:', err);
    toast.error('Failed to load master data.');
  }
}

async function onCategoryChange() {
  if (!form.category_id) return;
  try {
    const res = await api.get(`/categories/${form.category_id}/size-chart`);
    if (res.data && res.data.id) {
      form.size_chart_id = res.data.id;
      suggestedChart.value = res.data;
    } else {
      suggestedChart.value = null;
    }
    await updateAvailableSizes();
  } catch (err) {
    suggestedChart.value = null;
    await updateAvailableSizes();
  }
}

async function onSizeChartSelect() {
  await updateAvailableSizes();
}

async function updateAvailableSizes() {
  if (form.size_chart_id) {
    try {
      const res = await api.get(`/size-charts/${form.size_chart_id}`);
      const chart = res.data;
      if (chart && chart.rows && chart.rows.length > 0) {
        activeChartName.value = chart.name;
        const extracted = chart.rows.map((r) => {
          const val = r.size_value || (r.values ? Object.values(r.values)[0] : null) || '';
          const szObj = sizesMaster.value.find(s => String(s.size_number) === String(val));
          return {
            id: szObj ? szObj.id : r.size_id || r.id,
            size_number: String(val),
          };
        }).filter(s => s.size_number !== '');

        availableSizes.value = extracted;
      } else {
        fallbackToMasterSizes();
      }
    } catch (err) {
      fallbackToMasterSizes();
    }
  } else {
    fallbackToMasterSizes();
  }

  // Select all sizes by default
  selectedSizes.value = availableSizes.value.map(s => s.size_number);
  syncColorBlocksWithSelectedSizes();
}

function fallbackToMasterSizes() {
  activeChartName.value = '';
  availableSizes.value = sizesMaster.value.map(s => ({
    id: s.id,
    size_number: String(s.size_number),
  }));
}

function onSelectedSizesChange() {
  syncColorBlocksWithSelectedSizes();
}

function syncColorBlocksWithSelectedSizes() {
  if (form.color_blocks.length === 0 && colors.value.length > 0) {
    form.color_blocks.push({
      color_id: colors.value[0].id,
      size_rows: [],
    });
  }

  const selectedObjects = availableSizes.value.filter(s => selectedSizes.value.includes(s.size_number));

  form.color_blocks.forEach(block => {
    const existingMap = new Map(block.size_rows.map(r => [String(r.size_number), r]));
    block.size_rows = selectedObjects.map(sz => {
      const existing = existingMap.get(sz.size_number);
      return {
        size_id: sz.id,
        size_number: sz.size_number,
        mrp: existing ? existing.mrp : form.mrp,
        selling_price: existing ? existing.selling_price : form.selling_price,
        opening_stock: existing ? existing.opening_stock : 10,
      };
    });
  });
}

function addColorBlock() {
  const defaultColor = colors.value[form.color_blocks.length % colors.value.length];
  const selectedObjects = availableSizes.value.filter(s => selectedSizes.value.includes(s.size_number));

  form.color_blocks.push({
    color_id: defaultColor?.id || '',
    size_rows: selectedObjects.map(sz => ({
      size_id: sz.id,
      size_number: sz.size_number,
      mrp: form.mrp,
      selling_price: form.selling_price,
      opening_stock: 10,
    })),
  });
}

function removeColorBlock(index) {
  if (form.color_blocks.length > 1) {
    form.color_blocks.splice(index, 1);
  }
}

function generateSkuPreview(colorId, sizeNumber) {
  const art = (form.article_number || 'RP').replace(/[^A-Za-z0-9]/g, '').toUpperCase();
  const colorObj = colors.value.find(c => c.id === colorId);
  const colCode = (colorObj?.code || 'BLK').toUpperCase();
  const szStr = String(sizeNumber).padStart(2, '0');
  return `${art}-${colCode}-${szStr}`;
}

watch([() => form.mrp, () => form.selling_price], ([newMrp, newSp]) => {
  form.color_blocks.forEach(block => {
    block.size_rows.forEach(row => {
      row.mrp = newMrp;
      row.selling_price = newSp;
    });
  });
});

async function submitProduct() {
  if (fileValidationError.value) {
    toast.error(fileValidationError.value);
    return;
  }

  submitting.value = true;
  try {
    const formData = new FormData();
    formData.append('article_number', form.article_number);
    formData.append('name', form.name);
    formData.append('brand_id', form.brand_id);
    formData.append('category_id', form.category_id);
    formData.append('hsn_code_id', form.hsn_code_id || '');
    formData.append('size_chart_id', form.size_chart_id || '');
    formData.append('gender', form.gender);
    formData.append('upper_material', form.upper_material || '');
    formData.append('sole_material', form.sole_material || '');
    formData.append('description', form.description || '');
    formData.append('cost_price', form.cost_price || 0);
    formData.append('mrp', form.mrp || 0);
    formData.append('selling_price', form.selling_price || 0);
    formData.append('is_active', '1');
    formData.append('is_visible_on_web', '1');

    if (selectedFile.value) {
      formData.append('image', selectedFile.value);
    }

    formData.append('color_blocks', JSON.stringify(form.color_blocks));

    const res = await api.post('/products/bulk-create', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    toast.success(`Product ${form.article_number} (${form.name}) created successfully!`);
    router.push('/admin/products');
  } catch (err) {
    console.error('Failed to create product:', err);
    toast.error(err.message || 'Failed to create product article.');
  } finally {
    submitting.value = false;
  }
}

onMounted(() => {
  fetchMasterData();
});
</script>
