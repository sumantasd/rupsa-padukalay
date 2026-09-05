<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 p-6 rounded-2xl border border-slate-800 shadow-xl">
      <div class="flex items-center gap-3">
        <div class="h-10 w-10 rounded-xl bg-indigo-600/20 text-indigo-400 flex items-center justify-center font-bold text-xl border border-indigo-500/30">
          🔒
        </div>
        <div>
          <h1 class="text-xl font-extrabold text-white">Store Access Control & Isolation</h1>
          <p class="text-xs text-slate-400 mt-0.5">
            Configure multi-store user access permissions & default store operational contexts
          </p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <span class="text-xs text-slate-400 font-medium">Active Outlets: <strong class="text-white">{{ storeAccessStore.storesList.length }}</strong></span>
      </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="bg-slate-900/60 p-4 rounded-xl border border-slate-800/80 flex flex-col sm:flex-row gap-4 items-center justify-between">
      <div class="relative w-full sm:w-80">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search user name or username..."
          class="w-full bg-slate-950 border border-slate-800 text-slate-200 placeholder-slate-500 text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-red-500 transition-colors"
        />
        <span class="absolute right-3 top-2.5 text-slate-500 text-xs">🔍</span>
      </div>
      <div class="text-xs text-slate-400">
        Total Users: <span class="font-bold text-white">{{ filteredUsers.length }}</span>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="storeAccessStore.loading" class="py-12 flex justify-center items-center">
      <div class="flex items-center gap-3 text-slate-400 text-sm">
        <span class="h-5 w-5 border-2 border-red-500 border-t-transparent rounded-full animate-spin"></span>
        Loading store access matrix...
      </div>
    </div>

    <!-- Store Access Table / Mobile Cards -->
    <div v-else class="space-y-4">
      <div class="hidden md:block overflow-x-auto rounded-2xl border border-slate-800 bg-slate-900/80 shadow-xl">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-950 border-b border-slate-800 text-slate-400 uppercase text-[10px] font-black tracking-wider">
              <th class="py-3.5 px-4">User</th>
              <th class="py-3.5 px-4">Roles</th>
              <th class="py-3.5 px-4">Assigned Outlets</th>
              <th class="py-3.5 px-4">Default Operational Store</th>
              <th class="py-3.5 px-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/60 text-slate-300">
            <tr v-for="user in filteredUsers" :key="user.user_id" class="hover:bg-slate-800/40 transition-colors">
              <td class="py-3.5 px-4">
                <div class="font-extrabold text-white">{{ user.name }}</div>
                <div class="text-[10px] text-slate-400 font-mono">@{{ user.username }} ({{ user.email }})</div>
              </td>
              <td class="py-3.5 px-4">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="role in user.roles"
                    :key="role"
                    class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-slate-950 text-indigo-400 border border-slate-800"
                  >
                    {{ role }}
                  </span>
                </div>
              </td>
              <td class="py-3.5 px-4">
                <div class="flex flex-wrap gap-1.5">
                  <span
                    v-for="s in user.assigned_stores"
                    :key="s.id"
                    :class="[
                      'px-2.5 py-1 text-[10px] font-bold rounded-lg flex items-center gap-1 border',
                      s.is_default ? 'bg-emerald-950/60 text-emerald-300 border-emerald-800/60' : 'bg-slate-950 text-slate-300 border-slate-800'
                    ]"
                  >
                    <span>🏬</span>
                    <span>{{ s.name }} ({{ s.code }})</span>
                    <span v-if="s.is_default" class="text-[8px] font-black uppercase bg-emerald-500 text-slate-950 px-1 rounded">DEFAULT</span>
                  </span>
                </div>
              </td>
              <td class="py-3.5 px-4">
                <span v-if="getDefaultStoreName(user)" class="font-extrabold text-emerald-400 flex items-center gap-1">
                  <span>✓</span>
                  <span>{{ getDefaultStoreName(user) }}</span>
                </span>
                <span v-else class="text-amber-500 font-bold text-[11px]">⚠️ No Default Set</span>
              </td>
              <td class="py-3.5 px-4 text-right">
                <button
                  @click="openAssignModal(user)"
                  class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-xl shadow-md transition-all cursor-pointer"
                >
                  Manage Store Access
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile View Cards -->
      <div class="md:hidden space-y-4">
        <div
          v-for="user in filteredUsers"
          :key="user.user_id"
          class="p-4 rounded-xl border border-slate-800 bg-slate-900 space-y-3 shadow-lg"
        >
          <div class="flex items-start justify-between">
            <div>
              <h3 class="font-black text-white text-sm">{{ user.name }}</h3>
              <p class="text-xs text-slate-400 font-mono">@{{ user.username }}</p>
            </div>
            <button
              @click="openAssignModal(user)"
              class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-lg cursor-pointer"
            >
              Assign Stores
            </button>
          </div>

          <div class="space-y-1.5 text-xs">
            <span class="text-[10px] font-bold text-slate-500 uppercase">Assigned Outlets:</span>
            <div class="flex flex-wrap gap-1">
              <span
                v-for="s in user.assigned_stores"
                :key="s.id"
                class="px-2 py-0.5 text-[10px] font-bold rounded bg-slate-950 text-slate-300 border border-slate-800"
              >
                {{ s.name }} {{ s.is_default ? '(Default)' : '' }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Assign Stores Modal (Teleport to Body) -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-[999] bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden my-auto">
          <!-- Modal Header -->
          <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-950 shrink-0">
            <div>
              <h2 class="text-base font-black text-white">Manage Outlet Access</h2>
              <p class="text-xs text-slate-400">User: <strong class="text-white">{{ selectedUser?.name }}</strong> (@{{ selectedUser?.username }})</p>
            </div>
            <button @click="closeModal" class="text-slate-400 hover:text-white text-lg font-bold">✕</button>
          </div>

          <!-- Modal Body -->
          <div class="p-6 space-y-6">
            <!-- Outlets Checkbox Selection -->
            <div class="space-y-3">
              <label class="block text-xs font-extrabold text-slate-300 uppercase tracking-wider">Select Authorized Outlets *</label>
              <div class="space-y-2 max-h-60 overflow-y-auto">
                <label
                  v-for="store in storeAccessStore.storesList"
                  :key="store.id"
                  class="flex items-center justify-between p-3 rounded-xl bg-slate-950 border border-slate-800 hover:border-slate-700 cursor-pointer select-none"
                >
                  <div class="flex items-center gap-3">
                    <input
                      type="checkbox"
                      :value="store.id"
                      v-model="form.selectedStoreIds"
                      @change="handleStoreToggle(store.id)"
                      class="rounded border-slate-700 bg-slate-900 text-red-600 focus:ring-red-500"
                    />
                    <div>
                      <div class="font-extrabold text-white text-xs">{{ store.name }}</div>
                      <div class="text-[10px] text-slate-400">Code: {{ store.code }} | {{ store.city }}</div>
                    </div>
                  </div>
                  <span v-if="form.defaultStoreId == store.id" class="px-2 py-0.5 text-[9px] font-black uppercase rounded bg-emerald-500 text-slate-950">
                    Default Store
                  </span>
                </label>
              </div>
            </div>

            <!-- Default Store Radio Select -->
            <div v-if="form.selectedStoreIds.length > 0" class="space-y-2 pt-2 border-t border-slate-800">
              <label class="block text-xs font-extrabold text-slate-300 uppercase tracking-wider">Select Default Operational Context Store *</label>
              <select
                v-model="form.defaultStoreId"
                class="w-full bg-slate-950 border border-slate-800 text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-red-500"
              >
                <option v-for="id in form.selectedStoreIds" :key="id" :value="id">
                  {{ getStoreById(id)?.name }} ({{ getStoreById(id)?.code }})
                </option>
              </select>
              <p class="text-[11px] text-slate-400">
                The default store will automatically load when this user logs in to POS or Admin dashboard.
              </p>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="px-6 py-4 border-t border-slate-800 bg-slate-950 flex items-center justify-end gap-3">
            <button
              @click="closeModal"
              type="button"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-xl transition-all cursor-pointer"
            >
              Cancel
            </button>
            <button
              @click="submitStoreAccess"
              :disabled="storeAccessStore.saving"
              type="button"
              class="inline-flex items-center gap-2 px-5 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-xl shadow-lg transition-all cursor-pointer disabled:opacity-50"
            >
              <span v-if="storeAccessStore.saving" class="h-4 w-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              <span>Save Store Access</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useStoreAccessStore } from '../../stores/storeAccessStore';
import { useNotificationStore } from '../../stores/notificationStore';

const storeAccessStore = useStoreAccessStore();
const notificationStore = useNotificationStore();

const searchQuery = ref('');
const showModal = ref(false);
const selectedUser = ref(null);

const form = reactive({
  selectedStoreIds: [],
  defaultStoreId: null,
});

onMounted(async () => {
  await storeAccessStore.fetchMatrix();
});

const filteredUsers = computed(() => {
  if (!searchQuery.value.trim()) return storeAccessStore.usersMatrix;
  const q = searchQuery.value.toLowerCase();
  return storeAccessStore.usersMatrix.filter(u =>
    u.name.toLowerCase().includes(q) ||
    u.username.toLowerCase().includes(q) ||
    u.email.toLowerCase().includes(q)
  );
});

function getDefaultStoreName(user) {
  const store = user.assigned_stores.find(s => s.is_default);
  return store ? store.name : (user.assigned_stores[0]?.name || null);
}

function getStoreById(id) {
  return storeAccessStore.storesList.find(s => s.id === id);
}

function openAssignModal(user) {
  selectedUser.value = user;
  form.selectedStoreIds = [...(user.assigned_store_ids || [])];
  form.defaultStoreId = user.default_store_id || (form.selectedStoreIds[0] || null);
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  selectedUser.value = null;
}

function handleStoreToggle(storeId) {
  if (!form.selectedStoreIds.includes(form.defaultStoreId)) {
    form.defaultStoreId = form.selectedStoreIds[0] || null;
  }
}

async function submitStoreAccess() {
  if (form.selectedStoreIds.length === 0) {
    notificationStore.showNotification('User must be assigned to at least one valid store.', 'error');
    return;
  }
  if (!form.defaultStoreId || !form.selectedStoreIds.includes(form.defaultStoreId)) {
    notificationStore.showNotification('Default store must be selected from assigned stores.', 'error');
    return;
  }

  try {
    await storeAccessStore.assignUserStores(selectedUser.value.user_id, {
      store_ids: form.selectedStoreIds,
      default_store_id: form.defaultStoreId,
    });
    notificationStore.showNotification('Store access permissions updated successfully.', 'success');
    closeModal();
  } catch (e) {
    const msg = e.response?.data?.message || 'Failed to update store access.';
    notificationStore.showNotification(msg, 'error');
  }
}
</script>
