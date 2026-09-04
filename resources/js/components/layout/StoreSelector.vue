<template>
  <div class="flex items-center gap-1.5">
    <div class="flex items-center gap-1 bg-slate-100/90 border border-slate-200 rounded-xl px-2.5 py-1 text-xs text-slate-800 font-bold">
      <span class="text-sm">🏬</span>
      <select
        v-if="availableStores.length > 0"
        :value="activeStoreId"
        @change="handleStoreChange"
        class="bg-transparent text-xs font-black text-slate-900 focus:outline-none cursor-pointer"
      >
        <option v-if="isSuperAdmin" :value="null">All Stores (Cross-Store Overview)</option>
        <option v-for="st in availableStores" :key="st.id" :value="st.id">
          RUPSA - {{ st.name }} ({{ st.code }})
        </option>
      </select>
      <span v-else class="text-xs font-semibold text-slate-400">Loading Stores...</span>
    </div>
  </div>
</template>

<script setup>
import { useStoreAccessStore } from '../../stores/storeAccessStore';
import { useAuth } from '../../composables/useAuth';
import { computed, onMounted } from 'vue';

const storeAccessStore = useStoreAccessStore();
const { isSuperAdmin } = useAuth();

const availableStores = computed(() => storeAccessStore.availableStores);
const activeStoreId = computed(() => storeAccessStore.activeStoreId);

function handleStoreChange(e) {
  const val = e.target.value ? Number(e.target.value) : null;
  storeAccessStore.setActiveStore(val);
}

onMounted(() => {
  storeAccessStore.fetchAvailableStores();
});
</script>
