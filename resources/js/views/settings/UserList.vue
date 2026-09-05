<template>
  <div class="space-y-6 antialiased font-sans max-w-7xl mx-auto pb-16">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-xs">
      <div>
        <div class="flex items-center gap-2">
          <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">Users & Access Control</h1>
          <Badge variant="danger" class="text-[10px]">RBAC Active</Badge>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
          Manage system users, security role assignments, store access boundaries, and credential policies.
        </p>
      </div>

      <button
        v-if="canManageUsers"
        type="button"
        @click="openCreateModal"
        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white font-black text-xs rounded-xl shadow-md shadow-red-600/20 transition-all uppercase tracking-wider shrink-0 min-h-[44px]"
      >
        <span>👤</span>
        <span>Add New User</span>
      </button>
    </div>

    <!-- Error Alert -->
    <div v-if="error" class="p-4 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800/60 rounded-2xl flex items-center justify-between gap-3 text-xs text-red-700 dark:text-red-400 font-bold">
      <div class="flex items-center gap-2">
        <span>⚠️</span>
        <span>{{ error }}</span>
      </div>
      <button @click="fetchUsers(pagination.current_page)" class="underline hover:text-red-900 dark:hover:text-red-200 cursor-pointer">Retry</button>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-xs flex flex-col md:flex-row items-center justify-between gap-4 text-xs">
      <!-- Search Input -->
      <div class="relative w-full md:w-80">
        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
        <input
          type="text"
          v-model="filters.search"
          @input="debouncedSearch"
          placeholder="Search name, username, email, phone..."
          class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl pl-9 pr-4 py-2.5 font-medium text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-600"
        />
      </div>

      <!-- Dropdown Filters -->
      <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
        <!-- Role Filter -->
        <select
          v-model="filters.role_id"
          @change="fetchUsers(1)"
          class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 font-bold text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-red-600"
        >
          <option value="">All Security Roles</option>
          <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
        </select>

        <!-- Status Filter -->
        <select
          v-model="filters.is_active"
          @change="fetchUsers(1)"
          class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 font-bold text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-red-600"
        >
          <option value="">All Statuses</option>
          <option value="true">Active</option>
          <option value="false">Inactive</option>
        </select>

        <!-- Store Filter -->
        <select
          v-model="filters.store_id"
          @change="fetchUsers(1)"
          class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 font-bold text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-red-600"
        >
          <option value="">All Stores</option>
          <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
      </div>
    </div>

    <!-- Loading State Skeleton -->
    <div v-if="loading" class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 p-8 text-center space-y-4">
      <div class="inline-block animate-spin text-2xl text-red-600">⏳</div>
      <p class="text-xs font-bold text-slate-500">Loading user accounts database...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="!users.length" class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 p-12 text-center space-y-3">
      <div class="text-4xl">👥</div>
      <h3 class="text-base font-black text-slate-900 dark:text-white">No Users Found</h3>
      <p class="text-xs text-slate-500 max-w-sm mx-auto">No user records match your search criteria or active filter settings.</p>
    </div>

    <!-- Users Table / Mobile Cards -->
    <div v-else class="space-y-4">
      <!-- Desktop ERP Datatable (Hidden on mobile) -->
      <div class="hidden md:block bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-xs overflow-hidden">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-500 dark:text-slate-400 font-extrabold uppercase tracking-wider border-b border-slate-200/90 dark:border-slate-800">
            <tr>
              <th class="py-3.5 px-4">User Name</th>
              <th class="py-3.5 px-4">Username & Email</th>
              <th class="py-3.5 px-4">Security Role</th>
              <th class="py-3.5 px-4">Assigned Stores</th>
              <th class="py-3.5 px-4">Status</th>
              <th class="py-3.5 px-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
            <tr v-for="user in users" :key="user.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
              <td class="py-3.5 px-4">
                <div class="flex items-center gap-3">
                  <div class="h-9 w-9 rounded-full bg-slate-900 dark:bg-slate-700 text-white flex items-center justify-center font-black text-xs shadow-xs shrink-0">
                    {{ getUserInitials(user.name) }}
                  </div>
                  <div>
                    <div class="font-black text-slate-900 dark:text-white text-xs flex items-center gap-1.5">
                      <span>{{ user.name }}</span>
                      <span v-if="user.is_protected" class="text-[9px] bg-amber-100 text-amber-800 font-extrabold px-1.5 py-0.5 rounded border border-amber-300" title="Protected System Account">Protected</span>
                    </div>
                    <div class="text-[10px] text-slate-400 font-mono">{{ user.phone || 'No phone' }}</div>
                  </div>
                </div>
              </td>

              <td class="py-3.5 px-4">
                <div class="font-mono text-xs font-bold text-red-600 dark:text-red-400">@{{ user.username }}</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400">{{ user.email }}</div>
              </td>

              <td class="py-3.5 px-4">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="role in (user.roles || [])"
                    :key="role.id || role.name"
                    class="px-2 py-0.5 text-[10px] font-black uppercase tracking-wider rounded-md bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/60"
                  >
                    {{ role.name }}
                  </span>
                  <span v-if="!user.roles?.length" class="text-slate-400 text-[10px] italic">No Role</span>
                </div>
              </td>

              <td class="py-3.5 px-4">
                <div class="text-xs">
                  <span v-if="isUserSuperAdmin(user)" class="text-emerald-600 dark:text-emerald-400 font-bold text-[11px]">Universal Access (Super Admin)</span>
                  <div v-else-if="user.stores && user.stores.length" class="flex flex-wrap gap-1">
                    <span v-for="st in user.stores" :key="st.id" class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                      {{ st.name }}
                    </span>
                  </div>
                  <span v-else class="text-slate-400 text-[10px] italic">Unassigned</span>
                </div>
              </td>

              <td class="py-3.5 px-4">
                <Badge :variant="user.is_active ? 'success' : 'neutral'">
                  {{ user.is_active ? 'Active' : 'Inactive' }}
                </Badge>
              </td>

              <td class="py-3.5 px-4 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <!-- View Details -->
                  <button
                    @click="viewUser(user)"
                    class="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 font-bold transition-colors"
                    title="View Details"
                  >
                    👁️
                  </button>

                  <!-- Edit -->
                  <button
                    v-if="canManageUsers && !user.is_protected"
                    @click="openEditModal(user)"
                    class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 font-bold transition-colors"
                    title="Edit User"
                  >
                    ✏️
                  </button>

                  <!-- Toggle Status -->
                  <button
                    v-if="canManageUsers && !user.is_protected && user.id !== authStore.user?.id"
                    @click="toggleUserStatus(user)"
                    :class="['p-1.5 rounded-lg font-bold transition-colors', user.is_active ? 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 hover:bg-amber-100' : 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100']"
                    :title="user.is_active ? 'Deactivate Account' : 'Activate Account'"
                  >
                    {{ user.is_active ? '⏸️' : '▶️' }}
                  </button>

                  <!-- Reset Password -->
                  <button
                    v-if="canManageUsers && (!user.is_protected || authStore.user?.is_protected)"
                    @click="openResetPasswordModal(user)"
                    class="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 font-bold transition-colors"
                    title="Reset Password"
                  >
                    🔑
                  </button>

                  <!-- Delete User -->
                  <button
                    v-if="canManageUsers && !user.is_protected && user.id !== authStore.user?.id"
                    @click="confirmDeleteUser(user)"
                    class="p-1.5 rounded-lg bg-red-50 dark:bg-red-950/50 text-red-600 dark:text-red-400 hover:bg-red-100 font-bold transition-colors"
                    title="Delete User"
                  >
                    🗑️
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile List Cards (Screen < 768px) -->
      <div class="md:hidden space-y-3">
        <MobileListCard
          v-for="user in users"
          :key="user.id"
          :title="user.name"
          :subtitle="`@${user.username} • ${user.email}`"
          :status="user.is_active ? 'Active' : 'Inactive'"
          :statusType="user.is_active ? 'success' : 'neutral'"
        >
          <div class="space-y-1.5 text-xs">
            <div class="flex items-center justify-between">
              <span class="text-slate-500">Security Role:</span>
              <span class="font-bold text-slate-900 dark:text-white uppercase">{{ user.roles?.[0]?.name || 'N/A' }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-500">Assigned Stores:</span>
              <span class="font-bold text-slate-900 dark:text-white">
                {{ isUserSuperAdmin(user) ? 'Universal (Super Admin)' : (user.stores?.map(s => s.name).join(', ') || 'None') }}
              </span>
            </div>
            <div v-if="user.phone" class="flex items-center justify-between">
              <span class="text-slate-500">Phone:</span>
              <span class="font-mono font-bold text-slate-900 dark:text-white">{{ user.phone }}</span>
            </div>
          </div>

          <template #actions>
            <button
              @click="viewUser(user)"
              class="px-2.5 py-1.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl min-h-[44px]"
            >
              Details
            </button>
            <button
              v-if="canManageUsers && !user.is_protected"
              @click="openEditModal(user)"
              class="px-2.5 py-1.5 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold text-xs rounded-xl min-h-[44px]"
            >
              Edit
            </button>
            <button
              v-if="canManageUsers && !user.is_protected && user.id !== authStore.user?.id"
              @click="toggleUserStatus(user)"
              :class="['px-2.5 py-1.5 font-bold text-xs rounded-xl min-h-[44px]', user.is_active ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700']"
            >
              {{ user.is_active ? 'Disable' : 'Enable' }}
            </button>
          </template>
        </MobileListCard>
      </div>

      <!-- Pagination Footer -->
      <div v-if="pagination.last_page > 1" class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/90 dark:border-slate-800 text-xs font-bold text-slate-600 dark:text-slate-400">
        <div>
          Showing page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} total users)
        </div>

        <div class="flex items-center gap-2">
          <button
            @click="fetchUsers(pagination.current_page - 1)"
            :disabled="pagination.current_page <= 1"
            class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 disabled:opacity-40 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"
          >
            ← Previous
          </button>
          <button
            @click="fetchUsers(pagination.current_page + 1)"
            :disabled="pagination.current_page >= pagination.last_page"
            class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 disabled:opacity-40 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"
          >
            Next →
          </button>
        </div>
      </div>
    </div>

    <!-- TELEPORTED CREATE / EDIT USER MODAL -->
    <Teleport to="body">
      <div v-if="showCreateModal || showEditModal" class="fixed inset-0 z-[500] bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl max-w-lg w-full p-6 space-y-4 text-xs font-sans max-h-[90vh] overflow-y-auto">
          <!-- Modal Header -->
          <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <h3 class="font-black text-base text-slate-900 dark:text-white">
              {{ showEditModal ? 'Edit User Account' : 'Create New User Account' }}
            </h3>
            <button @click="closeUserModal" class="text-slate-400 hover:text-slate-600 text-sm font-bold">✕</button>
          </div>

          <!-- Form Error Alert -->
          <div v-if="formError" class="p-3 bg-red-50 text-red-700 rounded-xl text-xs font-bold border border-red-200">
            {{ formError }}
          </div>

          <!-- User Form -->
          <form @submit.prevent="saveUser" class="space-y-4">
            <!-- Full Name -->
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Full Name *</label>
              <input
                type="text"
                v-model="form.name"
                required
                placeholder="e.g. Rahul Sharma"
                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 font-medium text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-600"
              />
              <span v-if="formErrors.name" class="text-red-600 text-[10px] font-bold mt-0.5 block">{{ formErrors.name[0] }}</span>
            </div>

            <!-- Username & Email Row -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Username *</label>
                <input
                  type="text"
                  v-model="form.username"
                  required
                  placeholder="rahul_admin"
                  class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 font-mono text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-600"
                />
                <span v-if="formErrors.username" class="text-red-600 text-[10px] font-bold mt-0.5 block">{{ formErrors.username[0] }}</span>
              </div>

              <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Email Address *</label>
                <input
                  type="email"
                  v-model="form.email"
                  required
                  placeholder="rahul@rupsa.in"
                  class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 font-medium text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-600"
                />
                <span v-if="formErrors.email" class="text-red-600 text-[10px] font-bold mt-0.5 block">{{ formErrors.email[0] }}</span>
              </div>
            </div>

            <!-- Phone Number -->
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Phone Number</label>
              <input
                type="text"
                v-model="form.phone"
                placeholder="+91 98765 43210"
                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 font-mono text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-600"
              />
            </div>

            <!-- Password Fields (Required on create, optional on edit) -->
            <div v-if="!showEditModal || form.change_password" class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-slate-50 dark:bg-slate-800/60 rounded-2xl border border-slate-200/80 dark:border-slate-700">
              <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                  {{ showEditModal ? 'New Password' : 'Password *' }}
                </label>
                <input
                  type="password"
                  v-model="form.password"
                  :required="!showEditModal"
                  placeholder="Minimum 8 characters"
                  class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 font-medium text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-600"
                />
                <span v-if="formErrors.password" class="text-red-600 text-[10px] font-bold mt-0.5 block">{{ formErrors.password[0] }}</span>
              </div>

              <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                  Confirm Password *
                </label>
                <input
                  type="password"
                  v-model="form.password_confirmation"
                  :required="!showEditModal || !!form.password"
                  placeholder="Re-enter password"
                  class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 font-medium text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-600"
                />
              </div>
            </div>

            <!-- Option to toggle password edit in Edit mode -->
            <div v-if="showEditModal" class="flex items-center gap-2">
              <input type="checkbox" id="changePassCheck" v-model="form.change_password" class="rounded accent-red-600" />
              <label for="changePassCheck" class="font-bold text-slate-700 dark:text-slate-300">Change password for this user</label>
            </div>

            <!-- Role Selection Dropdown -->
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Security Role *</label>
              <select
                v-model="form.role_id"
                required
                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-600"
              >
                <option value="" disabled>Select Security Role</option>
                <option v-for="r in roles" :key="r.id" :value="r.id">
                  {{ r.name }} — {{ r.description }}
                </option>
              </select>
            </div>

            <!-- Active Status Switch -->
            <div class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-800/60 rounded-2xl border border-slate-200/80 dark:border-slate-700">
              <div>
                <div class="font-black text-slate-900 dark:text-white text-xs">Account Status</div>
                <div class="text-[10px] text-slate-500">Inactive users are blocked from logging in.</div>
              </div>
              <button
                type="button"
                @click="form.is_active = !form.is_active"
                :class="['px-3 py-1.5 font-black text-xs rounded-xl transition-all cursor-pointer', form.is_active ? 'bg-emerald-600 text-white' : 'bg-slate-300 text-slate-700']"
              >
                {{ form.is_active ? 'Active' : 'Inactive' }}
              </button>
            </div>

            <!-- Store Access Checkboxes -->
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Assigned Retail Stores Access</label>
              <div class="space-y-2 max-h-36 overflow-y-auto p-3 bg-slate-50 dark:bg-slate-800/60 rounded-2xl border border-slate-200/80 dark:border-slate-700">
                <div v-for="st in stores" :key="st.id" class="flex items-center gap-2">
                  <input
                    type="checkbox"
                    :id="`store_${st.id}`"
                    :value="st.id"
                    v-model="form.store_ids"
                    class="rounded accent-red-600 h-4 w-4"
                  />
                  <label :for="`store_${st.id}`" class="font-bold text-slate-800 dark:text-slate-200 text-xs cursor-pointer">
                    {{ st.name }} <span class="text-slate-400 font-mono text-[10px]">({{ st.code }})</span>
                  </label>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
              <button
                type="button"
                @click="closeUserModal"
                class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold rounded-xl hover:bg-slate-200"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="saving"
                class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white font-black rounded-xl shadow-md shadow-red-600/20 disabled:opacity-50"
              >
                {{ saving ? 'Saving...' : (showEditModal ? 'Update User' : 'Create User') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- TELEPORTED RESET PASSWORD MODAL -->
    <Teleport to="body">
      <div v-if="showResetPasswordModal" class="fixed inset-0 z-[500] bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl max-w-sm w-full p-6 space-y-4 text-xs font-sans">
          <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <h3 class="font-black text-base text-slate-900 dark:text-white">Reset User Password</h3>
            <button @click="showResetPasswordModal = false" class="text-slate-400 hover:text-slate-600 text-sm font-bold">✕</button>
          </div>

          <p class="text-xs text-slate-500 font-medium">
            Resetting password for <strong class="text-slate-900 dark:text-white">{{ selectedUser?.name }}</strong> (@{{ selectedUser?.username }}).
          </p>

          <form @submit.prevent="executeResetPassword" class="space-y-3">
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">New Password *</label>
              <input
                type="password"
                v-model="resetPasswordForm.password"
                required
                placeholder="Minimum 8 characters"
                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 font-medium text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-600"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Confirm New Password *</label>
              <input
                type="password"
                v-model="resetPasswordForm.password_confirmation"
                required
                placeholder="Re-enter new password"
                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 font-medium text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-600"
              />
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
              <button
                type="button"
                @click="showResetPasswordModal = false"
                class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold rounded-xl"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="saving"
                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-black rounded-xl shadow-md shadow-red-600/20 disabled:opacity-50"
              >
                {{ saving ? 'Resetting...' : 'Set New Password' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- TELEPORTED VIEW USER DETAILS MODAL -->
    <Teleport to="body">
      <div v-if="showDetailModal" class="fixed inset-0 z-[500] bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl max-w-md w-full p-6 space-y-4 text-xs font-sans">
          <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <h3 class="font-black text-base text-slate-900 dark:text-white">User Profile Overview</h3>
            <button @click="showDetailModal = false" class="text-slate-400 hover:text-slate-600 text-sm font-bold">✕</button>
          </div>

          <div v-if="selectedUser" class="space-y-3">
            <div class="flex items-center gap-3 p-3 bg-slate-50 dark:bg-slate-800/60 rounded-2xl">
              <div class="h-12 w-12 rounded-full bg-slate-900 text-white font-black text-sm flex items-center justify-center">
                {{ getUserInitials(selectedUser.name) }}
              </div>
              <div>
                <div class="font-black text-sm text-slate-900 dark:text-white">{{ selectedUser.name }}</div>
                <div class="text-xs font-mono text-red-600">@{{ selectedUser.username }}</div>
                <div class="text-[11px] text-slate-500">{{ selectedUser.email }}</div>
              </div>
            </div>

            <div class="space-y-2 bg-slate-50 dark:bg-slate-800/40 p-4 rounded-2xl border border-slate-100 dark:border-slate-800">
              <div class="flex items-center justify-between">
                <span class="text-slate-500 font-bold">Account Status:</span>
                <Badge :variant="selectedUser.is_active ? 'success' : 'neutral'">{{ selectedUser.is_active ? 'Active' : 'Inactive' }}</Badge>
              </div>

              <div class="flex items-center justify-between">
                <span class="text-slate-500 font-bold">Security Role:</span>
                <span class="font-black text-slate-900 dark:text-white uppercase">{{ selectedUser.roles?.[0]?.name || 'N/A' }}</span>
              </div>

              <div class="flex items-center justify-between">
                <span class="text-slate-500 font-bold">Phone Number:</span>
                <span class="font-mono text-slate-900 dark:text-white">{{ selectedUser.phone || 'N/A' }}</span>
              </div>

              <div>
                <span class="text-slate-500 font-bold block mb-1">Assigned Retail Stores:</span>
                <div v-if="isUserSuperAdmin(selectedUser)" class="text-emerald-600 font-bold">Universal (Super Admin)</div>
                <div v-else-if="selectedUser.stores?.length" class="flex flex-wrap gap-1">
                  <span v-for="st in selectedUser.stores" :key="st.id" class="px-2 py-0.5 rounded bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-200 font-bold text-[10px]">
                    {{ st.name }}
                  </span>
                </div>
                <div v-else class="text-slate-400 italic">No stores assigned</div>
              </div>
            </div>
          </div>

          <div class="flex justify-end pt-2">
            <button @click="showDetailModal = false" class="px-4 py-2 bg-slate-900 text-white font-bold rounded-xl">Close</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- TELEPORTED DELETE USER MODAL -->
    <Teleport to="body">
      <div v-if="showDeleteModal" class="fixed inset-0 z-[500] bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl max-w-sm w-full p-6 space-y-4 text-xs font-sans">
          <div class="flex items-center gap-3 text-red-600">
            <span class="text-2xl">⚠️</span>
            <h3 class="font-black text-base text-slate-900 dark:text-white">Delete User Account</h3>
          </div>

          <p class="text-slate-600 dark:text-slate-300 font-medium">
            Are you sure you want to delete user account <strong class="text-slate-900 dark:text-white">{{ selectedUser?.name }}</strong> (@{{ selectedUser?.username }})?
          </p>

          <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
            <button @click="showDeleteModal = false" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl">Cancel</button>
            <button
              @click="executeDeleteUser"
              :disabled="saving"
              class="px-4 py-2 bg-red-600 text-white font-black rounded-xl shadow-md shadow-red-600/20 disabled:opacity-50"
            >
              {{ saving ? 'Deleting...' : 'Confirm Delete' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useAuthStore } from '../../stores/authStore';
import api from '../../services/api';
import Badge from '../../components/ui/Badge.vue';
import MobileListCard from '../../components/ui/MobileListCard.vue';

const authStore = useAuthStore();

const users = ref([]);
const roles = ref([]);
const stores = ref([]);

const loading = ref(false);
const saving = ref(false);
const error = ref('');
const formError = ref('');
const formErrors = reactive({});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDetailModal = ref(false);
const showResetPasswordModal = ref(false);
const showDeleteModal = ref(false);

const selectedUser = ref(null);

const pagination = reactive({
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1,
});

const filters = reactive({
  search: '',
  role_id: '',
  is_active: '',
  store_id: '',
});

const form = reactive({
  name: '',
  username: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
  role_id: '',
  is_active: true,
  store_ids: [],
  change_password: false,
});

const resetPasswordForm = reactive({
  password: '',
  password_confirmation: '',
});

const canManageUsers = computed(() => {
  return authStore.hasPermission('users.manage') || authStore.hasPermission('users.create') || authStore.isSuperAdmin;
});

function getUserInitials(name) {
  if (!name) return 'U';
  const parts = name.split(' ');
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
  return name.substring(0, 2).toUpperCase();
}

function isUserSuperAdmin(u) {
  return u.roles?.some(r => (typeof r === 'string' ? r : r.name) === 'Super Admin') || u.is_super_admin;
}

let searchTimer = null;
function debouncedSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    fetchUsers(1);
  }, 350);
}

async function fetchUsers(page = 1) {
  loading.value = true;
  error.value = '';
  try {
    const params = {
      page,
      per_page: pagination.per_page,
    };
    if (filters.search) params.search = filters.search;
    if (filters.role_id) params.role_id = filters.role_id;
    if (filters.is_active !== '') params.is_active = filters.is_active;
    if (filters.store_id) params.store_id = filters.store_id;

    const res = await api.get('/users', { params });
    const payload = res.data || res;

    if (payload.items) {
      users.value = payload.items;
      if (payload.pagination) Object.assign(pagination, payload.pagination);
    } else if (Array.isArray(payload)) {
      users.value = payload;
    } else {
      users.value = [];
    }
  } catch (err) {
    console.error('Failed to fetch users:', err);
    error.value = err.response?.data?.message || 'Failed to connect to users database.';
  } finally {
    loading.value = false;
  }
}

async function fetchRoles() {
  try {
    const res = await api.get('/roles');
    roles.value = res.data || res || [];
  } catch (err) {
    console.error('Failed to fetch roles:', err);
  }
}

async function fetchStores() {
  try {
    const res = await api.get('/stores');
    stores.value = res.data || res || [];
  } catch (err) {
    console.error('Failed to fetch stores:', err);
  }
}

function openCreateModal() {
  formError.value = '';
  Object.keys(formErrors).forEach(k => delete formErrors[k]);
  form.name = '';
  form.username = '';
  form.email = '';
  form.phone = '';
  form.password = '';
  form.password_confirmation = '';
  form.role_id = roles.value[0]?.id || '';
  form.is_active = true;
  form.store_ids = stores.value[0] ? [stores.value[0].id] : [];
  form.change_password = false;
  showCreateModal.value = true;
}

function openEditModal(u) {
  selectedUser.value = u;
  formError.value = '';
  Object.keys(formErrors).forEach(k => delete formErrors[k]);
  form.name = u.name;
  form.username = u.username;
  form.email = u.email;
  form.phone = u.phone || '';
  form.password = '';
  form.password_confirmation = '';
  form.role_id = u.roles?.[0]?.id || '';
  form.is_active = u.is_active !== false;
  form.store_ids = u.stores?.map(s => s.id) || [];
  form.change_password = false;
  showEditModal.value = true;
}

function closeUserModal() {
  showCreateModal.value = false;
  showEditModal.value = false;
}

async function saveUser() {
  saving.value = true;
  formError.value = '';
  Object.keys(formErrors).forEach(k => delete formErrors[k]);

  try {
    const payload = {
      name: form.name,
      username: form.username,
      email: form.email,
      phone: form.phone,
      role_id: form.role_id,
      is_active: form.is_active,
      store_ids: form.store_ids,
    };

    if (!showEditModal.value || form.change_password) {
      payload.password = form.password;
      payload.password_confirmation = form.password_confirmation;
    }

    if (showEditModal.value) {
      await api.put(`/users/${selectedUser.value.id}`, payload);
    } else {
      await api.post('/users', payload);
    }

    closeUserModal();
    fetchUsers(pagination.current_page);
  } catch (err) {
    if (err.response?.status === 422 && err.response?.data?.errors) {
      Object.assign(formErrors, err.response.data.errors);
    }
    formError.value = err.response?.data?.message || 'Failed to save user account.';
  } finally {
    saving.value = false;
  }
}

function viewUser(u) {
  selectedUser.value = u;
  showDetailModal.value = true;
}

async function toggleUserStatus(u) {
  try {
    await api.patch(`/users/${u.id}/status`);
    fetchUsers(pagination.current_page);
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to change user status.');
  }
}

function openResetPasswordModal(u) {
  selectedUser.value = u;
  resetPasswordForm.password = '';
  resetPasswordForm.password_confirmation = '';
  showResetPasswordModal.value = true;
}

async function executeResetPassword() {
  saving.value = true;
  try {
    await api.post(`/users/${selectedUser.value.id}/reset-password`, resetPasswordForm);
    alert('User password has been reset successfully.');
    showResetPasswordModal.value = false;
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to reset password.');
  } finally {
    saving.value = false;
  }
}

function confirmDeleteUser(u) {
  selectedUser.value = u;
  showDeleteModal.value = true;
}

async function executeDeleteUser() {
  saving.value = true;
  try {
    await api.delete(`/users/${selectedUser.value.id}`);
    showDeleteModal.value = false;
    fetchUsers(pagination.current_page);
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to delete user.');
  } finally {
    saving.value = false;
  }
}

onMounted(() => {
  fetchUsers();
  fetchRoles();
  fetchStores();
});
</script>
