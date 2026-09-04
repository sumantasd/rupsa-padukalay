<template>
  <Modal :show="show" :title="store ? 'Edit Store' : 'Create New Store'" maxWidth="lg" @close="$emit('close')">
    <form @submit.prevent="handleSubmit" class="space-y-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <Input
          label="Store Code"
          v-model="form.code"
          placeholder="STORE-01"
          required
          :error="errors.code"
        />

        <Input
          label="Store Name"
          v-model="form.name"
          placeholder="Main Bazaar Branch"
          required
          :error="errors.name"
        />
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <Input
          label="Phone Number"
          v-model="form.phone"
          placeholder="+91 9876543210"
          :error="errors.phone"
        />

        <Input
          label="Email Address"
          type="email"
          v-model="form.email"
          placeholder="store01@rupsa.com"
          :error="errors.email"
        />
      </div>

      <Input
        label="Street Address"
        v-model="form.address"
        placeholder="123 Footwear Market Road"
        :error="errors.address"
      />

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <Input
          label="City"
          v-model="form.city"
          placeholder="Kolkata"
          :error="errors.city"
        />

        <Input
          label="Pincode"
          v-model="form.pincode"
          placeholder="700001"
          :error="errors.pincode"
        />
      </div>

      <div class="flex items-center gap-2 pt-2">
        <input
          type="checkbox"
          id="store-is-active"
          v-model="form.is_active"
          class="h-4 w-4 rounded border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500"
        />
        <label for="store-is-active" class="text-xs font-semibold text-slate-700 dark:text-slate-300">
          Active Store Status
        </label>
      </div>

      <ErrorAlert v-if="generalError" :message="generalError" />
    </form>

    <template #footer>
      <Button variant="outline" size="sm" @click="$emit('close')">Cancel</Button>
      <Button variant="primary" size="sm" :loading="submitting" @click="handleSubmit">
        {{ store ? 'Update Store' : 'Create Store' }}
      </Button>
    </template>
  </Modal>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import Modal from '../../components/ui/Modal.vue';
import Input from '../../components/ui/Input.vue';
import Button from '../../components/ui/Button.vue';
import ErrorAlert from '../../components/ui/ErrorAlert.vue';
import api from '../../services/api';
import { useToast } from '../../composables/useToast';

const props = defineProps({
  show: { type: Boolean, default: false },
  store: { type: Object, default: null },
});

const emit = defineEmits(['close', 'saved']);
const toast = useToast();

const form = reactive({
  code: '',
  name: '',
  phone: '',
  email: '',
  address: '',
  city: '',
  pincode: '',
  is_active: true,
});

const submitting = ref(false);
const errors = reactive({});
const generalError = ref('');

watch(() => props.store, (newVal) => {
  if (newVal) {
    form.code = newVal.code || '';
    form.name = newVal.name || '';
    form.phone = newVal.phone || '';
    form.email = newVal.email || '';
    form.address = newVal.address || '';
    form.city = newVal.city || '';
    form.pincode = newVal.pincode || '';
    form.is_active = newVal.is_active !== undefined ? newVal.is_active : true;
  } else {
    resetForm();
  }
}, { immediate: true });

function resetForm() {
  form.code = '';
  form.name = '';
  form.phone = '';
  form.email = '';
  form.address = '';
  form.city = '';
  form.pincode = '';
  form.is_active = true;
  Object.keys(errors).forEach(key => delete errors[key]);
  generalError.value = '';
}

async function handleSubmit() {
  submitting.value = true;
  generalError.value = '';
  Object.keys(errors).forEach(key => delete errors[key]);

  try {
    let res;
    if (props.store) {
      res = await api.put(`/stores/${props.store.id}`, form);
    } else {
      res = await api.post('/stores', form);
    }

    if (res.success) {
      toast.success(res.message || (props.store ? 'Store updated successfully' : 'Store created successfully'));
      emit('saved');
      emit('close');
    }
  } catch (err) {
    if (err.errors) {
      Object.assign(errors, err.errors);
    } else {
      generalError.value = err.message || 'Operation failed';
    }
  } finally {
    submitting.value = false;
  }
}
</script>
