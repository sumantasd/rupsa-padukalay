<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 p-6 rounded-2xl border border-slate-800 shadow-xl">
      <div>
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-red-600/20 text-red-500 flex items-center justify-center font-bold text-xl border border-red-500/30">
            🛡️
          </div>
          <div>
            <h1 class="text-xl font-extrabold text-white">Roles & Permissions Management</h1>
            <p class="text-xs text-slate-400 mt-0.5">
              Define security roles, assign granular permission capabilities & manage access controls
            </p>
          </div>
        </div>
      </div>
      <button
        @click="openCreateModal"
        class="inline-flex items-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-700 active:bg-red-800 text-white text-xs font-bold rounded-xl shadow-lg shadow-red-600/20 transition-all shrink-0 cursor-pointer"
      >
        <span class="text-base leading-none">+</span>
        <span>Create New Role</span>
      </button>
    </div>

    <!-- Search & Filter Bar -->
    <div class="bg-slate-900/60 p-4 rounded-xl border border-slate-800/80 flex flex-col sm:flex-row gap-4 items-center justify-between">
      <div class="relative w-full sm:w-80">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search roles or permissions..."
          class="w-full bg-slate-950 border border-slate-800 text-slate-200 placeholder-slate-500 text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-red-500 transition-colors"
        />
        <span class="absolute right-3 top-2.5 text-slate-500 text-xs">🔍</span>
      </div>
      <div class="text-xs text-slate-400 font-medium">
        Total Roles: <span class="font-bold text-white">{{ filteredRoles.length }}</span>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="roleStore.loading" class="py-12 flex justify-center items-center">
      <div class="flex items-center gap-3 text-slate-400 text-sm">
        <span class="h-5 w-5 border-2 border-red-500 border-t-transparent rounded-full animate-spin"></span>
        Loading roles & permissions matrix...
      </div>
    </div>

    <!-- Roles Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div
        v-for="role in filteredRoles"
        :key="role.id"
        class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl flex flex-col justify-between hover:border-slate-700 transition-all space-y-5"
      >
        <div>
          <div class="flex items-start justify-between gap-3 mb-2">
            <div>
              <h3 class="font-extrabold text-base text-white flex items-center gap-2">
                {{ role.name }}
                <span
                  v-if="isSystemRole(role.name)"
                  class="px-2 py-0.5 text-[9px] font-black uppercase rounded bg-amber-500/20 text-amber-400 border border-amber-500/30"
                >
                  System Protected
                </span>
              </h3>
              <p class="text-xs text-slate-400 mt-1 line-clamp-2">{{ role.description || 'No description provided.' }}</p>
            </div>
          </div>

          <!-- Stats Counters -->
          <div class="grid grid-cols-2 gap-2 my-4 p-3 rounded-xl bg-slate-950/70 border border-slate-800/80 text-center">
            <div>
              <span class="block text-[10px] text-slate-500 font-bold uppercase tracking-wider">Assigned Users</span>
              <span class="text-sm font-black text-emerald-400">{{ role.users_count ?? 0 }}</span>
            </div>
            <div>
              <span class="block text-[10px] text-slate-500 font-bold uppercase tracking-wider">Capabilities</span>
              <span class="text-sm font-black text-indigo-400">
                {{ isSuperAdminRole(role.name) ? 'Full Access (All)' : (role.permissions_count ?? role.permissions?.length ?? 0) }}
              </span>
            </div>
          </div>

          <!-- Permissions Badges Preview -->
          <div class="space-y-2">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Key Permissions:</span>
            <div v-if="isSuperAdminRole(role.name)" class="p-2 rounded-xl bg-red-950/30 border border-red-800/40 text-[11px] font-bold text-red-400 text-center">
              ⚡ Full Unrestricted System Capabilities
            </div>
            <div v-else-if="role.permissions && role.permissions.length > 0" class="flex flex-wrap gap-1.5 max-h-28 overflow-y-auto pr-1">
              <span
                v-for="perm in role.permissions.slice(0, 12)"
                :key="perm.id || perm.name"
                class="px-2 py-0.5 text-[10px] font-mono font-bold rounded-lg bg-slate-950 text-indigo-300 border border-slate-800"
              >
                {{ perm.display_name || perm.name }}
              </span>
              <span v-if="role.permissions.length > 12" class="px-2 py-0.5 text-[10px] font-bold text-slate-500">
                +{{ role.permissions.length - 12 }} more
              </span>
            </div>
            <div v-else class="text-xs text-slate-500 italic py-1">
              No permissions assigned.
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="pt-4 border-t border-slate-800/80 flex items-center justify-end gap-2">
          <button
            @click="openEditModal(role)"
            class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs rounded-xl transition-all cursor-pointer"
          >
            Edit Permissions
          </button>
          <button
            v-if="!isSystemRole(role.name)"
            @click="confirmDeleteRole(role)"
            class="px-3 py-1.5 bg-red-950/50 hover:bg-red-900/60 text-red-400 font-bold text-xs rounded-xl transition-all border border-red-800/40 cursor-pointer"
          >
            Delete
          </button>
        </div>
      </div>
    </div>

    <!-- Create / Edit Role Modal (Teleport to Body) -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-[999] bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-4xl max-h-[90vh] flex flex-col shadow-2xl overflow-hidden my-auto">
          <!-- Modal Header -->
          <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-950 shrink-0">
            <h2 class="text-lg font-black text-white flex items-center gap-2">
              <span>{{ isEditing ? 'Edit Security Role' : 'Create New Security Role' }}</span>
              <span v-if="form.name && isSystemRole(form.name)" class="text-xs text-amber-400 font-normal">(System Protected)</span>
            </h2>
            <button @click="closeModal" class="text-slate-400 hover:text-white text-lg font-bold">✕</button>
          </div>

          <!-- Modal Body -->
          <div class="p-6 overflow-y-auto space-y-6 flex-1">
            <!-- Alert for System Role -->
            <div v-if="form.name && isSuperAdminRole(form.name)" class="p-4 rounded-xl bg-amber-950/40 border border-amber-800/50 text-amber-300 text-xs">
              ⚠️ <strong>Super Admin Protection:</strong> Super Admin retains full unrestricted access across all existing & future permissions automatically.
            </div>

            <!-- Role Details Fields -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-extrabold text-slate-300 mb-1">Role Name *</label>
                <input
                  v-model="form.name"
                  type="text"
                  :disabled="isEditing && isSystemRole(form.name)"
                  placeholder="e.g. Senior Inventory Auditor"
                  class="w-full bg-slate-950 border border-slate-800 text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-red-500 disabled:opacity-50"
                />
              </div>
              <div>
                <label class="block text-xs font-extrabold text-slate-300 mb-1">Description</label>
                <input
                  v-model="form.description"
                  type="text"
                  placeholder="Short summary of role capabilities"
                  class="w-full bg-slate-950 border border-slate-800 text-white text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-red-500"
                />
              </div>
            </div>

            <!-- Permissions Matrix Header & Controls -->
            <div class="space-y-4 pt-2">
              <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 bg-slate-950 p-4 rounded-xl border border-slate-800">
                <div>
                  <h3 class="text-sm font-black text-white">Assign Module Permissions</h3>
                  <p class="text-[11px] text-slate-400">
                    Selected: <span class="font-bold text-emerald-400">{{ form.selectedPermissions.length }}</span> capability permissions
                  </p>
                </div>
                <div class="flex items-center gap-2">
                  <button
                    @click="selectAllPermissions"
                    type="button"
                    class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-[11px] font-bold rounded-lg transition-all cursor-pointer"
                  >
                    Select All Global
                  </button>
                  <button
                    @click="clearAllPermissions"
                    type="button"
                    class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-400 text-[11px] font-bold rounded-lg transition-all cursor-pointer"
                  >
                    Clear All
                  </button>
                </div>
              </div>

              <!-- Permission Search -->
              <input
                v-model="permSearch"
                type="text"
                placeholder="Filter permission capabilities (e.g., 'view', 'delete', 'pos')..."
                class="w-full bg-slate-950 border border-slate-800 text-slate-200 placeholder-slate-500 text-xs rounded-xl px-3.5 py-2 focus:outline-none focus:border-red-500"
              />

              <!-- Grouped Permission Checkboxes Grid -->
              <div class="space-y-4 max-h-96 overflow-y-auto pr-2">
                <div
                  v-for="group in filteredPermissionGroups"
                  :key="group.group"
                  class="rounded-xl border border-slate-800 bg-slate-950/60 p-4 space-y-3"
                >
                  <div class="flex items-center justify-between border-b border-slate-800/80 pb-2">
                    <span class="text-xs font-black text-red-400 uppercase tracking-widest">{{ group.group }}</span>
                    <div class="flex items-center gap-2 text-[10px]">
                      <button
                        @click="selectAllGroup(group)"
                        type="button"
                        class="text-indigo-400 hover:underline font-bold cursor-pointer"
                      >
                        Select Module
                      </button>
                      <span class="text-slate-600">|</span>
                      <button
                        @click="clearGroup(group)"
                        type="button"
                        class="text-slate-400 hover:underline font-bold cursor-pointer"
                      >
                        Clear
                      </button>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                    <label
                      v-for="perm in group.permissions"
                      :key="perm.id"
                      class="flex items-start gap-2.5 p-2 rounded-lg bg-slate-900/90 border border-slate-800/90 hover:border-slate-700 cursor-pointer select-none"
                    >
                      <input
                        type="checkbox"
                        :value="perm.id"
                        v-model="form.selectedPermissions"
                        class="mt-0.5 rounded border-slate-700 bg-slate-950 text-red-600 focus:ring-red-500 shrink-0"
                      />
                      <span class="text-xs text-slate-300 font-medium leading-tight">
                        {{ perm.display_name || perm.name }}
                        <span class="block text-[9px] font-mono text-slate-500 font-normal">{{ perm.name }}</span>
                      </span>
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="px-6 py-4 border-t border-slate-800 bg-slate-950 flex items-center justify-end gap-3 shrink-0">
            <button
              @click="closeModal"
              type="button"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-xl transition-all cursor-pointer"
            >
              Cancel
            </button>
            <button
              @click="submitForm"
              :disabled="roleStore.saving"
              type="button"
              class="inline-flex items-center gap-2 px-5 py-2 bg-red-600 hover:bg-red-700 active:bg-red-800 text-white text-xs font-bold rounded-xl shadow-lg shadow-red-600/20 transition-all cursor-pointer disabled:opacity-50"
            >
              <span v-if="roleStore.saving" class="h-4 w-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              <span>{{ isEditing ? 'Save Changes' : 'Create Role' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoleStore } from '../../stores/roleStore';
import { useNotificationStore } from '../../stores/notificationStore';

const roleStore = useRoleStore();
const notificationStore = useNotificationStore();

const searchQuery = ref('');
const permSearch = ref('');
const showModal = ref(false);
const isEditing = ref(false);
const currentEditingId = ref(null);

const form = reactive({
  name: '',
  description: '',
  selectedPermissions: [],
});

onMounted(async () => {
  await Promise.all([
    roleStore.fetchRoles(),
    roleStore.fetchPermissions(),
  ]);
});

const isSystemRole = (name) => {
  if (!name) return false;
  return ['super admin', 'system admin', 'store manager', 'pos cashier'].includes(name.toLowerCase());
};

const isSuperAdminRole = (name) => {
  if (!name) return false;
  return ['super admin', 'system admin'].includes(name.toLowerCase());
};

const filteredRoles = computed(() => {
  if (!searchQuery.value.trim()) return roleStore.roles;
  const q = searchQuery.value.toLowerCase();
  return roleStore.roles.filter(r =>
    r.name.toLowerCase().includes(q) ||
    (r.description && r.description.toLowerCase().includes(q))
  );
});

const filteredPermissionGroups = computed(() => {
  if (!permSearch.value.trim()) return roleStore.permissionGroups;
  const q = permSearch.value.toLowerCase();
  return roleStore.permissionGroups
    .map(g => ({
      ...g,
      permissions: g.permissions.filter(p =>
        p.name.toLowerCase().includes(q) ||
        (p.display_name && p.display_name.toLowerCase().includes(q))
      )
    }))
    .filter(g => g.permissions.length > 0);
});

function openCreateModal() {
  isEditing.value = false;
  currentEditingId.value = null;
  form.name = '';
  form.description = '';
  form.selectedPermissions = [];
  showModal.value = true;
}

async function openEditModal(role) {
  isEditing.value = true;
  currentEditingId.value = role.id;
  form.name = role.name;
  form.description = role.description || '';

  try {
    const details = await roleStore.fetchRoleDetails(role.id);
    form.selectedPermissions = details.permission_ids || [];
  } catch (e) {
    form.selectedPermissions = role.permissions ? role.permissions.map(p => p.id) : [];
  }
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
}

function selectAllPermissions() {
  const allIds = [];
  roleStore.permissionGroups.forEach(g => {
    g.permissions.forEach(p => allIds.push(p.id));
  });
  form.selectedPermissions = allIds;
}

function clearAllPermissions() {
  form.selectedPermissions = [];
}

function selectAllGroup(group) {
  const ids = new Set(form.selectedPermissions);
  group.permissions.forEach(p => ids.add(p.id));
  form.selectedPermissions = Array.from(ids);
}

function clearGroup(group) {
  const groupIds = new Set(group.permissions.map(p => p.id));
  form.selectedPermissions = form.selectedPermissions.filter(id => !groupIds.has(id));
}

async function submitForm() {
  if (!form.name.trim()) {
    notificationStore.showNotification('Role name is required.', 'error');
    return;
  }

  try {
    const payload = {
      name: form.name.trim(),
      description: form.description.trim(),
      permissions: form.selectedPermissions,
    };

    if (isEditing.value) {
      await roleStore.updateRole(currentEditingId.value, payload);
      notificationStore.showNotification('Role updated successfully.', 'success');
    } else {
      await roleStore.createRole(payload);
      notificationStore.showNotification('Role created successfully.', 'success');
    }
    closeModal();
  } catch (e) {
    const msg = e.response?.data?.message || 'Failed to save role.';
    notificationStore.showNotification(msg, 'error');
  }
}

async function confirmDeleteRole(role) {
  if (!confirm(`Are you sure you want to delete role '${role.name}'?`)) return;
  try {
    await roleStore.deleteRole(role.id);
    notificationStore.showNotification('Role deleted successfully.', 'success');
  } catch (e) {
    const msg = e.response?.data?.message || 'Failed to delete role.';
    notificationStore.showNotification(msg, 'error');
  }
}
</script>
