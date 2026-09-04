<template>
  <div class="space-y-6 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900">{{ title }}</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">{{ description }}</p>
      </div>
      <div class="px-3 py-1.5 bg-amber-50 text-amber-800 border border-amber-200 rounded-xl text-xs font-bold flex items-center gap-1.5">
        <span>🚧</span>
        <span>Scheduled Module</span>
      </div>
    </div>

    <!-- Info Box -->
    <div class="p-8 rounded-2xl bg-white border border-slate-200 shadow-xs flex flex-col items-center justify-center text-center space-y-3">
      <div class="h-16 w-16 rounded-2xl bg-slate-100 flex items-center justify-center text-3xl">
        {{ icon }}
      </div>
      <h3 class="text-lg font-black text-slate-900">{{ title }}</h3>
      <p class="text-xs text-slate-500 max-w-md">
        This business module is scheduled for implementation in the next phase. Route <code class="font-mono text-red-600 bg-red-50 px-1.5 py-0.5 rounded">{{ currentRoutePath }}</code> is active and protected under RUPSA PADUKALAYA RBAC permissions.
      </p>
      <div class="pt-2">
        <router-link
          to="/admin/dashboard"
          class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition-colors inline-block"
        >
          ← Return to Executive Dashboard
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

const currentRoutePath = computed(() => route.path);

const props = defineProps({
  title: { type: String, default: 'Module Under Schedule' },
  description: { type: String, default: 'Operational retail module' },
  icon: { type: String, default: '⚙️' }
});
</script>
