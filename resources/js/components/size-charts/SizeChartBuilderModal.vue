<template>
  <div v-if="isOpen" class="fixed inset-0 z-[500] flex items-center justify-center p-3 sm:p-6 overflow-y-auto bg-slate-900/60 backdrop-blur-xs">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl w-full max-w-5xl my-auto flex flex-col max-h-[92vh] overflow-hidden transition-all transform antialiased font-sans">
      
      <!-- Modal Header -->
      <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800 shrink-0">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-2xl bg-red-600/20 border border-red-500/30 flex items-center justify-center text-red-500 font-bold text-lg">
            ⚙️
          </div>
          <div>
            <h2 class="text-base sm:text-lg font-black text-white tracking-tight">
              {{ chartId ? 'Edit Size Chart' : 'Create Size Chart' }}
            </h2>
            <p class="text-xs text-slate-400 font-medium">
              Configure dynamic footwear size systems, columns & measurement rows
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <!-- View Toggle: Builder / Preview -->
          <div class="bg-slate-800 p-1 rounded-xl flex items-center gap-1 border border-slate-700 text-xs font-bold">
            <button
              @click="activeTab = 'builder'"
              :class="activeTab === 'builder' ? 'bg-red-600 text-white shadow-xs' : 'text-slate-400 hover:text-white'"
              class="px-3 py-1.5 rounded-lg transition-all"
            >
              🛠️ Builder
            </button>
            <button
              @click="activeTab = 'preview'"
              :class="activeTab === 'preview' ? 'bg-red-600 text-white shadow-xs' : 'text-slate-400 hover:text-white'"
              class="px-3 py-1.5 rounded-lg transition-all"
            >
              👁️ Preview
            </button>
          </div>

          <button
            @click="closeModal"
            class="w-8 h-8 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition-colors text-sm font-bold ml-2"
          >
            ✕
          </button>
        </div>
      </div>

      <!-- Modal Body -->
      <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-6">
        
        <!-- Tab 1: Builder -->
        <div v-if="activeTab === 'builder'" class="space-y-6">
          
          <!-- Metadata Fields -->
          <div class="bg-slate-50 p-4 sm:p-5 rounded-2xl border border-slate-200/80 space-y-4">
            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
              <span>📌 Chart Information</span>
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <div class="sm:col-span-2">
                <label class="block text-xs font-black text-slate-700 mb-1">Chart Name *</label>
                <input
                  v-model="form.name"
                  type="text"
                  placeholder="e.g. Men's Footwear Size Chart"
                  class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:ring-2 focus:ring-red-600 focus:outline-none"
                />
              </div>

              <div>
                <label class="block text-xs font-black text-slate-700 mb-1">Applicable Category</label>
                <select
                  v-model="form.category_id"
                  class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:ring-2 focus:ring-red-600 focus:outline-none"
                >
                  <option :value="null">All Categories (Global)</option>
                  <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                    {{ cat.name }}
                  </option>
                </select>
              </div>

              <div>
                <label class="block text-xs font-black text-slate-700 mb-1">Gender</label>
                <select
                  v-model="form.gender"
                  class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:ring-2 focus:ring-red-600 focus:outline-none"
                >
                  <option value="unisex">Unisex</option>
                  <option value="men">Men</option>
                  <option value="women">Women</option>
                  <option value="kids">Kids</option>
                </select>
              </div>

              <div>
                <label class="block text-xs font-black text-slate-700 mb-1">Age Group</label>
                <input
                  v-model="form.age_group"
                  type="text"
                  placeholder="e.g. Adult / Junior"
                  class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:ring-2 focus:ring-red-600 focus:outline-none"
                />
              </div>

              <div>
                <label class="block text-xs font-black text-slate-700 mb-1">Measurement Unit</label>
                <input
                  v-model="form.unit"
                  type="text"
                  placeholder="e.g. CM / Inches"
                  class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:ring-2 focus:ring-red-600 focus:outline-none"
                />
              </div>

              <div class="flex items-center gap-6 pt-5">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                  <input v-model="form.is_default" type="checkbox" class="w-4 h-4 text-red-600 rounded focus:ring-red-500" />
                  <span class="text-xs font-black text-slate-800">Set as Default Chart</span>
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                  <input v-model="form.is_active" type="checkbox" class="w-4 h-4 text-red-600 rounded focus:ring-red-500" />
                  <span class="text-xs font-black text-slate-800">Active</span>
                </label>
              </div>
            </div>

            <div>
              <label class="block text-xs font-black text-slate-700 mb-1">Description / Notes</label>
              <textarea
                v-model="form.description"
                rows="2"
                placeholder="Optional notes or fit guidance (e.g. Standard Indian Sizing chart)..."
                class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-medium text-slate-900 focus:ring-2 focus:ring-red-600 focus:outline-none"
              ></textarea>
            </div>
          </div>

          <!-- Column Configuration -->
          <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-xs space-y-3">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                  <span>📐 Columns Management</span>
                </h3>
                <p class="text-[11px] text-slate-500 font-medium">
                  Add custom columns (e.g. Size, UK, US, EU, Foot Length CM)
                </p>
              </div>
              <button
                @click="addColumn"
                type="button"
                class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-black transition-all flex items-center gap-1.5 shadow-xs"
              >
                <span>➕</span>
                <span>Add Column</span>
              </button>
            </div>

            <!-- Columns Tags / List -->
            <div class="flex flex-wrap items-center gap-2 pt-2">
              <div
                v-for="(col, index) in form.columns"
                :key="index"
                class="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs shadow-2xs"
              >
                <span class="text-[10px] font-black text-slate-400">#{{ index + 1 }}</span>
                <input
                  v-model="col.name"
                  type="text"
                  placeholder="Column Name"
                  class="bg-white border border-slate-200 rounded-lg px-2 py-1 text-xs font-bold text-slate-900 w-32 focus:ring-2 focus:ring-red-600 focus:outline-none"
                />
                <select
                  v-model="col.data_type"
                  class="bg-white border border-slate-200 rounded-lg px-1.5 py-1 text-[11px] font-bold text-slate-700"
                >
                  <option value="text">Text</option>
                  <option value="number">Number</option>
                </select>
                <button
                  v-if="form.columns.length > 1"
                  @click="removeColumn(index)"
                  type="button"
                  class="w-6 h-6 rounded-md hover:bg-red-50 text-slate-400 hover:text-red-600 flex items-center justify-center font-bold text-xs transition-colors"
                >
                  ✕
                </button>
              </div>
            </div>
          </div>

          <!-- Dynamic Spreadsheet Matrix -->
          <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                  <span>📊 Sizing Matrix Rows</span>
                </h3>
                <p class="text-[11px] text-slate-500 font-medium">
                  Enter size conversions row by row
                </p>
              </div>
              <button
                @click="addRow"
                type="button"
                class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-black transition-all flex items-center gap-1.5 shadow-md shadow-red-600/20"
              >
                <span>➕</span>
                <span>Add Row</span>
              </button>
            </div>

            <!-- Spreadsheet Table -->
            <div class="border border-slate-200 rounded-xl overflow-x-auto">
              <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-slate-100 text-slate-700 font-black uppercase text-[10px] tracking-wider border-b border-slate-200">
                  <tr>
                    <th class="p-2.5 w-10 text-center">#</th>
                    <th v-for="(col, cIdx) in form.columns" :key="cIdx" class="p-2.5 min-w-[120px] border-r border-slate-200">
                      {{ col.name || ('Col ' + (cIdx + 1)) }}
                    </th>
                    <th class="p-2.5 w-16 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                  <tr v-if="form.rows.length === 0">
                    <td :colspan="form.columns.length + 2" class="p-6 text-center text-slate-400">
                      No size rows added yet. Click "+ Add Row" to add sizes.
                    </td>
                  </tr>
                  <tr v-for="(row, rIdx) in form.rows" :key="rIdx" class="hover:bg-slate-50/80 transition-colors">
                    <td class="p-2 text-center text-slate-400 font-mono font-bold text-[11px]">
                      {{ rIdx + 1 }}
                    </td>
                    <td v-for="(col, cIdx) in form.columns" :key="cIdx" class="p-1.5 border-r border-slate-100">
                      <input
                        v-model="row.values[cIdx]"
                        :type="col.data_type === 'number' ? 'number' : 'text'"
                        step="any"
                        placeholder="Val"
                        class="w-full bg-slate-50 focus:bg-white border border-slate-200 focus:border-red-600 rounded-lg px-2.5 py-1.5 text-xs font-bold text-slate-900 focus:outline-none"
                      />
                    </td>
                    <td class="p-2 text-right">
                      <button
                        @click="removeRow(rIdx)"
                        type="button"
                        class="px-2 py-1 bg-slate-100 hover:bg-red-50 text-slate-500 hover:text-red-600 rounded-md text-[11px] font-bold transition-colors"
                      >
                        Delete
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

        </div>

        <!-- Tab 2: Live Preview -->
        <div v-else-if="activeTab === 'preview'" class="space-y-4">
          <div class="bg-slate-50 p-6 rounded-3xl border border-slate-200 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-200 pb-4">
              <div>
                <span class="px-2.5 py-1 rounded-md bg-red-100 text-red-700 font-extrabold text-[10px] uppercase border border-red-200">
                  {{ form.gender || 'Unisex' }} • {{ form.unit || 'CM' }}
                </span>
                <h3 class="text-lg font-black text-slate-900 mt-1">
                  {{ form.name || 'Footwear Size Chart Preview' }}
                </h3>
                <p class="text-xs text-slate-500 font-medium" v-if="form.description">
                  {{ form.description }}
                </p>
              </div>
              <div class="text-right">
                <span class="text-xs font-black text-slate-400 block">TOTAL SIZES</span>
                <span class="text-xl font-black text-slate-900">{{ form.rows.length }}</span>
              </div>
            </div>

            <!-- Preview Table -->
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
              <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-slate-900 text-white font-black text-xs uppercase tracking-wider">
                  <tr>
                    <th v-for="(col, cIdx) in form.columns" :key="cIdx" class="p-3 border-r border-slate-800">
                      {{ col.name || ('Column ' + (cIdx + 1)) }}
                    </th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                  <tr v-for="(row, rIdx) in form.rows" :key="rIdx" class="hover:bg-slate-50">
                    <td v-for="(col, cIdx) in form.columns" :key="cIdx" class="p-3 font-bold border-r border-slate-100">
                      {{ row.values[cIdx] || '-' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>

      <!-- Modal Footer -->
      <div class="px-6 py-4 bg-slate-50 border-t border-slate-200/90 flex items-center justify-between shrink-0">
        <div class="text-xs text-slate-500 font-bold">
          {{ form.columns.length }} Column(s) • {{ form.rows.length }} Row(s)
        </div>

        <div class="flex items-center gap-3">
          <button
            @click="closeModal"
            type="button"
            class="px-4 py-2.5 bg-slate-200 hover:bg-slate-300 text-slate-800 rounded-xl text-xs font-bold transition-all"
          >
            Cancel
          </button>
          <button
            @click="saveChart"
            :disabled="saving"
            type="button"
            class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-black shadow-md shadow-red-600/20 transition-all flex items-center gap-2 disabled:opacity-50"
          >
            <span v-if="saving" class="animate-spin">⏳</span>
            <span>{{ saving ? 'Saving...' : (chartId ? 'Save Changes' : 'Create Chart') }}</span>
          </button>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, reactive, watch } from 'vue';
import api from '../../services/api';
import { useToast } from '../../composables/useToast';

const props = defineProps({
  isOpen: Boolean,
  chartId: Number,
  categories: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['close', 'saved']);
const toast = useToast();

const activeTab = ref('builder');
const saving = ref(false);

const form = reactive({
  name: '',
  description: '',
  category_id: null,
  gender: 'unisex',
  age_group: 'Adult',
  unit: 'CM',
  is_default: false,
  is_active: true,
  columns: [
    { name: 'Size', data_type: 'text' },
    { name: 'UK', data_type: 'text' },
    { name: 'US', data_type: 'text' },
    { name: 'EU', data_type: 'text' },
    { name: 'Foot Length CM', data_type: 'number' },
  ],
  rows: [],
});

watch(
  () => props.isOpen,
  (newVal) => {
    if (newVal) {
      activeTab.value = 'builder';
      if (props.chartId) {
        fetchChartDetails(props.chartId);
      } else {
        resetForm();
      }
    }
  }
);

async function resetForm() {
  form.name = '';
  form.description = '';
  form.category_id = null;
  form.gender = 'unisex';
  form.age_group = 'Adult';
  form.unit = 'CM';
  form.is_default = false;
  form.is_active = true;
  form.columns = [
    { name: 'Size', data_type: 'text' },
    { name: 'UK', data_type: 'text' },
    { name: 'US', data_type: 'text' },
    { name: 'EU', data_type: 'text' },
    { name: 'Foot Length CM', data_type: 'number' },
  ];
  
  // Try to pre-populate rows from existing Size Master database entries if available
  try {
    const res = await api.get('/sizes');
    const dbSizes = res.data || [];
    if (dbSizes.length > 0) {
      form.rows = dbSizes.map((s) => ({
        values: [s.size_number, s.size_number, String(Number(s.size_number) + 1 || s.size_number), String(Number(s.size_number) + 34 || s.size_number), ''],
      }));
    } else {
      form.rows = [
        { values: ['6', '6', '7', '40', '25.0'] },
        { values: ['7', '7', '8', '41', '26.0'] },
        { values: ['8', '8', '9', '42', '27.0'] },
        { values: ['9', '9', '10', '43', '28.0'] },
        { values: ['10', '10', '11', '44', '29.0'] },
      ];
    }
  } catch (err) {
    form.rows = [
      { values: ['6', '6', '7', '40', '25.0'] },
      { values: ['7', '7', '8', '41', '26.0'] },
      { values: ['8', '8', '9', '42', '27.0'] },
      { values: ['9', '9', '10', '43', '28.0'] },
      { values: ['10', '10', '11', '44', '29.0'] },
    ];
  }
}

async function fetchChartDetails(id) {
  try {
    const res = await api.get(`/size-charts/${id}`);
    if (res.data) {
      const data = res.data;
      form.name = data.name;
      form.description = data.description || '';
      form.category_id = data.category_id;
      form.gender = data.gender || 'unisex';
      form.age_group = data.age_group || 'Adult';
      form.unit = data.unit || 'CM';
      form.is_default = data.is_default;
      form.is_active = data.is_active;

      form.columns = data.columns.map((c) => ({
        id: c.id,
        name: c.name,
        data_type: c.data_type || 'text',
      }));

      form.rows = data.rows.map((r) => {
        const rowVals = data.columns.map((col) => r.values[col.id] || '');
        return { values: rowVals };
      });
    }
  } catch (err) {
    console.error('Failed to load size chart details:', err);
    toast.error(err.message || 'Failed to load chart details.');
  }
}

function addColumn() {
  form.columns.push({ name: 'New Col', data_type: 'text' });
  form.rows.forEach((r) => r.values.push(''));
}

function removeColumn(index) {
  if (form.columns.length <= 1) return;
  form.columns.splice(index, 1);
  form.rows.forEach((r) => r.values.splice(index, 1));
}

function addRow() {
  const newVals = form.columns.map(() => '');
  form.rows.push({ values: newVals });
}

function removeRow(index) {
  form.rows.splice(index, 1);
}

function closeModal() {
  emit('close');
}

async function saveChart() {
  if (!form.name.trim()) {
    toast.error('Please enter a size chart name.');
    return;
  }
  if (form.columns.length === 0) {
    toast.error('Please add at least one column.');
    return;
  }

  saving.value = true;
  try {
    const payload = {
      name: form.name,
      description: form.description,
      category_id: form.category_id,
      gender: form.gender,
      age_group: form.age_group,
      unit: form.unit,
      is_default: form.is_default,
      is_active: form.is_active,
      columns: form.columns.map((col) => ({
        name: col.name,
        data_type: col.data_type,
      })),
      rows: form.rows.map((row) => ({
        values: row.values,
      })),
    };

    let res;
    if (props.chartId) {
      res = await api.put(`/size-charts/${props.chartId}`, payload);
    } else {
      res = await api.post('/size-charts', payload);
    }

    toast.success(res.message || (props.chartId ? 'Size chart updated.' : 'Size chart created.'));
    emit('saved', res.data);
    closeModal();
  } catch (err) {
    toast.error(err.message || 'Failed to save size chart.');
  } finally {
    saving.value = false;
  }
}
</script>
