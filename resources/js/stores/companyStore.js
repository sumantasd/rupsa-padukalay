import { defineStore } from 'pinia';
import api from '../services/api';

export const useCompanyStore = defineStore('company', {
  state: () => ({
    profile: {
      company_name: 'RUPSA PADUKALAYA',
      tagline: 'STEP INTO COMFORT',
      legal_name: 'RUPSA PADUKALAYA RETAIL PRIVATE LIMITED',
      gstin: '19AAECR1234F1Z5',
      phone: '+91 9735125112',
      email: 'support@rupsapadukalaya.com',
      website: 'https://rupsapadukalaya.com',
      address_line1: 'DHANTALA BAZAR',
      address_line2: 'DHANTALA, RANAGHAT - II',
      city: 'NADIA',
      state: 'WEST BENGAL',
      pincode: '741202',
      country: 'INDIA',
      primary_logo_path: null,
      primary_logo_url: null,
      white_logo_path: null,
      white_logo_url: null,
    },
    loading: false,
    initialized: false,
  }),

  getters: {
    primaryLogo: (state) => state.profile?.primary_logo_url || null,
    whiteLogo: (state) => state.profile?.white_logo_url || state.profile?.primary_logo_url || null,
    companyName: (state) => state.profile?.company_name || 'RUPSA PADUKALAYA',
    tagline: (state) => state.profile?.tagline || 'STEP INTO COMFORT',
  },

  actions: {
    async fetchCompanyProfile() {
      this.loading = true;
      try {
        const res = await api.get('/public/company-profile');
        const data = res.data?.data || res.data || res;
        if (data) {
          Object.assign(this.profile, data);
        }
        this.initialized = true;
      } catch (err) {
        console.error('Failed to fetch company profile:', err);
      } finally {
        this.loading = false;
      }
    },

    async updateProfile(payload) {
      const res = await api.post('/settings/company', payload);
      const data = res.data?.data || res.data || res;
      if (data) {
        Object.assign(this.profile, data);
      }
      return data;
    },

    async uploadPrimaryLogo(file) {
      const formData = new FormData();
      formData.append('logo', file);
      const res = await api.post('/settings/company/primary-logo', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      const data = res.data?.data || res.data || res;
      if (data) {
        Object.assign(this.profile, data);
      }
      return data;
    },

    async deletePrimaryLogo() {
      const res = await api.delete('/settings/company/primary-logo');
      const data = res.data?.data || res.data || res;
      if (data) {
        Object.assign(this.profile, data);
      }
      return data;
    },

    async uploadWhiteLogo(file) {
      const formData = new FormData();
      formData.append('logo', file);
      const res = await api.post('/settings/company/white-logo', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      const data = res.data?.data || res.data || res;
      if (data) {
        Object.assign(this.profile, data);
      }
      return data;
    },

    async deleteWhiteLogo() {
      const res = await api.delete('/settings/company/white-logo');
      const data = res.data?.data || res.data || res;
      if (data) {
        Object.assign(this.profile, data);
      }
      return data;
    },
  },
});
