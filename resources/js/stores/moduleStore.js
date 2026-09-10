import { defineStore } from 'pinia';
import api from '../services/api';

export const useModuleStore = defineStore('moduleStore', {
  state: () => ({
    multiStore: false, // Default Single Store Mode (Multi Store OFF)
    loyalty: true,
    transfers: true,
    advancedReports: true,
    expenses: true,
    allowNewStoreCreation: false, // Default Allow New Store Creation OFF
    productFieldCategory: false, // Default OFF per business requirement
    productFieldGender: false, // Default OFF
    productFieldUpperMaterial: false, // Default OFF
    productFieldSoleMaterial: false, // Default OFF
    productFieldColor: false, // Default OFF
    loading: false,
    initialized: false,
  }),

  getters: {
    isModuleEnabled: (state) => (key) => {
      return Boolean(state[key]);
    },
  },

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
          this.allowNewStoreCreation = d.allow_new_store_creation === true || d.allow_new_store_creation === '1' || d.allow_new_store_creation === 1;
          this.productFieldCategory = d.product_field_category === true || d.product_field_category === '1' || d.product_field_category === 1;
          this.productFieldGender = d.product_field_gender === true || d.product_field_gender === '1' || d.product_field_gender === 1;
          this.productFieldUpperMaterial = d.product_field_upper_material === true || d.product_field_upper_material === '1' || d.product_field_upper_material === 1;
          this.productFieldSoleMaterial = d.product_field_sole_material === true || d.product_field_sole_material === '1' || d.product_field_sole_material === 1;
          this.productFieldColor = d.product_field_color === true || d.product_field_color === '1' || d.product_field_color === 1;
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
        if (moduleKey === 'allowNewStoreCreation') payload.allow_new_store_creation = isEnabled;
        if (moduleKey === 'productFieldCategory') payload.product_field_category = isEnabled;
        if (moduleKey === 'productFieldGender') payload.product_field_gender = isEnabled;
        if (moduleKey === 'productFieldUpperMaterial') payload.product_field_upper_material = isEnabled;
        if (moduleKey === 'productFieldSoleMaterial') payload.product_field_sole_material = isEnabled;
        if (moduleKey === 'productFieldColor') payload.product_field_color = isEnabled;

        const response = await api.put('/module-settings', payload);
        if (response.data) {
          const d = response.data;
          this.multiStore = d.multi_store_enabled === true || d.multi_store_enabled === '1' || d.multi_store_enabled === 1;
          this.loyalty = d.loyalty_enabled !== false;
          this.transfers = d.transfers_enabled !== false;
          this.advancedReports = d.advanced_reports_enabled !== false;
          this.expenses = d.expenses_enabled !== false;
          this.allowNewStoreCreation = d.allow_new_store_creation === true || d.allow_new_store_creation === '1' || d.allow_new_store_creation === 1;
          this.productFieldCategory = d.product_field_category === true || d.product_field_category === '1' || d.product_field_category === 1;
          this.productFieldGender = d.product_field_gender === true || d.product_field_gender === '1' || d.product_field_gender === 1;
          this.productFieldUpperMaterial = d.product_field_upper_material === true || d.product_field_upper_material === '1' || d.product_field_upper_material === 1;
          this.productFieldSoleMaterial = d.product_field_sole_material === true || d.product_field_sole_material === '1' || d.product_field_sole_material === 1;
          this.productFieldColor = d.product_field_color === true || d.product_field_color === '1' || d.product_field_color === 1;
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
