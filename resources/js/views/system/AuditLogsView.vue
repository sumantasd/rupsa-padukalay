<template>
  <div class="space-y-6 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 p-6 rounded-2xl border border-slate-800 shadow-xl">
      <div class="flex items-center gap-3">
        <div class="h-10 w-10 rounded-xl bg-amber-600/20 text-amber-400 flex items-center justify-center font-bold text-xl border border-amber-500/30">
          📜
        </div>
        <div>
          <h1 class="text-xl font-extrabold text-white">System Audit Trail & Event Logs</h1>
          <p class="text-xs text-slate-400 mt-0.5">
            Immutable tracking log of administrative, operational & security actions across all store outlets
          </p>
        </div>
      </div>
      <div class="px-3.5 py-2 bg-emerald-950/60 text-emerald-400 border border-emerald-800/60 rounded-xl text-xs font-bold flex items-center gap-2">
        <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
        <span>Audit Trail Active & Immutable</span>
      </div>
    </div>

    <!-- Filters & Search Bar -->
    <div class="bg-slate-900/80 p-4 rounded-2xl border border-slate-800 space-y-3">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <!-- Search Input -->
        <div class="relative lg:col-span-2">
          <input
            v-model="filters.search"
            @input="debounceSearch"
            type="text"
            placeholder="Search UUID, notes, or target entity..."
            class="w-full bg-slate-950 border border-slate-800 text-slate-200 placeholder-slate-500 text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-red-500"
          />
          <span class="absolute right-3 top-2.5 text-slate-500 text-xs">🔍</span>
        </div>

        <!-- Module Filter -->
        <div>
          <select
            v-model="filters.module"
            @change="fetchLogs(1)"
            class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-red-500"
          >
            <option value="">All System Modules</option>
            <option value="access_control">Access Control & Roles</option>
            <option value="store_access">Store Access</option>
            <option value="company_profile">Company Profile</option>
            <option value="invoice_settings">Invoice Settings</option>
            <option value="payment_methods">Payment Methods</option>
            <option value="pos_settings">POS Settings</option>
            <option value="number_series">Number Series</option>
            <option value="general_settings">General Settings</option>
            <option value="module_settings">Module Settings</option>
            <option value="printer_settings">Printer Settings</option>
            <option value="pos_sale">POS Billing</option>
            <option value="sales_return">Sales Returns</option>
            <option value="inventory">Inventory Movements</option>
          </select>
        </div>

        <!-- Event Type Filter -->
        <div>
          <select
            v-model="filters.event_type"
            @change="fetchLogs(1)"
            class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-red-500"
          >
            <option value="">All Action Events</option>
            <option value="role_created">Role Created</option>
            <option value="role_updated">Role Updated</option>
            <option value="role_deleted">Role Deleted</option>
            <option value="store_access_updated">Store Access Updated</option>
            <option value="company_profile_updated">Company Profile Updated</option>
            <option value="invoice_settings_updated">Invoice Settings Updated</option>
            <option value="payment_method_created">Payment Method Created</option>
            <option value="pos_settings_updated">POS Settings Updated</option>
            <option value="general_settings_updated">General Settings Updated</option>
            <option value="module_settings_updated">Module Settings Updated</option>
          </select>
        </div>

        <!-- Date Range From -->
        <div>
          <input
            v-model="filters.date_from"
            @change="fetchLogs(1)"
            type="date"
            class="w-full bg-slate-950 border border-slate-800 text-slate-200 text-xs rounded-xl px-3.5 py-2.5 focus:outline-none focus:border-red-500"
          />
        </div>
      </div>
    </div>

    <!-- Logs Table -->
    <div class="bg-slate-900/80 rounded-2xl border border-slate-800 shadow-xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="bg-slate-950 border-b border-slate-800 text-slate-400 font-extrabold uppercase text-[10px] tracking-wider">
              <th class="py-3.5 px-4">Log UUID / Date</th>
              <th class="py-3.5 px-4">User</th>
              <th class="py-3.5 px-4">Module</th>
              <th class="py-3.5 px-4">Action Event</th>
              <th class="py-3.5 px-4">Reason / Notes</th>
              <th class="py-3.5 px-4">IP Address</th>
              <th class="py-3.5 px-4 text-right">Details</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/60 text-slate-300">
            <tr v-if="loading">
              <td colspan="7" class="py-12 text-center text-slate-400">
                <span class="inline-flex items-center gap-2">
                  <span class="h-4 w-4 border-2 border-red-500 border-t-transparent rounded-full animate-spin"></span>
                  Fetching audit logs...
                </span>
              </td>
            </tr>
            <tr v-else-if="logs.length === 0">
              <td colspan="7" class="py-12 text-center text-slate-500">
                No audit log entries match your filter criteria.
              </td>
            </tr>
            <tr v-for="log in logs" :key="log.id" class="hover:bg-slate-800/40 transition-colors">
              <td class="py-3 px-4">
                <div class="font-mono text-[11px] font-bold text-red-400">{{ log.audit_uuid ? log.audit_uuid.substring(0, 8) + '...' : '#LOG-' + log.id }}</div>
                <div class="text-[10px] text-slate-500">{{ formatDate(log.created_at) }}</div>
              </td>
              <td class="py-3 px-4">
                <div class="font-bold text-white">{{ log.user?.name || log.user_name || 'System / Admin' }}</div>
                <div class="text-[10px] text-slate-400 font-mono">{{ log.user?.email || '' }}</div>
              </td>
              <td class="py-3 px-4">
                <span class="px-2 py-0.5 text-[10px] font-extrabold rounded bg-slate-950 text-indigo-400 border border-slate-800 uppercase">
                  {{ log.module }}
                </span>
              </td>
              <td class="py-3 px-4">
                <span class="font-mono text-xs font-bold text-slate-200">{{ log.event_type }}</span>
              </td>
              <td class="py-3 px-4">
                <span class="text-xs text-slate-300 line-clamp-1">{{ log.reason_notes || '—' }}</span>
              </td>
              <td class="py-3 px-4 font-mono text-[11px] text-slate-400">
                {{ log.ip_address || '127.0.0.1' }}
              </td>
              <td class="py-3 px-4 text-right">
                <button
                  @click="openDetailModal(log)"
                  class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-[11px] rounded-lg transition-all cursor-pointer"
                >
                  View Diff
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.total > 0" class="px-6 py-4 border-t border-slate-800 bg-slate-950 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400">
        <div>
          Showing page <strong class="text-white">{{ pagination.current_page }}</strong> of <strong class="text-white">{{ pagination.last_page }}</strong> (Total <strong class="text-white">{{ pagination.total }}</strong> logs)
        </div>
        <div class="flex items-center gap-2">
          <button
            @click="fetchLogs(pagination.current_page - 1)"
            :disabled="pagination.current_page <= 1"
            class="px-3 py-1.5 bg-slate-900 border border-slate-800 text-slate-200 font-bold rounded-lg disabled:opacity-40 cursor-pointer"
          >
            ← Previous
          </button>
          <button
            @click="fetchLogs(pagination.current_page + 1)"
            :disabled="pagination.current_page >= pagination.last_page"
            class="px-3 py-1.5 bg-slate-900 border border-slate-800 text-slate-200 font-bold rounded-lg disabled:opacity-40 cursor-pointer"
          >
            Next →
          </button>
        </div>
      </div>
    </div>

    <!-- Detail Modal (Teleport to Body) -->
    <Teleport to="body">
      <div v-if="selectedLog" class="fixed inset-0 z-[999] bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-3xl max-h-[90vh] flex flex-col shadow-2xl overflow-hidden my-auto">
          <!-- Modal Header -->
          <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-950 shrink-0">
            <div>
              <h2 class="text-base font-black text-white">Audit Log Record Details</h2>
              <p class="text-xs text-slate-400 font-mono">UUID: {{ selectedLog.audit_uuid || selectedLog.id }}</p>
            </div>
            <button @click="selectedLog = null" class="text-slate-400 hover:text-white text-lg font-bold">✕</button>
          </div>

          <!-- Modal Body -->
          <div class="p-6 overflow-y-auto space-y-6 flex-1 text-xs">
            <!-- Metadata Summary Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-4 rounded-xl bg-slate-950 border border-slate-800">
              <div>
                <span class="block text-[10px] text-slate-500 font-bold uppercase">User</span>
                <span class="font-bold text-white">{{ selectedLog.user?.name || 'Admin' }}</span>
              </div>
              <div>
                <span class="block text-[10px] text-slate-500 font-bold uppercase">Module</span>
                <span class="font-bold text-indigo-400 uppercase">{{ selectedLog.module }}</span>
              </div>
              <div>
                <span class="block text-[10px] text-slate-500 font-bold uppercase">Event Type</span>
                <span class="font-mono font-bold text-emerald-400">{{ selectedLog.event_type }}</span>
              </div>
              <div>
                <span class="block text-[10px] text-slate-500 font-bold uppercase">IP Address</span>
                <span class="font-mono text-slate-300">{{ selectedLog.ip_address || '127.0.0.1' }}</span>
              </div>
            </div>

            <!-- Notes -->
            <div v-if="selectedLog.reason_notes" class="p-3 rounded-xl bg-slate-950/70 border border-slate-800">
              <span class="block text-[10px] text-slate-500 font-bold uppercase mb-1">Reason / Notes:</span>
              <p class="text-slate-200 font-medium">{{ selectedLog.reason_notes }}</p>
            </div>

            <!-- State Diff (Before & After) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Before State -->
              <div class="space-y-1.5">
                <span class="text-[11px] font-extrabold text-amber-400 uppercase tracking-wider block">Before State (Previous)</span>
                <pre class="p-3 rounded-xl bg-slate-950 border border-slate-800 text-[11px] font-mono text-amber-200/90 overflow-x-auto max-h-60 leading-relaxed">{{ formatJson(selectedLog.before_state) }}</pre>
              </div>

              <!-- After State -->
              <div class="space-y-1.5">
                <span class="text-[11px] font-extrabold text-emerald-400 uppercase tracking-wider block">After State (New Saved)</span>
                <pre class="p-3 rounded-xl bg-slate-950 border border-slate-800 text-[11px] font-mono text-emerald-200/90 overflow-x-auto max-h-60 leading-relaxed">{{ formatJson(selectedLog.after_state) }}</pre>
              </div>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="px-6 py-4 border-t border-slate-800 bg-slate-950 flex items-center justify-end">
            <button
              @click="selectedLog = null"
              type="button"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-xl cursor-pointer"
            >
              Close Record
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '../../services/api';

const logs = ref([]);
const loading = ref(false);
const selectedLog = ref(null);

const filters = reactive({
  search: '',
  module: '',
  event_type: '',
  date_from: '',
});

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  total: 0,
});

let searchTimer = null;
function debounceSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    fetchLogs(1);
  }, 400);
}

async function fetchLogs(page = 1) {
  loading.value = true;
  try {
    const params = {
      page,
      search: filters.search,
      module: filters.module,
      event_type: filters.event_type,
      date_from: filters.date_from,
    };
    const res = await api.get('/audit-logs', { params });
    if (res.data && res.data.items) {
      logs.value = res.data.items;
      pagination.current_page = res.data.pagination.current_page;
      pagination.last_page = res.data.pagination.last_page;
      pagination.total = res.data.pagination.total;
    } else if (res.data && Array.isArray(res.data)) {
      logs.value = res.data;
    }
  } catch (e) {
    console.error('Failed to load audit logs:', e);
  } finally {
    loading.value = false;
  }
}

function formatDate(dt) {
  if (!dt) return '—';
  return new Date(dt).toLocaleString();
}

function formatJson(val) {
  if (!val) return 'No state data recorded.';
  if (typeof val === 'string') {
    try {
      val = JSON.parse(val);
    } catch (e) {
      return val;
    }
  }
  return JSON.stringify(val, null, 2);
}

async function openDetailModal(log) {
  try {
    const res = await api.get(`/audit-logs/${log.id}`);
    selectedLog.value = res.data || log;
  } catch (e) {
    selectedLog.value = log;
  }
}

onMounted(() => {
  fetchLogs(1);
});
</script>
