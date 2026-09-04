<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
      <div>
        <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100">User & RBAC Management</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          View application users, security roles, permissions & assigned store access
        </p>
      </div>
      <Badge variant="info">Backend RBAC Authoritative</Badge>
    </div>

    <!-- Search & Filters -->
    <SearchFilter v-model="search" placeholder="Search users by name, username or email..." />

    <!-- Error Alert -->
    <ErrorAlert v-if="error" :message="error" class="mb-4" />

    <!-- Users DataTable -->
    <DataTable :columns="columns" :items="filteredUsers" :loading="loading">
      <template #name="{ item }">
        <div class="font-bold text-slate-900 dark:text-slate-100">{{ item.name }}</div>
      </template>

      <template #account="{ item }">
        <div>
          <div class="font-mono text-xs font-semibold text-indigo-600 dark:text-indigo-400">@{{ item.username }}</div>
          <div class="text-[10px] text-slate-500 dark:text-slate-400">{{ item.email }}</div>
        </div>
      </template>

      <template #roles="{ item }">
        <div class="flex flex-wrap gap-1">
          <Badge v-for="role in (item.roles || ['Cashier'])" :key="role" variant="neutral" class="capitalize text-[10px]">
            {{ typeof role === 'object' ? role.name : role }}
          </Badge>
        </div>
      </template>

      <template #stores="{ item }">
        <div class="text-xs">
          <span v-if="item.is_super_admin" class="text-indigo-500 font-bold">Universal (Super Admin)</span>
          <span v-else-if="item.stores && item.stores.length > 0" class="text-slate-700 dark:text-slate-300 font-medium">
            {{ item.stores.map(s => s.name).join(', ') }}
          </span>
          <span v-else class="text-slate-400 font-mono text-[11px]">Unassigned</span>
        </div>
      </template>

      <template #is_active="{ item }">
        <Badge :variant="item.is_active !== false ? 'success' : 'neutral'">
          {{ item.is_active !== false ? 'Active' : 'Inactive' }}
        </Badge>
      </template>
    </DataTable>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import DataTable from '../../components/ui/DataTable.vue';
import Badge from '../../components/ui/Badge.vue';
import SearchFilter from '../../components/ui/SearchFilter.vue';
import ErrorAlert from '../../components/ui/ErrorAlert.vue';
import api from '../../services/api';

const users = ref([]);
const loading = ref(false);
const error = ref('');
const search = ref('');

const columns = [
  { key: 'name', label: 'User Name' },
  { key: 'account', label: 'Username / Email' },
  { key: 'roles', label: 'Security Roles' },
  { key: 'stores', label: 'Assigned Stores' },
  { key: 'is_active', label: 'Status' },
];

const filteredUsers = computed(() => {
  if (!search.value) return users.value;
  const q = search.value.toLowerCase();
  return users.value.filter(u => 
    u.name?.toLowerCase().includes(q) ||
    u.username?.toLowerCase().includes(q) ||
    u.email?.toLowerCase().includes(q)
  );
});

async function fetchUsers() {
  loading.value = true;
  error.value = '';
  try {
    const res = await api.get('/stores');
    if (res.success && res.data) {
      // Gather unique users from assigned stores
      const userMap = new Map();
      res.data.forEach(store => {
        (store.users || []).forEach(user => {
          if (!userMap.has(user.id)) {
            userMap.set(user.id, {
              ...user,
              stores: [store],
            });
          } else {
            const existing = userMap.get(user.id);
            if (!existing.stores.some(s => s.id === store.id)) {
              existing.stores.push(store);
            }
          }
        });
      });
      users.value = Array.from(userMap.values());
    }
  } catch (err) {
    error.value = err.message || 'Failed to fetch users';
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  fetchUsers();
});
</script>
