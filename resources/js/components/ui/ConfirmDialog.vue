<template>
  <Modal :show="show" :title="title" maxWidth="sm" @close="$emit('cancel')">
    <div class="text-sm text-slate-600 dark:text-slate-300">
      {{ message }}
    </div>

    <template #footer>
      <Button variant="outline" size="sm" @click="$emit('cancel')">
        {{ cancelText }}
      </Button>
      <Button :variant="confirmVariant" size="sm" :loading="loading" @click="$emit('confirm')">
        {{ confirmText }}
      </Button>
    </template>
  </Modal>
</template>

<script setup>
import Modal from './Modal.vue';
import Button from './Button.vue';

defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: 'Confirm Action' },
  message: { type: String, default: 'Are you sure you want to proceed?' },
  confirmText: { type: String, default: 'Confirm' },
  cancelText: { type: String, default: 'Cancel' },
  confirmVariant: { type: String, default: 'danger' },
  loading: { type: Boolean, default: false },
});

defineEmits(['confirm', 'cancel']);
</script>
