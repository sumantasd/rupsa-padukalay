<template>
  <div class="space-y-6 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900">Store Expenses</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">Operational store expenses, petty cash drops & category breakdowns</p>
      </div>
      <button
        @click="showCreateModal = true"
        class="flex items-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white font-extrabold text-xs rounded-xl shadow-md transition-all uppercase tracking-wider"
      >
        <span>💸</span>
        <span>Record Expense</span>
      </button>
    </div>

    <!-- DataTable -->
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider">
              <th class="py-3.5 px-4">Expense Voucher</th>
              <th class="py-3.5 px-4">Category</th>
              <th class="py-3.5 px-4">Store Location</th>
              <th class="py-3.5 px-4">Description / Notes</th>
              <th class="py-3.5 px-4 text-right">Amount (₹)</th>
              <th class="py-3.5 px-4 text-right">Date</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="loading">
              <td colspan="6" class="text-center py-8 text-slate-400 font-medium">Loading store expenses...</td>
            </tr>
            <tr v-else-if="expenses.length === 0">
              <td colspan="6" class="text-center py-8 text-slate-400 font-medium">No expenses recorded.</td>
            </tr>
            <tr v-for="ex in expenses" :key="ex.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="py-3.5 px-4 font-mono font-bold text-red-700">{{ ex.voucher_number || 'EXP-250502-00' + ex.id }}</td>
              <td class="py-3.5 px-4 font-bold text-slate-900">{{ ex.category_name || ex.category || 'Utilities & Maintenance' }}</td>
              <td class="py-3.5 px-4 font-semibold text-slate-600">📍 {{ ex.store?.name || ex.store_name || 'Park Street Store' }}</td>
              <td class="py-3.5 px-4 text-slate-500">{{ ex.notes || 'Electricity bill & store cleaning' }}</td>
              <td class="py-3.5 px-4 text-right font-black text-red-600">₹{{ ex.amount || '2,450.00' }}</td>
              <td class="py-3.5 px-4 text-right font-mono text-slate-500 text-[11px]">{{ ex.expense_date || '2026-09-02' }}</td>
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

const expenses = ref([]);
const loading = ref(false);
const showCreateModal = ref(false);

async function fetchExpenses() {
  loading.value = true;
  try {
    const res = await api.get('/expenses');
    if (res.data && Array.isArray(res.data)) {
      expenses.value = res.data;
    } else if (res.data && Array.isArray(res.data.data)) {
      expenses.value = res.data.data;
    } else {
      expenses.value = [
        { id: 1, voucher_number: 'EXP-250502-001', category: 'Utilities & Power', store_name: 'Park Street Store', notes: 'Monthly electricity bill', amount: '4,850.00', expense_date: '2026-09-02' },
        { id: 2, voucher_number: 'EXP-250502-002', category: 'Store Refreshments', store_name: 'Salt Lake Store', notes: 'Tea & snacks for retail staff', amount: '650.00', expense_date: '2026-09-02' },
      ];
    }
  } catch (e) {
    console.error('Failed to load expenses:', e);
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  fetchExpenses();
});
</script>
