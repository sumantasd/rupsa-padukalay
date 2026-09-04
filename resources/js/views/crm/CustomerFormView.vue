<template>
  <div class="space-y-6 antialiased font-sans max-w-4xl mx-auto pb-16">
    <!-- Breadcrumb & Header -->
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
      <div>
        <div class="hidden md:flex items-center gap-2 text-xs font-bold text-slate-500 mb-1">
          <router-link to="/admin/customers" class="hover:text-red-600">Customers</router-link>
          <span>/</span>
          <span class="text-slate-900">{{ isEdit ? 'Edit Customer' : 'Register New Customer' }}</span>
        </div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
          {{ isEdit ? `Edit Customer: ${form.name}` : 'Register New Retail Customer' }}
        </h1>
      </div>

      <router-link
        to="/admin/customers"
        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-colors"
      >
        ← Back to Customers
      </router-link>
    </div>

    <!-- Error Alert -->
    <div v-if="errorMessage" class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-2xl text-xs font-bold flex items-center gap-2">
      <span>⚠️</span>
      <span>{{ errorMessage }}</span>
    </div>

    <!-- Success Alert -->
    <div v-if="successMessage" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-bold flex items-center gap-2">
      <span>✓</span>
      <span>{{ successMessage }}</span>
    </div>

    <!-- Main Customer Form Card -->
    <form @submit.prevent="submitForm" class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200/90 shadow-sm space-y-6 text-xs">
      <div class="border-b border-slate-100 pb-3">
        <h2 class="font-black text-base text-slate-900 flex items-center gap-2">
          <span>👤</span>
          <span>Customer Personal Details</span>
        </h2>
        <p class="text-slate-500 font-medium mt-0.5">Enter primary identification & contact details for retail invoice generation.</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <!-- Customer Name -->
        <div class="space-y-1.5">
          <label class="font-black text-slate-800 block">
            Customer Name <span class="text-red-600">*</span>
          </label>
          <input
            v-model="form.name"
            type="text"
            required
            placeholder="e.g. Subhash Ghosh"
            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
          />
        </div>

        <!-- Mobile Number -->
        <div class="space-y-1.5">
          <label class="font-black text-slate-800 block">
            Mobile Number <span class="text-red-600">*</span>
          </label>
          <input
            v-model="form.mobile_number"
            type="text"
            required
            placeholder="e.g. +91 9735125112"
            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-mono font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
          />
        </div>

        <!-- Email -->
        <div class="space-y-1.5">
          <label class="font-bold text-slate-700 block">Email Address (Optional)</label>
          <input
            v-model="form.email"
            type="email"
            placeholder="e.g. subhash@example.com"
            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
          />
        </div>

        <!-- City -->
        <div class="space-y-1.5">
          <label class="font-bold text-slate-700 block">City / Location</label>
          <input
            v-model="form.city"
            type="text"
            placeholder="e.g. Dhantala / Ranaghat / Kolkata"
            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
          />
        </div>

        <!-- PIN Code -->
        <div class="space-y-1.5">
          <label class="font-bold text-slate-700 block">PIN Code</label>
          <input
            v-model="form.pincode"
            type="text"
            placeholder="e.g. 741202"
            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
          />
        </div>
      </div>

      <!-- Address Line -->
      <div class="space-y-1.5 pt-2 border-t border-slate-100">
        <label class="font-bold text-slate-700 block">Full Residential / Shipping Address</label>
        <textarea
          v-model="form.address"
          rows="3"
          placeholder="e.g. Dhantala Bazar Main Road, Near Bus Stand, Nadia..."
          class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
        ></textarea>
      </div>

      <!-- Internal Notes -->
      <div class="space-y-1.5">
        <label class="font-bold text-slate-700 block">Special Footwear Size & Preference Notes</label>
        <textarea
          v-model="form.notes"
          rows="2"
          placeholder="e.g. Prefers Ortho slippers size 8, frequent buyer..."
          class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
        ></textarea>
      </div>

      <!-- Action Buttons -->
      <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-3">
        <router-link
          to="/admin/customers"
          class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold transition-colors"
        >
          Cancel
        </router-link>

        <button
          type="submit"
          :disabled="submitting"
          class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black shadow-md shadow-red-600/20 transition-all flex items-center gap-2"
        >
          <span>💾</span>
          <span>{{ submitting ? 'Saving...' : isEdit ? 'Update Customer' : 'Save Customer' }}</span>
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../services/api';

const route = useRoute();
const router = useRouter();

const customerId = computed(() => route.params.id);
const isEdit = computed(() => Boolean(customerId.value));

const submitting = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

const form = reactive({
  name: '',
  mobile_number: '',
  email: '',
  address: '',
  city: '',
  pincode: '',
  notes: '',
});

async function fetchCustomerDetails() {
  if (!isEdit.value) return;
  try {
    const res = await api.get(`/customers/${customerId.value}`);
    const data = res.data || res;
    if (data) {
      form.name = data.name || '';
      form.mobile_number = data.mobile_number || data.phone || '';
      form.email = data.email || '';
      form.address = data.address || '';
      form.city = data.city || '';
      form.pincode = data.pincode || '';
      form.notes = data.notes || '';
    }
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Failed to load customer details for editing.';
  }
}

async function submitForm() {
  submitting.value = true;
  errorMessage.value = '';
  successMessage.value = '';

  try {
    if (isEdit.value) {
      await api.put(`/customers/${customerId.value}`, form);
      successMessage.value = 'Customer record updated successfully.';
    } else {
      const res = await api.post('/customers', form);
      const created = res.data || res;
      successMessage.value = 'New customer registered successfully.';
      setTimeout(() => {
        router.push(`/admin/customers/${created.id || ''}`);
      }, 1200);
      return;
    }

    setTimeout(() => {
      router.push('/admin/customers');
    }, 1200);
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Failed to save customer. Please verify input fields.';
  } finally {
    submitting.value = false;
  }
}

onMounted(() => {
  fetchCustomerDetails();
});
</script>
