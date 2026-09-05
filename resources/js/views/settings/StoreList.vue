<template>
  <div class="space-y-6">
    <!-- Header Block -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
      <div>
        <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100">Store Master Management</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          Manage multi-store retail branches, location details & user store assignments
        </p>
      </div>
      <Button v-if="hasPermission('users.manage') && moduleStore.allowNewStoreCreation" variant="primary" size="md" @click="openCreateModal">
        + Add New Store
      </Button>
    </div>

    <!-- Search & Filters -->
    <SearchFilter v-model="search" placeholder="Search by store name, code, or city...">
      <label class="flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300 cursor-pointer select-none">
        <input
          type="checkbox"
          v-model="activeOnly"
          @change="fetchStores"
          class="h-4 w-4 rounded border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500"
        />
        Active Only
      </label>
    </SearchFilter>

    <!-- Error Alert -->
    <ErrorAlert v-if="error" :message="error" class="mb-4" />

    <!-- Stores DataTable -->
    <DataTable :columns="columns" :items="stores" :loading="loading">
      <template #code="{ item }">
        <span class="font-mono font-bold text-xs bg-slate-100 dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 px-2 py-1 rounded">
          {{ item.code }}
        </span>
      </template>

      <template #name="{ item }">
        <div>
          <div class="font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
            <span>{{ item.name }}</span>
            <span v-if="item.code === 'STR-001' || item.code === 'ST-001'" class="px-2 py-0.5 rounded-md bg-red-100 text-red-800 text-[10px] font-black uppercase tracking-wider border border-red-200">
              ★ Main Outlet / Default
            </span>
          </div>
          <div class="text-[10px] text-slate-500 dark:text-slate-400">{{ item.email || 'contact@rupsapadukalaya.com' }} | Phone: {{ item.phone || '+91 9735125112' }}</div>
        </div>
      </template>

      <template #location="{ item }">
        <div class="text-xs">
          <div class="font-semibold text-slate-800 dark:text-slate-200">{{ item.city || 'N/A' }}</div>
          <div class="text-[10px] text-slate-500 dark:text-slate-400 truncate max-w-xs">{{ item.address || '-' }}</div>
        </div>
      </template>

      <template #users="{ item }">
        <div class="flex items-center gap-1">
          <span class="text-xs font-bold text-slate-700 dark:text-slate-300">
            {{ item.users?.length || 0 }} Users
          </span>
          <Button
            v-if="hasPermission('users.manage')"
            variant="ghost"
            size="sm"
            class="!p-1 text-xs"
            title="Assign Users"
            @click="openUsersModal(item)"
          >
            👥
          </Button>
        </div>
      </template>

      <template #is_active="{ item }">
        <Badge :variant="item.is_active ? 'success' : 'neutral'">
          {{ item.is_active ? 'Active' : 'Inactive' }}
        </Badge>
      </template>

      <template #actions="{ item }">
        <div class="flex items-center gap-2">
          <Button variant="ghost" size="sm" @click="goToPerformance(item)" title="View Store Performance">
            📊 Performance
          </Button>
          <template v-if="hasPermission('users.manage')">
            <Button variant="outline" size="sm" @click="openEditModal(item)">
              Edit
            </Button>
            <Button
              :variant="item.is_active ? 'secondary' : 'primary'"
              size="sm"
              @click="toggleStatus(item)"
            >
              {{ item.is_active ? 'Deactivate' : 'Activate' }}
            </Button>
            <Button
              v-if="item.code !== 'STR-001' && item.code !== 'ST-001' && item.id !== 1"
              variant="danger"
              size="sm"
              @click="confirmDeleteStore(item)"
            >
              Delete
            </Button>
          </template>
        </div>
      </template>
    </DataTable>

    <!-- Modals -->
    <StoreFormModal
      :show="showFormModal"
      :store="selectedStore"
      @close="showFormModal = false"
      @saved="fetchStores"
    />

    <StoreUsersModal
      :show="showUsersModal"
      :store="selectedStore"
      @close="showUsersModal = false"
      @saved="fetchStores"
    />

    <!-- Store Delete Confirmation Modal -->
    <Modal :show="showDeleteModal" title="Delete Store Confirmation" maxWidth="md" @close="showDeleteModal = false">
      <div class="space-y-4 text-xs font-sans">
        <div class="p-4 bg-red-50 border border-red-200 rounded-2xl flex items-start gap-3">
          <span class="text-xl shrink-0">⚠️</span>
          <div class="space-y-1">
            <h4 class="font-black text-red-900 text-sm">Delete Store "{{ storeToDelete?.name }}"?</h4>
            <p class="text-xs font-mono text-red-700">
              Code: <strong>{{ storeToDelete?.code }}</strong> | City: <strong>{{ storeToDelete?.city || 'N/A' }}</strong>
            </p>
            <p class="text-[11px] text-red-600 font-medium pt-1">
              Are you sure you want to delete this store? This action cannot be undone. Stores with existing inventory or sales records will be protected from deletion.
            </p>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
          <Button variant="outline" size="sm" @click="showDeleteModal = false">
            Cancel
          </Button>
          <Button variant="danger" size="sm" :loading="deletingStore" @click="handleDeleteStore">
            Delete Store
          </Button>
        </div>
      </div>
    </Modal>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import DataTable from '../../components/ui/DataTable.vue';
import Button from '../../components/ui/Button.vue';
import Badge from '../../components/ui/Badge.vue';
import SearchFilter from '../../components/ui/SearchFilter.vue';
import ErrorAlert from '../../components/ui/ErrorAlert.vue';
import Modal from '../../components/ui/Modal.vue';
import StoreFormModal from './StoreFormModal.vue';
import StoreUsersModal from './StoreUsersModal.vue';
import api from '../../services/api';
import { useRouter } from 'vue-router';
import { useAuth } from '../../composables/useAuth';
import { useToast } from '../../composables/useToast';
import { useStoreAccessStore } from '../../stores/storeAccessStore';
import { useModuleStore } from '../../stores/moduleStore';

const router = useRouter();
const { hasPermission } = useAuth();
const toast = useToast();
const storeAccessStore = useStoreAccessStore();
const moduleStore = useModuleStore();

function goToPerformance(store) {
  router.push({ name: 'stores-performance', query: { store_id: store.id } });
}

const stores = ref([]);
const loading = ref(false);
const error = ref('');
const search = ref('');
const activeOnly = ref(true);

const showFormModal = ref(false);
const showUsersModal = ref(false);
const showDeleteModal = ref(false);
const selectedStore = ref(null);
const storeToDelete = ref(null);
const deletingStore = ref(false);

const columns = [
  { key: 'code', label: 'Code' },
  { key: 'name', label: 'Store Name' },
  { key: 'location', label: 'City & Address' },
  { key: 'users', label: 'Users' },
  { key: 'is_active', label: 'Status' },
  { key: 'actions', label: 'Actions' },
];

let debounceTimer = null;
watch(search, () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchStores();
  }, 300);
});

async function fetchStores() {
  loading.value = true;
  error.value = '';
  try {
    const params = {};
    if (search.value) params.search = search.value;
    if (!activeOnly.value) params.include_inactive = 1;
    else params.is_active = 1;

    const res = await api.get('/stores', { params });
    if (res.success && res.data) {
      stores.value = res.data;
      // Refresh available stores in global store access store
      storeAccessStore.availableStores = res.data;
    }
  } catch (err) {
    error.value = err.message || 'Failed to fetch stores';
  } finally {
    loading.value = false;
  }
}

function openCreateModal() {
  selectedStore.value = null;
  showFormModal.value = true;
}

function openEditModal(store) {
  selectedStore.value = store;
  showFormModal.value = true;
}

function openUsersModal(store) {
  selectedStore.value = store;
  showUsersModal.value = true;
}

function confirmDeleteStore(store) {
  if (store.code === 'STR-001' || store.code === 'ST-001' || store.id === 1) {
    toast.error('Main/default store (STR-001) cannot be deleted.');
    return;
  }
  storeToDelete.value = store;
  showDeleteModal.value = true;
}

async function handleDeleteStore() {
  if (!storeToDelete.value) return;
  deletingStore.value = true;
  try {
    const res = await api.delete(`/stores/${storeToDelete.value.id}`);
    if (res.success || res.data) {
      toast.success(res.message || 'Store deleted successfully.');
      showDeleteModal.value = false;
      storeToDelete.value = null;
      fetchStores();
    }
  } catch (err) {
    const msg = err.errors ? Object.values(err.errors).flat().join(', ') : (err.message || 'Failed to delete store.');
    toast.error(msg);
  } finally {
    deletingStore.value = false;
  }
}

async function toggleStatus(store) {
  try {
    const res = await api.patch(`/stores/${store.id}/status`);
    if (res.success) {
      toast.success(res.message || 'Store status updated');
      fetchStores();
    }
  } catch (err) {
    toast.error(err.message || 'Failed to toggle status');
  }
}

onMounted(() => {
  fetchStores();
  moduleStore.fetchSettings();
});
</script>
