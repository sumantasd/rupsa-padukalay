<template>
  <div class="space-y-6 antialiased font-sans max-w-4xl mx-auto pb-16">
    <!-- Page Header -->
    <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
      <div class="flex items-center gap-4">
        <div class="h-14 w-14 rounded-2xl bg-red-700 text-white flex items-center justify-center font-black text-lg shadow-lg shadow-red-700/20 shrink-0">
          {{ userInitials }}
        </div>
        <div>
          <h1 class="text-xl font-black text-slate-900 dark:text-white tracking-tight">{{ authStore.user?.name }}</h1>
          <div class="flex items-center gap-2 mt-0.5">
            <span class="text-xs font-mono font-bold text-red-600 dark:text-red-400">@{{ authStore.user?.username }}</span>
            <span class="text-slate-300">•</span>
            <span class="text-xs text-slate-500">{{ authStore.user?.email }}</span>
          </div>
        </div>
      </div>

      <Badge variant="danger" class="text-xs uppercase tracking-wider">
        {{ authStore.isSuperAdmin ? 'Super Admin' : (authStore.user?.roles?.[0]?.name || 'Staff') }}
      </Badge>
    </div>

    <!-- Alert Messages -->
    <div v-if="successMsg" class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 text-emerald-700 dark:text-emerald-300 rounded-2xl font-bold text-xs flex items-center justify-between">
      <span>✅ {{ successMsg }}</span>
      <button @click="successMsg = ''" class="text-emerald-500 font-black">✕</button>
    </div>

    <div v-if="errorMsg" class="p-4 bg-red-50 dark:bg-red-950/40 border border-red-200 text-red-700 dark:text-red-300 rounded-2xl font-bold text-xs flex items-center justify-between">
      <span>⚠️ {{ errorMsg }}</span>
      <button @click="errorMsg = ''" class="text-red-500 font-black">✕</button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Section 1: Update Personal Profile Details -->
      <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-4 text-xs">
        <div class="border-b border-slate-100 dark:border-slate-800 pb-3">
          <h2 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
            <span>👤</span>
            <span>Personal Profile Details</span>
          </h2>
          <p class="text-[11px] text-slate-500 mt-0.5">Update your display name, official email, and contact number.</p>
        </div>

        <form @submit.prevent="updateProfile" class="space-y-3">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Full Name *</label>
            <input
              type="text"
              v-model="profileForm.name"
              required
              class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-600"
            />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Email Address *</label>
            <input
              type="email"
              v-model="profileForm.email"
              required
              class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-600"
            />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Phone Number</label>
            <input
              type="text"
              v-model="profileForm.phone"
              placeholder="+91 98765 43210"
              class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 font-mono text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-600"
            />
          </div>

          <div class="pt-2">
            <button
              type="submit"
              :disabled="savingProfile"
              class="w-full py-2.5 bg-red-600 hover:bg-red-700 text-white font-black text-xs rounded-xl shadow-md shadow-red-600/20 transition-all uppercase tracking-wider disabled:opacity-50 min-h-[44px]"
            >
              {{ savingProfile ? 'Saving Changes...' : 'Save Profile Details' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Section 2: Change Password -->
      <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-4 text-xs">
        <div class="border-b border-slate-100 dark:border-slate-800 pb-3">
          <h2 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
            <span>🔑</span>
            <span>Security & Change Password</span>
          </h2>
          <p class="text-[11px] text-slate-500 mt-0.5">Verify current credentials before updating your security password.</p>
        </div>

        <form @submit.prevent="changePassword" class="space-y-3">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Current Password *</label>
            <input
              type="password"
              v-model="passwordForm.current_password"
              required
              placeholder="Enter current password"
              class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 font-medium text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-600"
            />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">New Password *</label>
            <input
              type="password"
              v-model="passwordForm.new_password"
              required
              placeholder="Minimum 8 characters"
              class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 font-medium text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-600"
            />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Confirm New Password *</label>
            <input
              type="password"
              v-model="passwordForm.new_password_confirmation"
              required
              placeholder="Re-enter new password"
              class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 font-medium text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-600"
            />
          </div>

          <div class="pt-2">
            <button
              type="submit"
              :disabled="savingPassword"
              class="w-full py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-black text-xs rounded-xl shadow-md transition-all uppercase tracking-wider disabled:opacity-50 min-h-[44px]"
            >
              {{ savingPassword ? 'Updating Password...' : 'Update Security Password' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useAuthStore } from '../../stores/authStore';
import api from '../../services/api';
import Badge from '../../components/ui/Badge.vue';

const authStore = useAuthStore();

const savingProfile = ref(false);
const savingPassword = ref(false);
const successMsg = ref('');
const errorMsg = ref('');

const profileForm = reactive({
  name: '',
  email: '',
  phone: '',
});

const passwordForm = reactive({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
});

const userInitials = computed(() => {
  const name = authStore.user?.name;
  if (!name) return 'SA';
  const parts = name.split(' ');
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
  return name.substring(0, 2).toUpperCase();
});

function initForms() {
  if (authStore.user) {
    profileForm.name = authStore.user.name || '';
    profileForm.email = authStore.user.email || '';
    profileForm.phone = authStore.user.phone || '';
  }
}

async function updateProfile() {
  savingProfile.value = true;
  successMsg.value = '';
  errorMsg.value = '';

  try {
    const res = await api.put('/auth/profile', profileForm);
    const updatedUser = res.data?.data || res.data || res;
    if (updatedUser) {
      authStore.setUser(updatedUser);
    }
    successMsg.value = 'Your personal profile details have been saved successfully!';
  } catch (err) {
    errorMsg.value = err.response?.data?.message || 'Failed to update profile details.';
  } finally {
    savingProfile.value = false;
  }
}

async function changePassword() {
  if (passwordForm.new_password.length < 8) {
    errorMsg.value = 'New password must be at least 8 characters long.';
    return;
  }
  if (passwordForm.new_password !== passwordForm.new_password_confirmation) {
    errorMsg.value = 'New password and confirmation do not match.';
    return;
  }

  savingPassword.value = true;
  successMsg.value = '';
  errorMsg.value = '';

  try {
    await api.put('/auth/change-password', passwordForm);
    successMsg.value = 'Security password updated successfully!';
    passwordForm.current_password = '';
    passwordForm.new_password = '';
    passwordForm.new_password_confirmation = '';
  } catch (err) {
    errorMsg.value = err.response?.data?.message || 'Failed to update security password.';
  } finally {
    savingPassword.value = false;
  }
}

onMounted(() => {
  initForms();
});
</script>
