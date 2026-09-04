<template>
  <RouterView />
</template>

<script setup>
import { onMounted } from 'vue';
import { useAuthStore } from './stores/authStore';
import { usePrinterStore } from './stores/printerStore';

const authStore = useAuthStore();
const printerStore = usePrinterStore();

onMounted(() => {
  if (authStore.token) {
    authStore.fetchMe();
  }
  printerStore.fetchSettings();
});
</script>
