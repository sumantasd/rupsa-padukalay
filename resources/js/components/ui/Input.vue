<template>
  <div :class="['w-full', wrapperClass]">
    <label v-if="label" :for="id" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
      {{ label }}
      <span v-if="required" class="text-rose-500">*</span>
    </label>
    <div class="relative rounded-md shadow-sm">
      <input
        :id="id"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :readonly="readonly"
        @input="$emit('update:modelValue', $event.target.value)"
        :class="[
          'block w-full rounded-lg text-sm transition-colors border px-3 py-2 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2',
          error ? 'border-rose-500 focus:ring-rose-500 focus:border-rose-500' : 'border-slate-300 dark:border-slate-700 focus:ring-indigo-500 focus:border-indigo-500',
          disabled ? 'opacity-60 bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : ''
        ]"
        v-bind="$attrs"
      />
    </div>
    <p v-if="error" class="mt-1 text-xs text-rose-500 font-medium">{{ error }}</p>
    <p v-else-if="helpText" class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ helpText }}</p>
  </div>
</template>

<script setup>
defineProps({
  id: { type: String, default: () => `input-${Math.random().toString(36).substr(2, 9)}` },
  label: { type: String, default: '' },
  modelValue: { type: [String, Number], default: '' },
  type: { type: String, default: 'text' },
  placeholder: { type: String, default: '' },
  error: { type: String, default: '' },
  helpText: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  wrapperClass: { type: String, default: '' },
});

defineEmits(['update:modelValue']);
</script>
