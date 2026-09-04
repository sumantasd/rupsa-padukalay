<template>
  <nav class="hidden md:flex items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400 mb-4">
    <RouterLink to="/dashboard" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
      Home
    </RouterLink>
    <span v-if="crumbs.length > 0">/</span>
    <template v-for="(crumb, idx) in crumbs" :key="crumb.path">
      <RouterLink
        v-if="idx < crumbs.length - 1"
        :to="crumb.path"
        class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
      >
        {{ crumb.name }}
      </RouterLink>
      <span v-else class="text-slate-800 dark:text-slate-200 font-bold capitalize">
        {{ crumb.name }}
      </span>
      <span v-if="idx < crumbs.length - 1">/</span>
    </template>
  </nav>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

const crumbs = computed(() => {
  const pathArray = route.path.split('/').filter(p => p);
  return pathArray.map((path, idx) => {
    return {
      name: path.replace(/-/g, ' '),
      path: '/' + pathArray.slice(0, idx + 1).join('/'),
    };
  });
});
</script>
