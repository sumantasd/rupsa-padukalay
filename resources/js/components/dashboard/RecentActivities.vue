<template>
  <div class="bg-white rounded-2xl border border-slate-200/90 p-6 shadow-xs flex flex-col justify-between">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-base font-black text-slate-900">Recent Activities</h3>
        <p class="text-xs text-slate-500 font-medium mt-0.5">Audit events & recent system operations</p>
      </div>
      <router-link to="/admin/audit-logs" class="text-xs font-bold text-red-600 hover:underline">
        View All
      </router-link>
    </div>

    <!-- Empty State if no recent activities -->
    <div v-if="!activities || activities.length === 0" class="w-full py-8 flex flex-col items-center justify-center text-center p-6 bg-slate-50 rounded-xl border border-dashed border-slate-200">
      <span class="text-3xl mb-2">📋</span>
      <h4 class="font-black text-xs text-slate-700">No Recent Activities Logged</h4>
      <p class="text-[11px] text-slate-400 font-medium mt-0.5">No recent transaction or operational audit logs recorded.</p>
    </div>

    <!-- Timeline List -->
    <div v-else class="space-y-3">
      <div
        v-for="(act, i) in activities"
        :key="i"
        class="flex items-center justify-between p-3 rounded-xl bg-slate-50/70 border border-slate-100 hover:border-slate-300 transition-all"
      >
        <div class="flex items-center gap-3">
          <div :class="['h-9 w-9 rounded-xl flex items-center justify-center text-sm font-bold shrink-0 shadow-xs', getActivityBg(act.type)]">
            {{ getActivityIcon(act.type) }}
          </div>
          <div>
            <h4 class="font-bold text-xs text-slate-900 leading-snug">{{ act.title }}</h4>
            <div class="text-[10px] font-mono text-slate-500 mt-0.5">{{ act.detail }}</div>
          </div>
        </div>

        <div class="text-right shrink-0 pl-2">
          <div class="text-[10px] text-slate-600 font-bold">{{ act.location }}</div>
          <div class="text-[10px] text-slate-400 font-mono mt-0.5">{{ act.time }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  activities: {
    type: Array,
    default: () => []
  }
});

function getActivityIcon(type) {
  if (type === 'sale') return '🛒';
  if (type === 'stock') return '📦';
  if (type === 'transfer') return '🚚';
  if (type === 'po') return '📝';
  return '🔐';
}

function getActivityBg(type) {
  if (type === 'sale') return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
  if (type === 'stock') return 'bg-blue-50 text-blue-700 border border-blue-200';
  if (type === 'transfer') return 'bg-indigo-50 text-indigo-700 border border-indigo-200';
  if (type === 'po') return 'bg-purple-50 text-purple-700 border border-purple-200';
  return 'bg-slate-100 text-slate-700 border border-slate-200';
}
</script>
