<template>
  <div class="space-y-6 antialiased font-sans pb-12">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <h1 class="text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
            <span>🗄️</span>
            <span>Database Management</span>
          </h1>
          <span class="px-2.5 py-0.5 text-[10px] font-black uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 rounded-full border border-emerald-300 dark:border-emerald-800">
            LOCAL DEV ONLY
          </span>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-1">
          Create complete database backups, download SQL snapshots, and safely perform Demo/Business data resets.
        </p>
      </div>

      <button
        @click="createBackup"
        :disabled="creatingBackup"
        class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md flex items-center gap-2 transition-all cursor-pointer disabled:opacity-50 shrink-0"
      >
        <span v-if="creatingBackup" class="animate-spin text-sm">⌛</span>
        <span v-else>💾</span>
        <span>Create Database Backup</span>
      </button>
    </div>

    <!-- Alert / Status Messages -->
    <div v-if="alertMessage" :class="[
      'p-4 rounded-2xl border flex items-center justify-between gap-3 text-xs font-bold transition-all',
      alertType === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400' : 'bg-red-500/10 border-red-500/30 text-red-600 dark:text-red-400'
    ]">
      <div class="flex items-center gap-2">
        <span>{{ alertType === 'success' ? '✅' : '⚠️' }}</span>
        <span>{{ alertMessage }}</span>
      </div>
      <button @click="alertMessage = ''" class="hover:opacity-75 cursor-pointer">✕</button>
    </div>

    <!-- 1. DATABASE BACKUP SECTION -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-xs overflow-hidden">
      <div class="p-5 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-4">
        <div>
          <h2 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
            <span>📦</span>
            <span>Database Backup Snapshots</span>
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Stored securely in non-public storage with timestamped filenames</p>
        </div>

        <button
          @click="fetchBackups"
          class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl font-bold text-xs transition-colors cursor-pointer"
        >
          🔄 Refresh History
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loadingBackups" class="p-8 text-center space-y-2">
        <div class="inline-block animate-spin text-xl text-red-600">⌛</div>
        <p class="text-xs font-bold text-slate-500">Loading backup history...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="backups.length === 0" class="p-8 text-center space-y-2">
        <div class="text-3xl text-slate-400">📁</div>
        <div class="text-xs font-black text-slate-700 dark:text-slate-300">No database backups found</div>
        <p class="text-xs text-slate-400 font-medium">Click "Create Database Backup" above to generate your first SQL dump snapshot.</p>
      </div>

      <!-- Backups Table -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs font-sans">
          <thead class="bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 text-[10px] uppercase font-black tracking-wider text-slate-500 dark:text-slate-400">
            <tr>
              <th class="py-3 px-4">Date & Time</th>
              <th class="py-3 px-4">Filename</th>
              <th class="py-3 px-4">Type</th>
              <th class="py-3 px-4 text-right">Size</th>
              <th class="py-3 px-4">Created By</th>
              <th class="py-3 px-4 text-center">Status</th>
              <th class="py-3 px-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
            <tr v-for="item in backups" :key="item.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
              <td class="py-3 px-4 font-bold text-slate-900 dark:text-white whitespace-nowrap">
                {{ formatDate(item.created_at) }}
              </td>
              <td class="py-3 px-4 font-mono text-[11px] font-bold text-indigo-600 dark:text-indigo-400">
                {{ item.filename }}
              </td>
              <td class="py-3 px-4 whitespace-nowrap">
                <span :class="[
                  'px-2 py-0.5 text-[10px] font-black rounded-md uppercase',
                  item.type === 'auto_pre_reset' ? 'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                ]">
                  {{ item.type === 'auto_pre_reset' ? 'Pre-Reset Auto' : 'Manual' }}
                </span>
              </td>
              <td class="py-3 px-4 text-right font-mono font-bold text-slate-700 dark:text-slate-300 whitespace-nowrap">
                {{ formatBytes(item.file_size) }}
              </td>
              <td class="py-3 px-4 font-bold text-slate-700 dark:text-slate-300 whitespace-nowrap">
                {{ item.creator?.name || 'System / Admin' }}
              </td>
              <td class="py-3 px-4 text-center whitespace-nowrap">
                <span :class="[
                  'px-2 py-0.5 text-[10px] font-black rounded-md uppercase',
                  item.status === 'completed' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300'
                ]">
                  {{ item.status }}
                </span>
              </td>
              <td class="py-3 px-4 text-right whitespace-nowrap space-x-2">
                <button
                  @click="downloadBackupFile(item)"
                  class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-[11px] font-bold transition-colors cursor-pointer"
                  title="Download Backup SQL File"
                >
                  ⬇️ Download
                </button>
                <button
                  @click="deleteBackupFile(item)"
                  class="px-2.5 py-1 bg-slate-200 dark:bg-slate-800 hover:bg-red-600 hover:text-white text-slate-700 dark:text-slate-300 rounded-lg text-[11px] font-bold transition-colors cursor-pointer"
                  title="Delete Backup File"
                >
                  🗑️
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 2. DATABASE RESET DANGER SECTION -->
    <div class="bg-red-500/5 dark:bg-red-950/20 rounded-2xl border border-red-500/30 shadow-xs p-6 space-y-5">
      <div class="flex items-start gap-3">
        <div class="p-2.5 bg-red-600 text-white rounded-xl text-lg font-bold">⚠️</div>
        <div>
          <h2 class="text-base font-black text-red-600 dark:text-red-400 uppercase tracking-wider">
            Database Reset & Data Clearing
          </h2>
          <p class="text-xs text-slate-600 dark:text-slate-400 font-medium mt-0.5">
            Safely reset demo or operational business data while preserving protected system users, roles, stores, and configuration settings.
          </p>
        </div>
      </div>

      <!-- Presets Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Demo Data Reset Preset -->
        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 space-y-3 shadow-xs">
          <div class="flex items-center justify-between">
            <h3 class="font-black text-sm text-slate-900 dark:text-white flex items-center gap-1.5">
              <span>🧹</span>
              <span>Demo Data Reset</span>
            </h3>
            <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 bg-amber-100 text-amber-800 rounded">Recommended for Staging</span>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400 font-medium leading-relaxed">
            Clears sample operational transactions (sales, purchases, inventory stock balances, stock movements, expenses) while keeping your product catalog, categories, brands, customers, suppliers, and system configuration intact.
          </p>
          <button
            @click="openResetModal('demo_data_reset')"
            class="w-full py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-black transition-colors shadow-sm cursor-pointer"
          >
            Reset Demo Transactions Only
          </button>
        </div>

        <!-- Business Data Reset Preset -->
        <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200 dark:border-slate-800 space-y-3 shadow-xs">
          <div class="flex items-center justify-between">
            <h3 class="font-black text-sm text-slate-900 dark:text-white flex items-center gap-1.5">
              <span>⚡</span>
              <span>Full Business Data Reset</span>
            </h3>
            <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 bg-red-100 text-red-800 rounded">Destructive</span>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400 font-medium leading-relaxed">
            Clears all operational transactions AND product/inventory catalog data. Admin/staff accounts, retail store configs, tax settings, size charts, and audit trails are strictly protected.
          </p>
          <button
            @click="openResetModal('business_data_reset')"
            class="w-full py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-black transition-colors shadow-sm cursor-pointer"
          >
            Reset All Business & Product Data
          </button>
        </div>
      </div>

      <!-- Protected Tables Summary Banner -->
      <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 text-xs space-y-2">
        <div class="font-black text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
          <span>🛡️</span>
          <span>Protected Tables (Never Deleted During Reset):</span>
        </div>
        <div class="flex flex-wrap gap-1.5 text-[11px] font-mono font-bold text-slate-600 dark:text-slate-400">
          <span v-for="tbl in protectedTables" :key="tbl" class="px-2 py-0.5 bg-slate-100 dark:bg-slate-800 rounded border border-slate-200 dark:border-slate-700">
            {{ tbl }}
          </span>
        </div>
      </div>
    </div>

    <!-- 3. RESET CONFIRMATION MODAL WORKFLOW -->
    <div v-if="showResetModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-xs">
      <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xl w-full max-w-xl overflow-hidden space-y-0">
        <!-- Modal Header -->
        <div class="p-5 bg-red-600 text-white flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="text-xl">⚠️</span>
            <span class="font-black text-sm uppercase tracking-wider">Confirm Database Reset</span>
          </div>
          <button @click="closeResetModal" class="text-white hover:opacity-75 font-bold cursor-pointer">✕</button>
        </div>

        <!-- Modal Body -->
        <div class="p-6 space-y-5 max-h-[75vh] overflow-y-auto">
          <!-- Step 1: Automated Backup Notice -->
          <div class="p-3.5 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-emerald-600 dark:text-emerald-400 text-xs font-bold space-y-1">
            <div class="flex items-center gap-1.5">
              <span>💾</span>
              <span>Automated Pre-Reset Backup Protection Enabled</span>
            </div>
            <p class="text-[11px] font-normal text-slate-600 dark:text-slate-400">
              An automatic database SQL backup will be created and verified before any data deletion begins. If backup fails, reset will automatically abort.
            </p>
          </div>

          <!-- Step 2: Select Categories -->
          <div class="space-y-2">
            <label class="block text-xs font-black text-slate-900 dark:text-white uppercase">Selected Reset Categories *</label>
            <div class="grid grid-cols-1 gap-2 max-h-48 overflow-y-auto p-2 border border-slate-200 dark:border-slate-800 rounded-xl bg-slate-50 dark:bg-slate-950">
              <label v-for="cat in availableCategories" :key="cat.id" class="flex items-start gap-2.5 p-2 rounded-lg hover:bg-white dark:hover:bg-slate-900 cursor-pointer">
                <input
                  type="checkbox"
                  :value="cat.id"
                  v-model="selectedCategoryIds"
                  class="mt-0.5 accent-red-600 rounded"
                />
                <div>
                  <div class="text-xs font-bold text-slate-900 dark:text-white">{{ cat.name }}</div>
                  <div class="text-[10px] text-slate-500 font-medium">{{ cat.description }}</div>
                </div>
              </label>
            </div>
          </div>

          <!-- Step 3: Required Confirmation Phrase Input -->
          <div class="space-y-2">
            <label class="block text-xs font-black text-slate-900 dark:text-white">
              To proceed, please type <span class="text-red-600 font-mono font-black">CLEAR DATABASE</span> below:
            </label>
            <input
              type="text"
              v-model="confirmationInput"
              placeholder="CLEAR DATABASE"
              class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-xs font-mono font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-600"
            />
          </div>

          <!-- Error Feedback inside modal -->
          <div v-if="modalError" class="p-3 bg-red-500/10 border border-red-500/30 text-red-600 text-xs font-bold rounded-xl">
            {{ modalError }}
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="p-4 bg-slate-50 dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 flex justify-end gap-2">
          <button
            @click="closeResetModal"
            class="px-4 py-2 bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold cursor-pointer"
          >
            Cancel
          </button>
          <button
            @click="submitReset"
            :disabled="executingReset || confirmationInput.trim() !== 'CLEAR DATABASE' || selectedCategoryIds.length === 0"
            class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-black shadow-md flex items-center gap-1.5 disabled:opacity-40 cursor-pointer"
          >
            <span v-if="executingReset" class="animate-spin text-sm">⌛</span>
            <span>Execute Database Reset</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../services/api';

const backups = ref([]);
const loadingBackups = ref(false);
const creatingBackup = ref(false);

const alertMessage = ref('');
const alertType = ref('success');

const protectedTables = ref([]);
const availableCategories = ref([]);

// Reset Modal State
const showResetModal = ref(false);
const activeResetType = ref('demo_data_reset');
const selectedCategoryIds = ref([]);
const confirmationInput = ref('');
const executingReset = ref(false);
const modalError = ref('');

async function fetchBackups() {
  loadingBackups.value = true;
  try {
    const res = await api.get('/database/backups');
    backups.value = res.data || [];
  } catch (err) {
    showAlert(err.response?.data?.message || 'Failed to load database backups.', 'error');
  } finally {
    loadingBackups.value = false;
  }
}

async function fetchResetInfo() {
  try {
    const res = await api.get('/database/reset/categories');
    protectedTables.value = res.data?.protected_tables || [];
    availableCategories.value = res.data?.categories || [];
  } catch (err) {
    console.error('Failed to fetch reset categories:', err);
  }
}

async function createBackup() {
  creatingBackup.value = true;
  alertMessage.value = '';
  try {
    const res = await api.post('/database/backups');
    showAlert(res.message || 'Database backup created successfully.', 'success');
    fetchBackups();
  } catch (err) {
    showAlert(err.response?.data?.message || 'Failed to create database backup.', 'error');
  } finally {
    creatingBackup.value = false;
  }
}

async function downloadBackupFile(item) {
  try {
    const response = await api.get(`/database/backups/${item.id}/download`, { responseType: 'blob' });
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', item.filename);
    document.body.appendChild(link);
    link.click();
    link.remove();
    showAlert(`Download started for ${item.filename}`, 'success');
  } catch (err) {
    showAlert('Failed to download backup file.', 'error');
  }
}

async function deleteBackupFile(item) {
  if (!confirm(`Are you sure you want to delete backup file "${item.filename}"?`)) return;
  try {
    await api.delete(`/database/backups/${item.id}`);
    showAlert('Backup file deleted successfully.', 'success');
    fetchBackups();
  } catch (err) {
    showAlert(err.response?.data?.message || 'Failed to delete backup file.', 'error');
  }
}

function openResetModal(presetType) {
  activeResetType.value = presetType;
  confirmationInput.value = '';
  modalError.value = '';

  if (presetType === 'demo_data_reset') {
    selectedCategoryIds.value = ['sales', 'sales_returns', 'purchases', 'inventory', 'expenses', 'customer_loyalty'];
  } else if (presetType === 'business_data_reset') {
    selectedCategoryIds.value = ['sales', 'sales_returns', 'purchases', 'inventory', 'expenses', 'customer_loyalty', 'customers', 'suppliers', 'products'];
  } else {
    selectedCategoryIds.value = [];
  }

  showResetModal.value = true;
}

function closeResetModal() {
  showResetModal.value = false;
  confirmationInput.value = '';
  modalError.value = '';
}

async function submitReset() {
  if (confirmationInput.value.trim() !== 'CLEAR DATABASE') return;
  executingReset.value = true;
  modalError.value = '';

  try {
    const res = await api.post('/database/reset', {
      reset_type: activeResetType.value,
      categories: selectedCategoryIds.value,
      confirmation_text: confirmationInput.value.trim(),
    });

    closeResetModal();
    showAlert(res.message || 'Database reset completed successfully.', 'success');
    fetchBackups();
  } catch (err) {
    modalError.value = err.response?.data?.message || 'Database reset failed.';
  } finally {
    executingReset.value = false;
  }
}

function showAlert(msg, type = 'success') {
  alertMessage.value = msg;
  alertType.value = type;
}

function formatDate(dateStr) {
  if (!dateStr) return 'N/A';
  return new Date(dateStr).toLocaleString('en-IN', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
}

function formatBytes(bytes) {
  if (!bytes || bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

onMounted(() => {
  fetchBackups();
  fetchResetInfo();
});
</script>
