<template>
  <div class="space-y-6 antialiased font-sans pb-16">
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <h1 class="text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
            <span>💸</span>
            <span>Expense Management</span>
          </h1>
          <span class="px-2.5 py-0.5 text-[10px] font-black uppercase bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300 rounded-full border border-red-300 dark:border-red-800">
            FINANCE & CASH FLOW
          </span>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-1">
          Record operational store expenses, track petty cash, manage category budgets, and review financial reports.
        </p>
      </div>

      <div class="flex items-center gap-2 flex-wrap">
        <button
          @click="openCategoryModal"
          class="px-3.5 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl font-bold text-xs transition-all cursor-pointer flex items-center gap-1.5 shrink-0"
        >
          <span>🏷️</span>
          <span>Categories</span>
        </button>

        <button
          @click="openCreateModal"
          class="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md shadow-red-600/20 transition-all uppercase tracking-wider flex items-center gap-1.5 cursor-pointer shrink-0"
        >
          <span>➕</span>
          <span>Record Expense</span>
        </button>
      </div>
    </div>

    <!-- Alert / Toast Messages -->
    <div v-if="alertMessage" :class="[
      'p-4 rounded-2xl border flex items-center justify-between gap-3 text-xs font-bold transition-all',
      alertType === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400' : 'bg-red-500/10 border-red-500/30 text-red-600 dark:text-red-400'
    ]">
      <div class="flex items-center gap-2">
        <span>{{ alertType === 'success' ? '✅' : '⚠️' }}</span>
        <span>{{ alertMessage }}</span>
      </div>
      <button @click="alertMessage = ''" class="hover:opacity-75 cursor-pointer">✕</button>
    </div>

    <!-- Metric KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs space-y-2">
        <div class="flex items-center justify-between text-xs text-slate-500 font-bold">
          <span>TOTAL EXPENSES</span>
          <span class="text-red-500 text-base">💰</span>
        </div>
        <div class="text-xl font-black font-mono text-slate-900 dark:text-white">
          ₹{{ formatCurrency(reportSummary.total_expenses) }}
        </div>
        <div class="text-[11px] text-slate-500 font-medium">
          {{ reportSummary.total_records || 0 }} expense entries recorded
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs space-y-2">
        <div class="flex items-center justify-between text-xs text-slate-500 font-bold">
          <span>CASH PAYMENTS</span>
          <span class="text-emerald-500 text-base">💵</span>
        </div>
        <div class="text-xl font-black font-mono text-emerald-600 dark:text-emerald-400">
          ₹{{ formatCurrency(getCashExpenseTotal()) }}
        </div>
        <div class="text-[11px] text-slate-500 font-medium">
          Linked to cash drawer & day closing
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs space-y-2">
        <div class="flex items-center justify-between text-xs text-slate-500 font-bold">
          <span>UPI & BANK PAYMENTS</span>
          <span class="text-indigo-500 text-base">🏦</span>
        </div>
        <div class="text-xl font-black font-mono text-indigo-600 dark:text-indigo-400">
          ₹{{ formatCurrency(getBankExpenseTotal()) }}
        </div>
        <div class="text-[11px] text-slate-500 font-medium">
          Direct account & digital transfers
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs space-y-2">
        <div class="flex items-center justify-between text-xs text-slate-500 font-bold">
          <span>ACTIVE CATEGORIES</span>
          <span class="text-amber-500 text-base">🏷️</span>
        </div>
        <div class="text-xl font-black font-mono text-amber-600 dark:text-amber-400">
          {{ categoriesList.length }}
        </div>
        <div class="text-[11px] text-slate-500 font-medium">
          Configurable store cost heads
        </div>
      </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs space-y-3">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs">
        <div class="flex flex-wrap items-center gap-2.5 flex-1">
          <!-- Search -->
          <div class="relative w-full sm:w-64">
            <input
              type="text"
              v-model="filters.search"
              @input="onSearch"
              placeholder="Search Expense #, Payee, Description..."
              class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl pl-9 pr-3 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-500"
            />
            <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
          </div>

          <!-- Category Filter -->
          <select
            v-model="filters.expense_category_id"
            @change="fetchExpenses(1)"
            class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none"
          >
            <option value="">All Categories</option>
            <option v-for="cat in categoriesList" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
          </select>

          <!-- Payment Method Filter -->
          <select
            v-model="filters.payment_method"
            @change="fetchExpenses(1)"
            class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none"
          >
            <option value="">All Payment Methods</option>
            <option value="cash">Cash</option>
            <option value="upi">UPI</option>
            <option value="bank_transfer">Bank Transfer</option>
            <option value="card">Card</option>
          </select>

          <!-- Status Filter -->
          <select
            v-model="filters.status"
            @change="fetchExpenses(1)"
            class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none"
          >
            <option value="">All Statuses</option>
            <option value="paid">Paid</option>
            <option value="draft">Draft</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>

        <!-- Refresh Button -->
        <button
          @click="fetchExpenses(1)"
          class="px-3.5 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl font-bold text-xs transition-colors cursor-pointer shrink-0"
        >
          🔄 Refresh
        </button>
      </div>
    </div>

    <!-- Expenses Table Container -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
      <!-- Loading State -->
      <div v-if="loading" class="p-12 text-center space-y-2">
        <div class="inline-block animate-spin text-2xl text-red-600">⌛</div>
        <p class="text-xs font-bold text-slate-500">Loading store expenses...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="expenses.length === 0" class="p-12 text-center space-y-3">
        <div class="text-4xl text-slate-400">💸</div>
        <div class="text-sm font-black text-slate-800 dark:text-slate-200">No expenses recorded</div>
        <p class="text-xs text-slate-500 font-medium">Click "Record Expense" to add your first operational cost entry.</p>
      </div>

      <!-- Table View -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs font-sans">
          <thead class="bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 text-[10px] uppercase font-black tracking-wider text-slate-500 dark:text-slate-400">
            <tr>
              <th class="py-3.5 px-4">Ref # & Date</th>
              <th class="py-3.5 px-4">Category</th>
              <th class="py-3.5 px-4">Payee / Vendor</th>
              <th class="py-3.5 px-4">Description / Notes</th>
              <th class="py-3.5 px-4">Payment Method</th>
              <th class="py-3.5 px-4 text-center">Status</th>
              <th class="py-3.5 px-4 text-right font-mono">Amount (₹)</th>
              <th class="py-3.5 px-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
            <tr v-for="ex in expenses" :key="ex.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
              <td class="py-3.5 px-4">
                <div class="font-mono font-black text-red-600 text-xs">{{ ex.expense_number }}</div>
                <div class="text-[10px] text-slate-400 font-medium">{{ formatDate(ex.expense_date) }}</div>
              </td>
              <td class="py-3.5 px-4">
                <span class="px-2 py-0.5 text-[10px] font-black rounded bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                  {{ ex.expense_category_name }}
                </span>
              </td>
              <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white">
                {{ ex.payee_name || 'N/A' }}
              </td>
              <td class="py-3.5 px-4 text-slate-600 dark:text-slate-400 max-w-xs truncate">
                {{ ex.description || ex.notes || 'N/A' }}
              </td>
              <td class="py-3.5 px-4 uppercase font-bold text-slate-700 dark:text-slate-300 text-[11px]">
                {{ formatPaymentMethod(ex.payment_method) }}
              </td>
              <td class="py-3.5 px-4 text-center">
                <span :class="getStatusBadgeClass(ex.status)">
                  {{ ex.status || 'paid' }}
                </span>
              </td>
              <td class="py-3.5 px-4 text-right font-mono font-black text-red-600 dark:text-red-400 text-sm whitespace-nowrap">
                ₹{{ formatCurrency(ex.amount) }}
              </td>
              <td class="py-3.5 px-4 text-right whitespace-nowrap space-x-2">
                <button
                  @click="openDetailModal(ex)"
                  class="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-bold cursor-pointer"
                >
                  👁️ View
                </button>
                <button
                  @click="openEditModal(ex)"
                  class="px-2.5 py-1 bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 text-indigo-700 dark:text-indigo-300 rounded-lg text-xs font-bold cursor-pointer"
                >
                  ✏️ Edit
                </button>
                <button
                  @click="deleteExpense(ex)"
                  class="px-2 py-1 bg-red-50 dark:bg-red-950/60 hover:bg-red-600 hover:text-white text-red-700 dark:text-red-300 rounded-lg text-xs font-bold cursor-pointer"
                >
                  🗑️
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.total > pagination.per_page" class="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs font-bold">
        <div class="text-slate-500">
          Showing Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} records)
        </div>
        <div class="flex items-center gap-2">
          <button
            @click="fetchExpenses(pagination.current_page - 1)"
            :disabled="pagination.current_page <= 1"
            class="px-3 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 rounded-lg disabled:opacity-40 cursor-pointer"
          >
            Previous
          </button>
          <button
            @click="fetchExpenses(pagination.current_page + 1)"
            :disabled="pagination.current_page >= pagination.last_page"
            class="px-3 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 rounded-lg disabled:opacity-40 cursor-pointer"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- CREATE / EDIT EXPENSE MODAL -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/80 backdrop-blur-xs overflow-y-auto pb-20 sm:pb-4">
      <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[85vh] sm:max-h-[90vh] my-auto">
        <div class="p-4 sm:p-5 bg-red-600 text-white flex items-center justify-between shrink-0">
          <h2 class="font-black text-sm uppercase tracking-wider">
            {{ isEditing ? 'Edit Expense Entry' : 'Record New Expense' }}
          </h2>
          <button @click="showModal = false" class="text-white hover:opacity-75 font-bold cursor-pointer text-base px-2 py-1">✕</button>
        </div>

        <form @submit.prevent="saveExpense" class="flex flex-col flex-1 overflow-hidden min-h-0">
          <div class="p-4 sm:p-6 space-y-4 text-xs overflow-y-auto flex-1">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Expense Category *</label>
                <select
                  v-model="form.expense_category_id"
                  required
                  class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 font-bold text-slate-900 dark:text-white"
                >
                  <option value="">Select Category</option>
                  <option v-for="cat in categoriesList" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>
              </div>

              <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Expense Date *</label>
                <input
                  type="date"
                  v-model="form.expense_date"
                  required
                  class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 font-bold text-slate-900 dark:text-white"
                />
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Amount (₹) *</label>
                <input
                  type="number"
                  step="0.01"
                  min="0.01"
                  v-model.number="form.amount"
                  required
                  placeholder="0.00"
                  class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 font-mono font-bold text-slate-900 dark:text-white"
                />
              </div>

              <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Payment Method *</label>
                <select
                  v-model="form.payment_method"
                  required
                  class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 font-bold text-slate-900 dark:text-white"
                >
                  <option value="cash">Cash</option>
                  <option value="upi">UPI</option>
                  <option value="bank_transfer">Bank Transfer</option>
                  <option value="card">Card</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Payee / Vendor Name</label>
                <input
                  type="text"
                  v-model="form.payee_name"
                  placeholder="e.g. CESC Bengal, Staff Name, Vendor"
                  class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 font-bold text-slate-900 dark:text-white"
                />
              </div>

              <div>
                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Voucher / Ref Number</label>
                <input
                  type="text"
                  v-model="form.voucher_number"
                  placeholder="Voucher or Bill Ref #"
                  class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 font-bold text-slate-900 dark:text-white"
                />
              </div>
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Description / Notes</label>
              <textarea
                v-model="form.description"
                rows="2"
                placeholder="Describe the purpose of this store expense..."
                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-2 font-bold text-slate-900 dark:text-white"
              ></textarea>
            </div>
          </div>

          <div class="p-4 bg-slate-50 dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-2 shrink-0">
            <button
              type="button"
              @click="showModal = false"
              class="px-4 py-2 bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 text-slate-700 dark:text-slate-300 rounded-xl font-bold cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="submitting"
              class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black shadow-md flex items-center gap-1.5 disabled:opacity-40 cursor-pointer"
            >
              <span v-if="submitting" class="animate-spin text-sm">⌛</span>
              <span>{{ isEditing ? 'Update Expense' : 'Save Expense' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- CATEGORY MANAGEMENT MODAL -->
    <div v-if="showCategoryModal" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/80 backdrop-blur-xs overflow-y-auto pb-20 sm:pb-4">
      <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[85vh] sm:max-h-[90vh] my-auto">
        <div class="p-4 sm:p-5 bg-slate-900 text-white flex items-center justify-between shrink-0">
          <h2 class="font-black text-sm uppercase tracking-wider flex items-center gap-2">
            <span>🏷️</span>
            <span>Manage Expense Categories</span>
          </h2>
          <button @click="showCategoryModal = false" class="text-white hover:opacity-75 font-bold cursor-pointer text-base px-2 py-1">✕</button>
        </div>

        <div class="p-4 sm:p-6 space-y-4 text-xs overflow-y-auto flex-1">
          <!-- Add Category Form -->
          <form @submit.prevent="saveNewCategory" class="flex gap-2">
            <input
              type="text"
              v-model="newCategoryName"
              placeholder="New Category Name (e.g. Packaging)"
              required
              class="flex-1 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2 font-bold text-slate-900 dark:text-white"
            />
            <button
              type="submit"
              :disabled="savingCategory"
              class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-black shrink-0 cursor-pointer"
            >
              + Add Category
            </button>
          </form>

          <!-- List Categories -->
          <div class="max-h-60 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800 border border-slate-200 dark:border-slate-800 rounded-xl">
            <div v-for="cat in categoriesList" :key="cat.id" class="p-3 flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-800/40">
              <div>
                <span class="font-bold text-slate-900 dark:text-white">{{ cat.name }}</span>
                <span v-if="cat.code" class="ml-2 font-mono text-[10px] text-slate-400">({{ cat.code }})</span>
              </div>
              <button
                @click="deleteCategory(cat)"
                class="text-red-500 hover:text-red-700 font-bold cursor-pointer text-xs"
              >
                🗑️ Delete
              </button>
            </div>
          </div>
        </div>

        <div class="p-4 bg-slate-50 dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 flex justify-end shrink-0">
          <button
            @click="showCategoryModal = false"
            class="px-4 py-2 bg-slate-900 text-white rounded-xl font-bold cursor-pointer"
          >
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- DETAIL VIEW MODAL -->
    <div v-if="showDetailModal" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/80 backdrop-blur-xs overflow-y-auto pb-20 sm:pb-4">
      <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xl w-full max-w-md overflow-hidden flex flex-col max-h-[85vh] sm:max-h-[90vh] my-auto">
        <div class="p-5 bg-slate-900 text-white flex items-center justify-between">
          <div>
            <h2 class="font-black text-sm uppercase tracking-wider">Expense Details</h2>
            <div class="font-mono text-red-400 text-xs font-bold">{{ activeExpenseDetail?.expense_number }}</div>
          </div>
          <button @click="showDetailModal = false" class="text-white hover:opacity-75 font-bold cursor-pointer">✕</button>
        </div>

        <div v-if="activeExpenseDetail" class="p-4 sm:p-6 space-y-3 text-xs overflow-y-auto flex-1">
          <div class="flex justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
            <span class="text-slate-500 font-bold">Category:</span>
            <span class="font-bold text-slate-900 dark:text-white">{{ activeExpenseDetail.expense_category_name }}</span>
          </div>

          <div class="flex justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
            <span class="text-slate-500 font-bold">Amount:</span>
            <span class="font-mono font-black text-red-600 text-sm">₹{{ formatCurrency(activeExpenseDetail.amount) }}</span>
          </div>

          <div class="flex justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
            <span class="text-slate-500 font-bold">Payment Method:</span>
            <span class="font-bold uppercase text-slate-800 dark:text-slate-200">{{ formatPaymentMethod(activeExpenseDetail.payment_method) }}</span>
          </div>

          <div class="flex justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
            <span class="text-slate-500 font-bold">Payee / Vendor:</span>
            <span class="font-bold text-slate-800 dark:text-slate-200">{{ activeExpenseDetail.payee_name || 'N/A' }}</span>
          </div>

          <div class="flex justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
            <span class="text-slate-500 font-bold">Expense Date:</span>
            <span class="font-bold font-mono text-slate-800 dark:text-slate-200">{{ formatDate(activeExpenseDetail.expense_date) }}</span>
          </div>

          <div class="flex justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
            <span class="text-slate-500 font-bold">Store Outlet:</span>
            <span class="font-bold text-slate-800 dark:text-slate-200">{{ activeExpenseDetail.store_name }}</span>
          </div>

          <div class="flex justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
            <span class="text-slate-500 font-bold">Created By:</span>
            <span class="font-bold text-slate-800 dark:text-slate-200">{{ activeExpenseDetail.created_by_name }}</span>
          </div>

          <div v-if="activeExpenseDetail.description" class="space-y-1">
            <span class="text-slate-500 font-bold block">Description:</span>
            <p class="p-3 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 font-medium">
              {{ activeExpenseDetail.description }}
            </p>
          </div>
        </div>

        <div class="p-4 bg-slate-50 dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 flex justify-end shrink-0">
          <button @click="showDetailModal = false" class="px-4 py-2 bg-slate-900 text-white rounded-xl font-bold cursor-pointer">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '../../services/api';

const expenses = ref([]);
const categoriesList = ref([]);
const loading = ref(false);
const submitting = ref(false);
const alertMessage = ref('');
const alertType = ref('success');

const reportSummary = reactive({
  total_expenses: 0,
  total_records: 0,
});

const filters = reactive({
  search: '',
  expense_category_id: '',
  payment_method: '',
  status: '',
});

const pagination = reactive({
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1,
});

// Create / Edit Modal state
const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = reactive({
  expense_category_id: '',
  amount: '',
  expense_date: new Date().toISOString().substring(0, 10),
  payment_method: 'cash',
  payee_name: '',
  description: '',
  voucher_number: '',
});

// Category Modal State
const showCategoryModal = ref(false);
const newCategoryName = ref('');
const savingCategory = ref(false);

// Detail Modal State
const showDetailModal = ref(false);
const activeExpenseDetail = ref(null);

async function fetchExpenses(page = 1) {
  loading.value = true;
  try {
    const res = await api.get('/expenses', {
      params: {
        page,
        per_page: pagination.per_page,
        search: filters.search || undefined,
        expense_category_id: filters.expense_category_id || undefined,
        payment_method: filters.payment_method || undefined,
        status: filters.status || undefined,
      },
    });

    const data = res.data?.items || res.items || [];
    expenses.value = data;
    if (res.data?.pagination) {
      Object.assign(pagination, res.data.pagination);
    }
  } catch (err) {
    showAlert('Failed to load expenses.', 'error');
  } finally {
    loading.value = false;
  }
}

async function fetchCategories() {
  try {
    const res = await api.get('/expense-categories');
    categoriesList.value = res.data || res || [];
  } catch (err) {
    console.error('Failed to load categories:', err);
  }
}

async function fetchReports() {
  try {
    const res = await api.get('/expenses/reports');
    if (res.data?.summary) {
      reportSummary.total_expenses = res.data.summary.total_expenses || 0;
      reportSummary.total_records = res.data.summary.total_records || 0;
    }
  } catch (err) {
    console.error('Failed to fetch expense reports:', err);
  }
}

function getCashExpenseTotal() {
  return expenses.value
    .filter(x => x.payment_method === 'cash')
    .reduce((sum, x) => sum + Number(x.amount || 0), 0);
}

function getBankExpenseTotal() {
  return expenses.value
    .filter(x => x.payment_method !== 'cash')
    .reduce((sum, x) => sum + Number(x.amount || 0), 0);
}

function onSearch() {
  fetchExpenses(1);
}

function openCreateModal() {
  isEditing.value = false;
  editingId.value = null;
  form.expense_category_id = categoriesList.value[0]?.id || '';
  form.amount = '';
  form.expense_date = new Date().toISOString().substring(0, 10);
  form.payment_method = 'cash';
  form.payee_name = '';
  form.description = '';
  form.voucher_number = '';
  showModal.value = true;
}

function openEditModal(ex) {
  isEditing.value = true;
  editingId.value = ex.id;
  form.expense_category_id = ex.expense_category_id;
  form.amount = ex.amount;
  form.expense_date = ex.expense_date || new Date().toISOString().substring(0, 10);
  form.payment_method = ex.payment_method || 'cash';
  form.payee_name = ex.payee_name || '';
  form.description = ex.description || '';
  form.voucher_number = ex.voucher_number || '';
  showModal.value = true;
}

async function saveExpense() {
  submitting.value = true;
  try {
    if (isEditing.value) {
      await api.put(`/expenses/${editingId.value}`, form);
      showAlert('Expense updated successfully.', 'success');
    } else {
      await api.post('/expenses', form);
      showAlert('Expense recorded successfully.', 'success');
    }
    showModal.value = false;
    fetchExpenses(1);
    fetchReports();
  } catch (err) {
    showAlert(err.response?.data?.message || 'Failed to save expense.', 'error');
  } finally {
    submitting.value = false;
  }
}

async function deleteExpense(ex) {
  if (!confirm(`Are you sure you want to delete expense "${ex.expense_number}"?`)) return;
  try {
    await api.delete(`/expenses/${ex.id}`);
    showAlert('Expense entry archived successfully.', 'success');
    fetchExpenses(pagination.current_page);
    fetchReports();
  } catch (err) {
    showAlert('Failed to delete expense entry.', 'error');
  }
}

function openCategoryModal() {
  newCategoryName.value = '';
  showCategoryModal.value = true;
}

async function saveNewCategory() {
  if (!newCategoryName.value.trim()) return;
  savingCategory.value = true;
  try {
    await api.post('/expense-categories', { name: newCategoryName.value.trim() });
    newCategoryName.value = '';
    fetchCategories();
    showAlert('Category added successfully.', 'success');
  } catch (err) {
    showAlert(err.response?.data?.message || 'Failed to add category.', 'error');
  } finally {
    savingCategory.value = false;
  }
}

async function deleteCategory(cat) {
  if (!confirm(`Delete category "${cat.name}"?`)) return;
  try {
    await api.delete(`/expense-categories/${cat.id}`);
    fetchCategories();
    showAlert('Category deleted successfully.', 'success');
  } catch (err) {
    showAlert(err.response?.data?.message || 'Failed to delete category.', 'error');
  }
}

function openDetailModal(ex) {
  activeExpenseDetail.value = ex;
  showDetailModal.value = true;
}

function showAlert(msg, type = 'success') {
  alertMessage.value = msg;
  alertType.value = type;
}

function formatCurrency(val) {
  return Number(val || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(dateStr) {
  if (!dateStr) return 'N/A';
  return new Date(dateStr).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatPaymentMethod(pm) {
  if (!pm) return 'Cash';
  return String(pm).replace('_', ' ');
}

function getStatusBadgeClass(st) {
  if (st === 'cancelled') return 'px-2 py-0.5 rounded text-[10px] font-black uppercase bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300';
  if (st === 'draft') return 'px-2 py-0.5 rounded text-[10px] font-black uppercase bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300';
  return 'px-2 py-0.5 rounded text-[10px] font-black uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300';
}

onMounted(() => {
  fetchExpenses();
  fetchCategories();
  fetchReports();
});
</script>
