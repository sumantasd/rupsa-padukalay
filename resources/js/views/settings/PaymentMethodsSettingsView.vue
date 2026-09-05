<template>
  <div class="space-y-6 max-w-5xl mx-auto pb-16 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 p-6 rounded-2xl border border-slate-800 shadow-xl">
      <div class="flex items-center gap-3">
        <div class="h-10 w-10 rounded-xl bg-purple-600/20 text-purple-400 flex items-center justify-center font-bold text-xl border border-purple-500/30">
          💳
        </div>
        <div>
          <h1 class="text-xl font-extrabold text-white">Payment Methods Configuration</h1>
          <p class="text-xs text-slate-400 mt-0.5">
            Manage POS payment gateways, UPI reference requirements & counter payment modes
          </p>
        </div>
      </div>
      <button
        @click="openAddModal"
        class="inline-flex items-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-red-600/20 transition-all cursor-pointer shrink-0"
      >
        <span class="text-base leading-none">+</span>
        <span>Add Payment Method</span>
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="settingsStore.loading" class="py-12 flex justify-center items-center">
      <div class="flex items-center gap-3 text-slate-400 text-sm">
        <span class="h-5 w-5 border-2 border-red-500 border-t-transparent rounded-full animate-spin"></span>
        Loading payment methods...
      </div>
    </div>

    <!-- Payment Methods Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div
        v-for="method in settingsStore.paymentMethods"
        :key="method.id"
        class="p-5 rounded-2xl border border-slate-800 bg-slate-900/80 flex items-start justify-between gap-4 hover:border-slate-700 transition-all shadow-lg"
      >
        <div class="flex items-start gap-3">
          <div class="h-10 w-10 rounded-xl bg-slate-950 flex items-center justify-center text-xl shrink-0 border border-slate-800">
            {{ method.icon || '💰' }}
          </div>
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <h3 class="font-extrabold text-sm text-white">{{ method.name }}</h3>
              <span class="px-2 py-0.5 text-[9px] font-mono font-bold uppercase rounded bg-slate-950 text-indigo-400 border border-slate-800">
                {{ method.code }}
              </span>
            </div>
            <p class="text-xs text-slate-400">{{ method.description || 'No description provided.' }}</p>
            <div class="flex items-center gap-2 pt-1">
              <span
                v-if="method.requires_reference"
                class="px-2 py-0.5 text-[9px] font-extrabold rounded bg-amber-950/60 text-amber-300 border border-amber-800/60"
              >
                Requires Reference No.
              </span>
            </div>
          </div>
        </div>

        <div class="flex flex-col items-end gap-3 shrink-0">
          <!-- Active Toggle Switch -->
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <span class="text-[10px] font-extrabold uppercase" :class="method.is_active ? 'text-emerald-400' : 'text-slate-500'">
              {{ method.is_active ? 'Active' : 'Disabled' }}
            </span>
            <input
              type="checkbox"
              :checked="method.is_active"
              @change="toggleStatus(method)"
              class="rounded border-slate-700 bg-slate-950 text-red-600 focus:ring-red-500 cursor-pointer"
            />
          </label>

          <button
            @click="openEditModal(method)"
            class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs rounded-xl transition-all cursor-pointer"
          >
            Edit
          </button>
        </div>
      </div>
    </div>

    <!-- Modal (Teleport to Body) -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-[999] bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden my-auto">
          <!-- Modal Header -->
          <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-950 shrink-0">
            <h2 class="text-base font-black text-white">
              {{ isEditing ? 'Edit Payment Method' : 'Add New Payment Method' }}
            </h2>
            <button @click="showModal = false" class="text-slate-400 hover:text-white text-lg font-bold">✕</button>
          </div>

          <!-- Modal Body -->
          <div class="p-6 space-y-4 text-xs">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Method Name *</label>
              <input
                v-model="form.name"
                type="text"
                placeholder="e.g. PhonePe QR Code"
                class="w-full bg-slate-950 border border-slate-800 text-white rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-red-500"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Method Code *</label>
              <input
                v-model="form.code"
                type="text"
                placeholder="e.g. PHONEPE_QR"
                class="w-full bg-slate-950 border border-slate-800 text-white font-mono rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-red-500 uppercase"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Icon Emoji</label>
              <input
                v-model="form.icon"
                type="text"
                placeholder="📱"
                class="w-full bg-slate-950 border border-slate-800 text-white rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-red-500"
              />
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Description</label>
              <input
                v-model="form.description"
                type="text"
                placeholder="Brief summary of payment method"
                class="w-full bg-slate-950 border border-slate-800 text-white rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-red-500"
              />
            </div>

            <div class="space-y-2 pt-2">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="form.requires_reference" class="rounded border-slate-700 bg-slate-950 text-red-600 focus:ring-red-500" />
                <span class="font-bold text-slate-300">Require Transaction Reference / UTR Number</span>
              </label>

              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="form.is_active" class="rounded border-slate-700 bg-slate-950 text-red-600 focus:ring-red-500" />
                <span class="font-bold text-slate-300">Active & Enabled for POS Checkout</span>
              </label>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="px-6 py-4 border-t border-slate-800 bg-slate-950 flex items-center justify-end gap-3">
            <button
              @click="showModal = false"
              type="button"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-xl cursor-pointer"
            >
              Cancel
            </button>
            <button
              @click="submitForm"
              :disabled="settingsStore.saving"
              type="button"
              class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-xl shadow-lg cursor-pointer disabled:opacity-50"
            >
              Save Method
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useSettingsStore } from '../../stores/settingsStore';
import { useNotificationStore } from '../../stores/notificationStore';

const settingsStore = useSettingsStore();
const notificationStore = useNotificationStore();

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = reactive({
  name: '',
  code: '',
  icon: '💳',
  description: '',
  requires_reference: false,
  is_active: true,
});

onMounted(async () => {
  await settingsStore.fetchPaymentMethods();
});

function openAddModal() {
  isEditing.value = false;
  editingId.value = null;
  form.name = '';
  form.code = '';
  form.icon = '💳';
  form.description = '';
  form.requires_reference = false;
  form.is_active = true;
  showModal.value = true;
}

function openEditModal(method) {
  isEditing.value = true;
  editingId.value = method.id;
  form.name = method.name;
  form.code = method.code;
  form.icon = method.icon || '💳';
  form.description = method.description || '';
  form.requires_reference = !!method.requires_reference;
  form.is_active = !!method.is_active;
  showModal.value = true;
}

async function toggleStatus(method) {
  try {
    await settingsStore.togglePaymentMethodStatus(method.id);
    notificationStore.showNotification(`Payment method '${method.name}' status toggled.`, 'success');
  } catch (e) {
    const msg = e.response?.data?.message || 'Failed to toggle status.';
    notificationStore.showNotification(msg, 'error');
  }
}

async function submitForm() {
  if (!form.name.trim() || !form.code.trim()) {
    notificationStore.showNotification('Method name and code are required.', 'error');
    return;
  }

  try {
    if (isEditing.value) {
      await settingsStore.updatePaymentMethod(editingId.value, form);
      notificationStore.showNotification('Payment method updated successfully.', 'success');
    } else {
      await settingsStore.createPaymentMethod(form);
      notificationStore.showNotification('Payment method created successfully.', 'success');
    }
    showModal.value = false;
  } catch (e) {
    const msg = e.response?.data?.message || 'Failed to save payment method.';
    notificationStore.showNotification(msg, 'error');
  }
}
</script>
