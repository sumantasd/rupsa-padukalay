<template>
  <div class="w-full">
    <label v-if="label" :for="id" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
      {{ label }}
      <span v-if="required" class="text-rose-500">*</span>
    </label>
    <select
      :id="id"
      :value="modelValue"
      :disabled="disabled"
      @change="$emit('update:modelValue', $event.target.value)"
      :class="[
        'block w-full rounded-lg text-sm transition-colors border px-3 py-2 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2',
        error ? 'border-rose-500 focus:ring-rose-500 focus:border-rose-500' : 'border-slate-300 dark:border-slate-700 focus:ring-indigo-500 focus:border-indigo-500',
        disabled ? 'opacity-60 bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''
      ]"
      v-bind="$attrs"
    >
      <option v-if="placeholder" value="" disabled selected>{{ placeholder }}</option>
      <option
        v-for="opt in options"
        :key="getOptValue(opt)"
        :value="getOptValue(opt)"
      >
        {{ getOptLabel(opt) }}
      </option>
    </select>
    <p v-if="error" class="mt-1 text-xs text-rose-500 font-medium">{{ error }}</p>
  </div>
</template>

<script setup>
const props = defineProps({
  id: { type: String, default: () => `select-${Math.random().toString(36).substr(2, 9)}` },
  label: { type: String, default: '' },
  modelValue: { type: [String, Number], default: '' },
  options: { type: Array, default: () => [] },
  valueKey: { type: String, default: 'id' },
  labelKey: { type: String, default: 'name' },
  placeholder: { type: String, default: '' },
  error: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);

function getOptValue(opt) {
  if (typeof opt === 'object' && opt !== null) {
    return opt[props.valueKey] !== undefined ? opt[props.valueKey] : opt.value;
  }
  return opt;
}

function getOptLabel(opt) {
  if (typeof opt === 'object' && opt !== null) {
    return opt[props.labelKey] !== undefined ? opt[props.labelKey] : opt.label;
  }
  return opt;
}
</script>
