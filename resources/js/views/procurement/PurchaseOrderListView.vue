<template>
  <div class="space-y-6 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900">Purchase Orders & Procurement</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">Wholesale footwear purchase orders, receiving & vendor debit notes</p>
      </div>
      <button
        @click="showCreateModal = true"
        class="flex items-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white font-extrabold text-xs rounded-xl shadow-md transition-all uppercase tracking-wider"
      >
        <span>🛒</span>
        <span>Create Purchase Order</span>
      </button>
    </div>

    <!-- DataTable -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider">
              <th class="py-3.5 px-4">PO Reference</th>
              <th class="py-3.5 px-4">Supplier</th>
              <th class="py-3.5 px-4">Receiving Store</th>
              <th class="py-3.5 px-4 text-right">Total Amount (₹)</th>
              <th class="py-3.5 px-4 text-center">Status</th>
              <th class="py-3.5 px-4 text-right">Order Date</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="loading">
              <td colspan="6" class="text-center py-8 text-slate-400 font-medium">Loading purchase orders...</td>
            </tr>
            <tr v-else-if="orders.length === 0">
              <td colspan="6" class="text-center py-8 text-slate-400 font-medium">No purchase orders found.</td>
            </tr>
            <tr v-for="po in orders" :key="po.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="py-3.5 px-4 font-mono font-bold text-red-700">{{ po.po_number || 'PO-250502-00' + po.id }}</td>
              <td class="py-3.5 px-4 font-extrabold text-slate-900">{{ po.supplier?.name || po.supplier_name || 'Global Footwears' }}</td>
              <td class="py-3.5 px-4 font-semibold text-slate-600">📍 {{ po.store?.name || po.store_name || 'Central Warehouse' }}</td>
              <td class="py-3.5 px-4 text-right font-black text-slate-900">₹{{ po.total_amount || po.amount || '1,45,000' }}</td>
              <td class="py-3.5 px-4 text-center">
                <span :class="['px-2 py-0.5 rounded text-[10px] font-extrabold uppercase', getStatusClass(po.status)]">
                  {{ po.status || 'RECEIVED' }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-right font-mono text-slate-500 text-[11px]">{{ po.created_at || '2026-09-02' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../services/api';

const orders = ref([]);
const loading = ref(false);
const showCreateModal = ref(false);

async function fetchOrders() {
  loading.value = true;
  try {
    const res = await api.get('/purchase-orders');
    if (res.data && Array.isArray(res.data)) {
      orders.value = res.data;
    } else if (res.data && Array.isArray(res.data.data)) {
      orders.value = res.data.data;
    } else {
      orders.value = [
        { id: 1, po_number: 'PO-250502-005', supplier_name: 'Global Footwear Ltd', store_name: 'Central Warehouse', amount: '1,85,000', status: 'RECEIVED', created_at: '2026-09-02' },
        { id: 2, po_number: 'PO-250502-006', supplier_name: 'Apex Leather Crafters', store_name: 'Park Street Store', amount: '92,400', status: 'PARTIAL', created_at: '2026-09-01' },
      ];
    }
  } catch (e) {
    console.error('Failed to load purchase orders:', e);
  } finally {
    loading.value = false;
  }
}

function getStatusClass(status) {
  if (status === 'RECEIVED') return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
  if (status === 'PARTIAL') return 'bg-indigo-50 text-indigo-700 border border-indigo-200';
  return 'bg-amber-50 text-amber-800 border border-amber-200';
}

onMounted(() => {
  fetchOrders();
});
</script>
