<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-xs">
      <div>
        <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100">Security Roles & Permissions Matrix</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          Review role definitions, permission tags & enforcement boundaries
        </p>
      </div>
      <Badge variant="success">Granular Permissions Active</Badge>
    </div>

    <!-- Role Cards Matrix -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div v-for="role in roles" :key="role.name" class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-xs flex flex-col justify-between space-y-4">
        <div>
          <div class="flex items-center justify-between mb-2">
            <h3 class="font-bold text-base text-slate-900 dark:text-slate-100">{{ role.name }}</h3>
            <Badge :variant="role.color">{{ role.badgeText }}</Badge>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">{{ role.description }}</p>

          <div class="space-y-2">
            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Assigned Permissions:</span>
            <div class="flex flex-wrap gap-1.5">
              <span
                v-for="perm in role.permissions"
                :key="perm"
                class="px-2 py-0.5 text-[10px] font-mono font-semibold rounded bg-slate-100 dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 border border-slate-200 dark:border-slate-700"
              >
                {{ perm }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import Badge from '../../components/ui/Badge.vue';

const roles = [
  {
    name: 'Super Admin',
    badgeText: 'Full Access',
    color: 'danger',
    description: 'Universal cross-store access, system configuration, audit trails & role management.',
    permissions: ['* (All System Permissions)', 'users.manage', 'products.view', 'products.create', 'products.edit'],
  },
  {
    name: 'Store Manager',
    badgeText: 'Store Manager',
    color: 'warning',
    description: 'Store-scoped management for inventory, transfers, purchase orders, expenses & reports.',
    permissions: ['products.view', 'products.create', 'products.edit'],
  },
  {
    name: 'Cashier / POS Operator',
    badgeText: 'POS Cashier',
    color: 'info',
    description: 'POS sales checkout, split payments, receipts, returns, exchanges & drawer movements.',
    permissions: ['products.view', 'products.create'],
  },
];
</script>
