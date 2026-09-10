<template>
  <BottomSheet
    :show="show"
    @update:show="$emit('update:show', $event)"
    title="FILTER REPORT"
    subtitle="Select date range & grouping"
  >
    <div class="space-y-4 font-sans text-xs">
      <!-- 1. Quick Date Range Preset Buttons -->
      <div class="space-y-1.5">
        <label class="font-extrabold text-slate-700 dark:text-slate-300 uppercase tracking-wider text-[10px] block">
          Date Range Preset
        </label>
        <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
          <button
            v-for="preset in datePresets"
            :key="preset.id"
            type="button"
            @click="selectPreset(preset.id)"
            :class="[
              'py-2 px-2.5 rounded-xl font-bold transition-all text-center border min-h-[38px] text-[11px]',
              selectedPreset === preset.id
                ? 'bg-red-600 border-red-600 text-white shadow-md shadow-red-600/20'
                : 'bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:border-red-500'
            ]"
          >
            {{ preset.label }}
          </button>
        </div>
      </div>

      <!-- 2. Custom Date Range Pickers -->
      <div v-if="selectedPreset === 'custom'" class="grid grid-cols-2 gap-3 p-3 bg-slate-50 dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800">
        <div>
          <label class="font-extrabold text-slate-500 text-[10px] uppercase block mb-1">From Date</label>
          <input
            type="date"
            v-model="localFilters.date_from"
            class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-red-500"
          />
        </div>
        <div>
          <label class="font-extrabold text-slate-500 text-[10px] uppercase block mb-1">To Date</label>
          <input
            type="date"
            v-model="localFilters.date_to"
            class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-red-500"
          />
        </div>
      </div>

      <!-- 3. Group By Timeframe Selector -->
      <div v-if="showGroupBy" class="space-y-1.5">
        <label class="font-extrabold text-slate-700 dark:text-slate-300 uppercase tracking-wider text-[10px] block">
          Group By Timeframe
        </label>
        <div class="grid grid-cols-4 gap-2">
          <button
            v-for="gb in groupByOptions"
            :key="gb.id"
            type="button"
            @click="localFilters.group_by = gb.id"
            :class="[
              'py-2 px-2 rounded-xl font-bold transition-all text-center border min-h-[38px] text-[11px]',
              localFilters.group_by === gb.id
                ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900 border-slate-900 dark:border-white shadow-sm'
                : 'bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:border-slate-400'
            ]"
          >
            {{ gb.label }}
          </button>
        </div>
      </div>

      <!-- 4. Additional Slot for Specific Module Filters -->
      <slot :filters="localFilters"></slot>
    </div>

    <!-- Sticky Bottom Sheet Actions Footer -->
    <template #footer>
      <div class="flex items-center gap-3">
        <button
          type="button"
          @click="resetFilters"
          class="flex-1 py-3 px-4 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold text-xs hover:bg-slate-100 dark:hover:bg-slate-800 transition-all cursor-pointer min-h-[44px]"
        >
          Reset Filters
        </button>
        <button
          type="button"
          @click="applyFilters"
          class="flex-1 py-3 px-4 rounded-xl bg-red-600 hover:bg-red-700 active:scale-95 text-white font-black text-xs shadow-lg shadow-red-600/30 transition-all cursor-pointer min-h-[44px]"
        >
          Apply Filters
        </button>
      </div>
    </template>
  </BottomSheet>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import BottomSheet from './BottomSheet.vue';

const props = defineProps({
  show: Boolean,
  filters: { type: Object, default: () => ({}) },
  showGroupBy: { type: Boolean, default: true },
});

const emit = defineEmits(['update:show', 'apply', 'reset']);

const selectedPreset = ref('this_month');

const localFilters = reactive({
  date_from: '',
  date_to: '',
  group_by: 'day',
  ...props.filters,
});

const datePresets = [
  { id: 'today', label: 'Today' },
  { id: 'yesterday', label: 'Yesterday' },
  { id: 'this_week', label: 'This Week' },
  { id: 'this_month', label: 'This Month' },
  { id: 'previous_month', label: 'Previous Month' },
  { id: 'this_year', label: 'This Year' },
  { id: 'custom', label: 'Custom' },
];

const groupByOptions = [
  { id: 'day', label: 'Day' },
  { id: 'week', label: 'Week' },
  { id: 'month', label: 'Month' },
  { id: 'year', label: 'Year' },
];

function selectPreset(presetId) {
  selectedPreset.value = presetId;
  const now = new Date();

  if (presetId === 'today') {
    const d = formatDate(now);
    localFilters.date_from = d;
    localFilters.date_to = d;
  } else if (presetId === 'yesterday') {
    const y = new Date();
    y.setDate(y.getDate() - 1);
    const d = formatDate(y);
    localFilters.date_from = d;
    localFilters.date_to = d;
  } else if (presetId === 'this_week') {
    const first = new Date(now);
    const day = first.getDay() || 7; // Monday start
    if (day !== 1) first.setHours(-24 * (day - 1));
    localFilters.date_from = formatDate(first);
    localFilters.date_to = formatDate(now);
  } else if (presetId === 'this_month') {
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    localFilters.date_from = formatDate(firstDay);
    localFilters.date_to = formatDate(now);
  } else if (presetId === 'previous_month') {
    const firstDayPrev = new Date(now.getFullYear(), now.getMonth() - 1, 1);
    const lastDayPrev = new Date(now.getFullYear(), now.getMonth(), 0);
    localFilters.date_from = formatDate(firstDayPrev);
    localFilters.date_to = formatDate(lastDayPrev);
  } else if (presetId === 'this_year') {
    const firstDayYear = new Date(now.getFullYear(), 0, 1);
    localFilters.date_from = formatDate(firstDayYear);
    localFilters.date_to = formatDate(now);
  } else if (presetId === 'custom') {
    // leave existing custom dates
  }
}

function formatDate(dateObj) {
  const yyyy = dateObj.getFullYear();
  const mm = String(dateObj.getMonth() + 1).padStart(2, '0');
  const dd = String(dateObj.getDate()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}`;
}

function applyFilters() {
  emit('apply', { ...localFilters, preset: selectedPreset.value, store_id: '' });
  emit('update:show', false);
}

function resetFilters() {
  selectPreset('this_month');
  emit('reset');
  emit('update:show', false);
}

onMounted(() => {
  if (!localFilters.date_from && !localFilters.date_to) {
    selectPreset('this_month');
  }
});
</script>
