<template>
  <Modal :show="show" :title="`Assign Users to ${store?.name || 'Store'}`" maxWidth="lg" @close="$emit('close')">
    <div class="space-y-4">
      <p class="text-xs text-slate-500 dark:text-slate-400">
        Select users authorized to access and perform POS/ERP operations in this store.
      </p>

      <SearchFilter v-model="search" placeholder="Filter users by name or email..." />

      <div class="max-h-60 overflow-y-auto space-y-2 border border-slate-200 dark:border-slate-800 rounded-lg p-2 bg-slate-50 dark:bg-slate-900/50">
        <div v-if="loadingUsers" class="p-4 text-center text-xs text-slate-400">Loading users list...</div>
        <div v-else-if="filteredUsers.length === 0" class="p-4 text-center text-xs text-slate-400">No matching users found</div>
        <label
          v-else
          v-for="u in filteredUsers"
          :key="u.id"
          class="flex items-center justify-between p-2.5 rounded-md hover:bg-white dark:hover:bg-slate-800 border border-transparent hover:border-slate-200 dark:hover:border-slate-700 cursor-pointer transition-colors"
        >
          <div class="flex items-center gap-3">
            <input
              type="checkbox"
              :value="u.id"
              v-model="selectedUserIds"
              class="h-4 w-4 rounded border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500"
            />
            <div>
              <div class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ u.name }}</div>
              <div class="text-[10px] text-slate-500 dark:text-slate-400">{{ u.email }} ({{ u.username }})</div>
            </div>
          </div>
          <Badge :variant="u.is_active ? 'success' : 'neutral'">
            {{ u.is_active ? 'Active' : 'Inactive' }}
          </Badge>
        </label>
      </div>

      <ErrorAlert v-if="error" :message="error" />
    </div>

    <template #footer>
      <Button variant="outline" size="sm" @click="$emit('close')">Cancel</Button>
      <Button variant="primary" size="sm" :loading="submitting" @click="handleAssign">
        Save User Assignments ({{ selectedUserIds.length }})
      </Button>
    </template>
  </Modal>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import Modal from '../../components/ui/Modal.vue';
import Button from '../../components/ui/Button.vue';
import Badge from '../../components/ui/Badge.vue';
import SearchFilter from '../../components/ui/SearchFilter.vue';
import ErrorAlert from '../../components/ui/ErrorAlert.vue';
import api from '../../services/api';
import { useToast } from '../../composables/useToast';

const props = defineProps({
  show: { type: Boolean, default: false },
  store: { type: Object, default: null },
});

const emit = defineEmits(['close', 'saved']);
const toast = useToast();

const allUsers = ref([]);
const selectedUserIds = ref([]);
const search = ref('');
const loadingUsers = ref(false);
const submitting = ref(false);
const error = ref('');

const filteredUsers = computed(() => {
  if (!search.value) return allUsers.value;
  const q = search.value.toLowerCase();
  return allUsers.value.filter(u => 
    u.name?.toLowerCase().includes(q) || 
    u.email?.toLowerCase().includes(q) ||
    u.username?.toLowerCase().includes(q)
  );
});

watch(() => props.show, (newVal) => {
  if (newVal && props.store) {
    loadStoreUsers();
  }
});

async function loadStoreUsers() {
  loadingUsers.value = true;
  error.value = '';
  try {
    // Extract currently assigned users
    selectedUserIds.value = (props.store?.users || []).map(u => u.id);

    // Fetch all available stores/users from API
    const res = await api.get('/stores');
    if (res.success && res.data) {
      // Gather unique users across store collections
      const userMap = new Map();
      (props.store?.users || []).forEach(u => userMap.set(u.id, u));
      res.data.forEach(s => {
        (s.users || []).forEach(u => userMap.set(u.id, u));
      });
      allUsers.value = Array.from(userMap.values());
    }
  } catch (err) {
    error.value = err.message || 'Failed to load users';
  } finally {
    loadingUsers.value = false;
  }
}

async function handleAssign() {
  if (!props.store) return;
  submitting.value = true;
  error.value = '';
  try {
    const res = await api.post(`/stores/${props.store.id}/users`, {
      user_ids: selectedUserIds.value,
    });
    if (res.success) {
      toast.success(res.message || 'Store users assigned successfully');
      emit('saved');
      emit('close');
    }
  } catch (err) {
    error.value = err.message || 'Failed to assign users';
  } finally {
    submitting.value = false;
  }
}
</script>
