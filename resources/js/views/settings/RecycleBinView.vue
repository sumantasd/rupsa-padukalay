<template>
  <div class="space-y-6 antialiased font-sans pb-12">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <h1 class="text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
            <span>🗑️</span>
            <span>Recycle Bin & Data Recovery</span>
          </h1>
          <span class="px-2.5 py-0.5 text-[10px] font-black uppercase bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300 rounded-full border border-indigo-300 dark:border-indigo-800">
            SYSTEM ARCHIVE
          </span>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-1">
          Inspect soft-deleted records, restore items back into active ERP operations, or safely perform protected permanent deletion.
        </p>
      </div>

      <button
        @click="fetchItems"
        class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl font-bold text-xs transition-all cursor-pointer flex items-center gap-2 shrink-0"
      >
        <span>🔄</span>
        <span>Refresh Archive</span>
      </button>
    </div>

    <!-- Alert Message -->
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

    <!-- Main Container -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-xs overflow-hidden">
      <!-- Entity Tabs & Search Bar -->
      <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- Tabs -->
        <div class="flex items-center gap-1 overflow-x-auto pb-1 md:pb-0">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="switchTab(tab.id)"
            :class="[
              'px-3.5 py-2 rounded-xl text-xs font-black transition-all cursor-pointer flex items-center gap-1.5 whitespace-nowrap',
              activeTab === tab.id
                ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900 shadow-xs'
                : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
            ]"
          >
            <span>{{ tab.icon }}</span>
            <span>{{ tab.name }}</span>
          </button>
        </div>

        <!-- Search Input -->
        <div class="relative w-full md:w-72">
          <input
            type="text"
            v-model="searchQuery"
            @input="onSearch"
            placeholder="Search archived records..."
            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl pl-9 pr-3 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-slate-400"
          />
          <span class="absolute left-3 top-2.5 text-xs text-slate-400">🔍</span>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="p-12 text-center space-y-2">
        <div class="inline-block animate-spin text-2xl text-slate-600 dark:text-slate-400">⌛</div>
        <p class="text-xs font-bold text-slate-500">Retrieving trashed records...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="items.length === 0" class="p-12 text-center space-y-3">
        <div class="text-4xl text-slate-400">🎉</div>
        <div class="text-sm font-black text-slate-800 dark:text-slate-200">No trashed {{ activeTabName }} found</div>
        <p class="text-xs text-slate-500 font-medium">All active {{ activeTabName.toLowerCase() }} records are healthy and active in the system.</p>
      </div>

      <!-- Table View -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs font-sans">
          <thead class="bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 text-[10px] uppercase font-black tracking-wider text-slate-500 dark:text-slate-400">
            <tr>
              <th class="py-3 px-4">Item / Entity Name</th>
              <th class="py-3 px-4">SKU / Code</th>
              <th class="py-3 px-4">Category / Type</th>
              <th v-if="activeTab === 'products'" class="py-3 px-4 text-center">Stock Level</th>
              <th class="py-3 px-4">Details</th>
              <th class="py-3 px-4">Deleted Date</th>
              <th class="py-3 px-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
            <tr v-for="item in items" :key="item.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
              <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white whitespace-nowrap">
                {{ item.name }}
              </td>
              <td class="py-3.5 px-4 font-mono text-[11px] font-bold text-indigo-600 dark:text-indigo-400 whitespace-nowrap">
                {{ item.code_or_sku }}
              </td>
              <td class="py-3.5 px-4 whitespace-nowrap">
                <span class="px-2 py-0.5 text-[10px] font-black rounded-md uppercase bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                  {{ item.category_name }}
                </span>
              </td>
              <td v-if="activeTab === 'products'" class="py-3.5 px-4 text-center font-bold font-mono whitespace-nowrap">
                <span :class="[
                  'px-2 py-0.5 text-[11px] font-black rounded-full',
                  item.current_stock > 0 ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                ]">
                  {{ item.current_stock ?? 0 }} Pair(s)
                </span>
              </td>
              <td class="py-3.5 px-4 text-slate-600 dark:text-slate-400 text-[11px]">
                {{ item.details }}
              </td>
              <td class="py-3.5 px-4 font-bold text-slate-700 dark:text-slate-300 whitespace-nowrap">
                {{ formatDate(item.deleted_at) }}
              </td>
              <td class="py-3.5 px-4 text-right whitespace-nowrap space-x-2">
                <button
                  @click="restoreItem(item)"
                  class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition-all cursor-pointer shadow-xs inline-flex items-center gap-1"
                >
                  <span>↺</span>
                  <span>Restore</span>
                </button>
                <button
                  @click="openPermanentDeleteModal(item)"
                  class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-bold transition-all cursor-pointer shadow-xs inline-flex items-center gap-1"
                >
                  <span>🗑️</span>
                  <span>Permanent Delete</span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="meta.total > meta.per_page" class="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs font-bold">
        <div class="text-slate-500">
          Showing {{ ((meta.current_page - 1) * meta.per_page) + 1 }} to {{ Math.min(meta.current_page * meta.per_page, meta.total) }} of {{ meta.total }} records
        </div>
        <div class="flex items-center gap-2">
          <button
            @click="changePage(meta.current_page - 1)"
            :disabled="meta.current_page <= 1"
            class="px-3 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg disabled:opacity-40 cursor-pointer"
          >
            Previous
          </button>
          <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
          <button
            @click="changePage(meta.current_page + 1)"
            :disabled="meta.current_page >= meta.last_page"
            class="px-3 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg disabled:opacity-40 cursor-pointer"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- PERMANENT DELETE PROTECTION MODAL -->
    <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-xs">
      <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xl w-full max-w-lg overflow-hidden">
        <div class="p-5 bg-red-600 text-white flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="text-xl">🚨</span>
            <span class="font-black text-sm uppercase tracking-wider">Permanent Deletion Protection</span>
          </div>
          <button @click="closeDeleteModal" class="text-white hover:opacity-75 font-bold cursor-pointer">✕</button>
        </div>

        <div class="p-6 space-y-4">
          <div class="p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-600 dark:text-red-400 text-xs font-bold leading-relaxed">
            You are about to permanently delete <strong>"{{ selectedItemForDelete?.name }}"</strong>.
          </div>

          <div class="bg-slate-50 dark:bg-slate-950 p-4 rounded-xl border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
            <div class="font-black text-slate-900 dark:text-white uppercase tracking-wider text-[11px]">Strict Safeguards Active:</div>
            <ul class="list-disc list-inside text-slate-600 dark:text-slate-400 space-y-1 font-medium text-[11px]">
              <li>If any sales invoice, purchase order, stock movement, GRN, return or financial transaction relies on this record, permanent deletion will be <strong>blocked automatically</strong> by the system.</li>
              <li>Foreign key integrity and historical financial analytics will never be compromised.</li>
            </ul>
          </div>

          <div v-if="deleteError" class="p-3.5 bg-red-500/10 border border-red-500/40 text-red-600 text-xs font-extrabold rounded-xl">
            ⚠️ {{ deleteError }}
          </div>
        </div>

        <div class="p-4 bg-slate-50 dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 flex justify-end gap-2">
          <button
            @click="closeDeleteModal"
            class="px-4 py-2 bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold cursor-pointer"
          >
            Cancel
          </button>
          <button
            @click="executePermanentDelete"
            :disabled="deleting"
            class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-black shadow-md flex items-center gap-1.5 disabled:opacity-40 cursor-pointer"
          >
            <span v-if="deleting" class="animate-spin text-sm">⌛</span>
            <span>Permanently Delete</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../services/api';

const activeTab = ref('sales');
const searchQuery = ref('');
const items = ref([]);
const loading = ref(false);
const alertMessage = ref('');
const alertType = ref('success');

const meta = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
});

const tabs = [
  { id: 'sales', name: 'Sales', icon: '🧾' },
  { id: 'sales_returns', name: 'Sale Returns', icon: '↩️' },
  { id: 'sales_exchanges', name: 'Sale Exchanges', icon: '🔄' },
  { id: 'products', name: 'Products', icon: '👟' },
  { id: 'customers', name: 'Customers', icon: '👥' },
  { id: 'suppliers', name: 'Suppliers', icon: '🏭' },
  { id: 'categories', name: 'Categories', icon: '🏷️' },
  { id: 'brands', name: 'Brands', icon: '✨' },
];

const activeTabName = computed(() => {
  const t = tabs.find(x => x.id === activeTab.value);
  return t ? t.name : 'Records';
});

async function fetchItems(page = 1) {
  loading.value = true;
  alertMessage.value = '';
  try {
    const res = await api.get('/recycle-bin', {
      params: {
        type: activeTab.value,
        search: searchQuery.value,
        page: page,
        per_page: meta.value.per_page,
      },
    });
    items.value = res.data?.data || [];
    meta.value = res.data?.meta || { current_page: 1, last_page: 1, per_page: 15, total: items.value.length };
  } catch (err) {
    showAlert(err.response?.data?.message || 'Failed to load recycle bin records.', 'error');
  } finally {
    loading.value = false;
  }
}

function switchTab(tabId) {
  activeTab.value = tabId;
  searchQuery.value = '';
  meta.value.current_page = 1;
  fetchItems(1);
}

function onSearch() {
  meta.value.current_page = 1;
  fetchItems(1);
}

function changePage(page) {
  if (page < 1 || page > meta.value.last_page) return;
  meta.value.current_page = page;
  fetchItems(page);
}

async function restoreItem(item) {
  if (!confirm(`Restore "${item.name}" back to active status?`)) return;
  try {
    const res = await api.post(`/recycle-bin/${activeTab.value}/${item.id}/restore`);
    showAlert(res.message || `"${item.name}" restored successfully.`, 'success');
    fetchItems(meta.value.current_page);
  } catch (err) {
    showAlert(err.response?.data?.message || 'Failed to restore item.', 'error');
  }
}

// Permanent Delete Modal & Logic
const showDeleteModal = ref(false);
const selectedItemForDelete = ref(null);
const deleting = ref(false);
const deleteError = ref('');

function openPermanentDeleteModal(item) {
  selectedItemForDelete.value = item;
  deleteError.value = '';
  showDeleteModal.value = true;
}

function closeDeleteModal() {
  showDeleteModal.value = false;
  selectedItemForDelete.value = null;
  deleteError.value = '';
}

async function executePermanentDelete() {
  if (!selectedItemForDelete.value) return;
  deleting.value = true;
  deleteError.value = '';

  try {
    const res = await api.delete(`/recycle-bin/${activeTab.value}/${selectedItemForDelete.value.id}/force-delete`);
    closeDeleteModal();
    showAlert(res.message || 'Item permanently deleted.', 'success');
    fetchItems(meta.value.current_page);
  } catch (err) {
    deleteError.value = err.response?.data?.message || 'Permanent deletion failed due to dependent records.';
  } finally {
    deleting.value = false;
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
  });
}

onMounted(() => {
  fetchItems();
});
</script>
