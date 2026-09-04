import { defineStore } from 'pinia';
import api from '../services/api';

export const useModuleStore = defineStore('moduleStore', {
  state: () => ({
    multiStore: false, // Default Single Store Mode (Multi Store OFF)
    loyalty: true,
    transfers: true,
    advancedReports: true,
    expenses: true,
    loading: false,
    initialized: false,
  }),

  actions: {
    async fetchSettings() {
      this.loading = true;
      try {
        const response = await api.get('/module-settings');
        if (response.data) {
          const d = response.data;
          this.multiStore = false; // RUPSA PADUKALAYA operates strictly as Single-Store System (STR-001)
          this.loyalty = d.loyalty_enabled !== false;
          this.transfers = d.transfers_enabled !== false;
          this.advancedReports = d.advanced_reports_enabled !== false;
          this.expenses = d.expenses_enabled !== false;
        }
      } catch (err) {
        console.warn('Failed to fetch module settings from API, using default Single Store mode:', err);
      } finally {
        this.loading = false;
        this.initialized = true;
      }
    },

    async updateModule(moduleKey, isEnabled) {
      this.loading = true;
      try {
        const payload = {};
        if (moduleKey === 'multiStore') payload.multi_store_enabled = isEnabled;
        if (moduleKey === 'loyalty') payload.loyalty_enabled = isEnabled;
        if (moduleKey === 'transfers') payload.transfers_enabled = isEnabled;
        if (moduleKey === 'advancedReports') payload.advanced_reports_enabled = isEnabled;
        if (moduleKey === 'expenses') payload.expenses_enabled = isEnabled;

        const response = await api.put('/module-settings', payload);
        if (response.data) {
          const d = response.data;
          this.multiStore = d.multi_store_enabled === true || d.multi_store_enabled === '1' || d.multi_store_enabled === 1;
          this.loyalty = d.loyalty_enabled !== false;
          this.transfers = d.transfers_enabled !== false;
          this.advancedReports = d.advanced_reports_enabled !== false;
          this.expenses = d.expenses_enabled !== false;
        }
        return true;
      } catch (err) {
        console.error('Failed to update module settings:', err);
        throw err;
      } finally {
        this.loading = false;
      }
    },
  },
});
