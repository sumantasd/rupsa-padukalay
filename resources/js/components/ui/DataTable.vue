<template>
  <div class="w-full overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs">
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse text-sm">
        <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 uppercase text-[11px] font-semibold tracking-wider border-b border-slate-200 dark:border-slate-800">
          <tr>
            <th
              v-for="col in columns"
              :key="col.key"
              :class="['px-4 py-3', col.headerClass || '']"
            >
              {{ col.label }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 text-slate-700 dark:text-slate-200">
          <tr v-if="loading" v-for="i in 4" :key="`skeleton-${i}`" class="animate-pulse">
            <td v-for="col in columns" :key="col.key" class="px-4 py-3.5">
              <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded w-3/4"></div>
            </td>
          </tr>
          <tr v-else-if="items.length === 0">
            <td :colspan="columns.length" class="px-4 py-12 text-center text-slate-400 dark:text-slate-500">
              <slot name="empty">
                <div class="flex flex-col items-center justify-center gap-2">
                  <span class="text-2xl">🔍</span>
                  <p class="text-sm font-medium">No data records found</p>
                </div>
              </slot>
            </td>
          </tr>
          <tr
            v-else
            v-for="(item, index) in items"
            :key="item.id || index"
            class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors"
          >
            <td
              v-for="col in columns"
              :key="col.key"
              :class="['px-4 py-3.5 align-middle', col.cellClass || '']"
            >
              <slot :name="col.key" :item="item" :index="index">
                {{ item[col.key] !== undefined ? item[col.key] : '-' }}
              </slot>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
defineProps({
  columns: { type: Array, required: true }, // Array of { key: string, label: string, headerClass?: string, cellClass?: string }
  items: { type: Array, required: true },
  loading: { type: Boolean, default: false },
});
</script>
