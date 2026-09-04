<template>
  <div class="h-screen w-screen max-w-full bg-slate-100 text-slate-900 font-sans overflow-hidden flex flex-col">
    <!-- POS Content Area (Starts directly at top of viewport with zero top spacing) -->
    <main class="flex-1 flex min-h-0 overflow-hidden w-full max-w-full">
      <RouterView />
    </main>

    <!-- Global Toast Container -->
    <ToastContainer />
  </div>
</template>

<script setup>
import ToastContainer from '../components/ui/ToastContainer.vue';
</script>
