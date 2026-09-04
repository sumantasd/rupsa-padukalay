<template>
  <div class="space-y-6 antialiased font-sans max-w-5xl mx-auto pb-16">
    <!-- Breadcrumb & Header -->
    <div class="flex items-center justify-between border-b border-slate-200 pb-4">
      <div>
        <div class="hidden md:flex items-center gap-2 text-xs font-bold text-slate-500 mb-1">
          <router-link to="/admin/suppliers" class="hover:text-red-600">Suppliers</router-link>
          <span>/</span>
          <span class="text-slate-900">{{ isEdit ? 'Edit Supplier' : 'Register New Supplier' }}</span>
        </div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
          {{ isEdit ? `Edit Supplier: ${form.name}` : 'Register New Footwear Supplier' }}
        </h1>
      </div>

      <router-link
        to="/admin/suppliers"
        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-colors"
      >
        ← Back to Directory
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

    <!-- Main Supplier Form Card -->
    <form @submit.prevent="submitForm" class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200/90 shadow-sm space-y-8 text-xs">
      
      <!-- SECTION 1: BASIC & CONTACT INFORMATION -->
      <div class="space-y-5">
        <div class="border-b border-slate-100 pb-3">
          <h2 class="font-black text-base text-slate-900 flex items-center gap-2">
            <span>🏢</span>
            <span>Basic & Contact Information</span>
          </h2>
          <p class="text-slate-500 font-medium mt-0.5">Primary vendor entity name, phone numbers, tax identifiers & location.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          <!-- Supplier Name -->
          <div class="space-y-1.5">
            <label class="font-black text-slate-800 block">
              Supplier Contact / Person Name <span class="text-red-600">*</span>
            </label>
            <input
              v-model="form.name"
              type="text"
              required
              placeholder="e.g. Apex Footwear Industries"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <!-- Supplier Code -->
          <div class="space-y-1.5">
            <label class="font-bold text-slate-700 block">
              Supplier Code <span class="text-slate-400 font-normal">(Auto-generated if empty)</span>
            </label>
            <input
              v-model="form.code"
              type="text"
              placeholder="e.g. SUP-0001"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-mono font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <!-- Company Name -->
          <div class="space-y-1.5">
            <label class="font-bold text-slate-700 block">Registered Company Name</label>
            <input
              v-model="form.company_name"
              type="text"
              placeholder="e.g. Apex Leather Crafters Ltd"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <!-- Primary Phone -->
          <div class="space-y-1.5">
            <label class="font-black text-slate-800 block">
              Primary Mobile / Phone <span class="text-red-600">*</span>
            </label>
            <input
              v-model="form.phone"
              type="text"
              required
              placeholder="e.g. +91 98310 11223"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-mono font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <!-- Alternate Mobile -->
          <div class="space-y-1.5">
            <label class="font-bold text-slate-700 block">Alternate Mobile</label>
            <input
              v-model="form.alternate_mobile"
              type="text"
              placeholder="e.g. +91 97351 25112"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <!-- Email -->
          <div class="space-y-1.5">
            <label class="font-bold text-slate-700 block">Email Address</label>
            <input
              v-model="form.email"
              type="email"
              placeholder="e.g. vendor@apexfootwear.com"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <!-- GSTIN -->
          <div class="space-y-1.5">
            <label class="font-bold text-slate-700 block">GSTIN</label>
            <input
              v-model="form.gstin"
              type="text"
              placeholder="e.g. 19AAAAA0000A1Z5"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-mono text-slate-900 uppercase focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <!-- PAN Number -->
          <div class="space-y-1.5">
            <label class="font-bold text-slate-700 block">PAN Number</label>
            <input
              v-model="form.pan"
              type="text"
              placeholder="e.g. ABCDE1234F"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-mono text-slate-900 uppercase focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <!-- City -->
          <div class="space-y-1.5">
            <label class="font-bold text-slate-700 block">City / Hub</label>
            <input
              v-model="form.city"
              type="text"
              placeholder="e.g. Kolkata / Kanpur / Agra"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <!-- State -->
          <div class="space-y-1.5">
            <label class="font-bold text-slate-700 block">State</label>
            <input
              v-model="form.state"
              type="text"
              placeholder="e.g. West Bengal"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <!-- PIN Code -->
          <div class="space-y-1.5">
            <label class="font-bold text-slate-700 block">PIN Code</label>
            <input
              v-model="form.pincode"
              type="text"
              placeholder="e.g. 700105"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <!-- Active Status -->
          <div class="space-y-1.5">
            <label class="font-bold text-slate-700 block">Supplier Account Status</label>
            <select
              v-model="form.is_active"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            >
              <option :value="true">Active Vendor</option>
              <option :value="false">Deactivated Vendor</option>
            </select>
          </div>
        </div>

        <!-- Full Factory Address -->
        <div class="space-y-1.5">
          <label class="font-bold text-slate-700 block">Factory / Billing Address</label>
          <textarea
            v-model="form.address"
            rows="2"
            placeholder="e.g. Plot No 45, Leather Goods Complex, Bantala, Kolkata 700105..."
            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
          ></textarea>
        </div>
      </div>

      <!-- SECTION 2: FINANCIAL SETTINGS & OPENING BALANCE -->
      <div class="space-y-5 pt-4 border-t border-slate-100">
        <div class="border-b border-slate-100 pb-3">
          <h2 class="font-black text-base text-slate-900 flex items-center gap-2">
            <span>💰</span>
            <span>Financial Terms & Opening Ledger Balance</span>
          </h2>
          <p class="text-slate-500 font-medium mt-0.5">Define vendor credit limit, payment cycle terms and starting opening balance position.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
          <!-- Opening Balance -->
          <div class="space-y-1.5">
            <label class="font-bold text-slate-700 block">Opening Balance (₹)</label>
            <input
              v-model.number="form.opening_balance"
              type="number"
              step="0.01"
              placeholder="e.g. 0.00"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-mono font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <!-- Opening Balance Type -->
          <div class="space-y-1.5">
            <label class="font-bold text-slate-700 block">Balance Type</label>
            <select
              v-model="form.opening_balance_type"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            >
              <option value="payable">Payable (We owe vendor)</option>
              <option value="advance">Advance (Vendor owes us)</option>
            </select>
          </div>

          <!-- Payment Terms -->
          <div class="space-y-1.5">
            <label class="font-bold text-slate-700 block">Payment Terms</label>
            <input
              v-model="form.payment_terms"
              type="text"
              placeholder="e.g. Net 30 Days"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>

          <!-- Credit Limit -->
          <div class="space-y-1.5">
            <label class="font-bold text-slate-700 block">Credit Limit (₹)</label>
            <input
              v-model.number="form.credit_limit"
              type="number"
              step="0.01"
              placeholder="e.g. 200000"
              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-mono font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
            />
          </div>
        </div>
      </div>

      <!-- SECTION 3: NOTES & MEMO -->
      <div class="space-y-2 pt-4 border-t border-slate-100">
        <label class="font-bold text-slate-700 block">Vendor Notes & Preferred Footwear Articles</label>
        <textarea
          v-model="form.notes"
          rows="2"
          placeholder="e.g. Specializes in Gents Leather Formal Shoes, Gents Loafers & School Shoes..."
          class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:bg-white"
        ></textarea>
      </div>

      <!-- Action Buttons -->
      <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-3">
        <router-link
          to="/admin/suppliers"
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
          <span>{{ submitting ? 'Saving...' : isEdit ? 'Update Supplier' : 'Save Supplier' }}</span>
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

const supplierId = computed(() => route.params.id);
const isEdit = computed(() => Boolean(supplierId.value));

const submitting = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

const form = reactive({
  name: '',
  code: '',
  company_name: '',
  phone: '',
  alternate_mobile: '',
  email: '',
  gstin: '',
  pan: '',
  address: '',
  city: '',
  state: 'West Bengal',
  pincode: '',
  opening_balance: 0,
  opening_balance_type: 'payable',
  payment_terms: 'Net 30 Days',
  credit_limit: 0,
  notes: '',
  is_active: true,
});

async function fetchSupplierDetails() {
  if (!isEdit.value) return;
  try {
    const res = await api.get(`/suppliers/${supplierId.value}`);
    const data = res.data || res;
    if (data) {
      form.name = data.name || '';
      form.code = data.code || '';
      form.company_name = data.company_name || '';
      form.phone = data.phone || '';
      form.alternate_mobile = data.alternate_mobile || '';
      form.email = data.email || '';
      form.gstin = data.gstin || '';
      form.pan = data.pan || '';
      form.address = data.address || '';
      form.city = data.city || '';
      form.state = data.state || 'West Bengal';
      form.pincode = data.pincode || '';
      form.opening_balance = data.opening_balance || 0;
      form.opening_balance_type = data.opening_balance_type || 'payable';
      form.payment_terms = data.payment_terms || 'Net 30 Days';
      form.credit_limit = data.credit_limit || 0;
      form.notes = data.notes || '';
      form.is_active = data.is_active !== undefined ? Boolean(data.is_active) : true;
    }
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Failed to load supplier details for editing.';
  }
}

async function submitForm() {
  submitting.value = true;
  errorMessage.value = '';
  successMessage.value = '';

  try {
    if (isEdit.value) {
      await api.put(`/suppliers/${supplierId.value}`, form);
      successMessage.value = 'Supplier record updated successfully.';
    } else {
      const res = await api.post('/suppliers', form);
      const created = res.data || res;
      successMessage.value = 'New supplier registered successfully.';
      setTimeout(() => {
        router.push(`/admin/suppliers/${created.id || ''}`);
      }, 1000);
      return;
    }

    setTimeout(() => {
      router.push('/admin/suppliers');
    }, 1000);
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Failed to save supplier. Please verify input fields.';
  } finally {
    submitting.value = false;
  }
}

onMounted(() => {
  fetchSupplierDetails();
});
</script>
