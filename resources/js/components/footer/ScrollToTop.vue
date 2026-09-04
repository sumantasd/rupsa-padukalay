<template>
  <button
    v-if="show"
    @click="scrollToTop"
    class="fixed bottom-6 right-6 z-50 h-10 w-10 bg-black hover:bg-red-600 text-white rounded-md flex items-center justify-center font-bold text-base shadow-xl transition-all hover:scale-105 border border-white/20"
    aria-label="Scroll to top"
  >
    ↑
  </button>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const show = ref(false);

function handleScroll() {
  show.value = window.scrollY > 300;
}

function scrollToTop() {
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

onMounted(() => {
  window.addEventListener('scroll', handleScroll);
});

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll);
});
</script>
