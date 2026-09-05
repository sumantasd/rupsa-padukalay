import { defineStore } from 'pinia';
import api from '../services/api';

export const useSettingsStore = defineStore('settings', {
    state: () => ({
        invoiceSettings: null,
        paymentMethods: [],
        activePaymentMethods: [],
        posSettings: null,
        numberSeries: null,
        generalSettings: null,
        loading: false,
        saving: false,
    }),

    actions: {
        // Invoice Settings
        async fetchInvoiceSettings() {
            this.loading = true;
            try {
                const res = await api.get('/settings/invoices');
                if (res.data) {
                    this.invoiceSettings = res.data;
                }
                return res.data;
            } catch (e) {
                console.error('Failed to fetch invoice settings:', e);
                throw e;
            } finally {
                this.loading = false;
            }
        },

        async saveInvoiceSettings(payload) {
            this.saving = true;
            try {
                const res = await api.post('/settings/invoices', payload);
                if (res.data) {
                    this.invoiceSettings = res.data;
                }
                return res;
            } catch (e) {
                throw e;
            } finally {
                this.saving = false;
            }
        },

        // Payment Methods
        async fetchPaymentMethods() {
            this.loading = true;
            try {
                const res = await api.get('/settings/payment-methods');
                if (res.data) {
                    this.paymentMethods = res.data;
                }
                return res.data;
            } catch (e) {
                console.error('Failed to fetch payment methods:', e);
                throw e;
            } finally {
                this.loading = false;
            }
        },

        async fetchActivePaymentMethods() {
            try {
                const res = await api.get('/payment-methods/active');
                if (res.data) {
                    this.activePaymentMethods = res.data;
                }
                return res.data;
            } catch (e) {
                console.error('Failed to fetch active payment methods:', e);
                throw e;
            }
        },

        async createPaymentMethod(payload) {
            this.saving = true;
            try {
                const res = await api.post('/settings/payment-methods', payload);
                await this.fetchPaymentMethods();
                return res;
            } catch (e) {
                throw e;
            } finally {
                this.saving = false;
            }
        },

        async updatePaymentMethod(id, payload) {
            this.saving = true;
            try {
                const res = await api.put(`/settings/payment-methods/${id}`, payload);
                await this.fetchPaymentMethods();
                return res;
            } catch (e) {
                throw e;
            } finally {
                this.saving = false;
            }
        },

        async togglePaymentMethodStatus(id) {
            this.saving = true;
            try {
                const res = await api.patch(`/settings/payment-methods/${id}/status`);
                await this.fetchPaymentMethods();
                return res;
            } catch (e) {
                throw e;
            } finally {
                this.saving = false;
            }
        },

        // POS Settings
        async fetchPosSettings() {
            this.loading = true;
            try {
                const res = await api.get('/settings/pos');
                if (res.data) {
                    this.posSettings = res.data;
                }
                return res.data;
            } catch (e) {
                console.error('Failed to fetch POS settings:', e);
                throw e;
            } finally {
                this.loading = false;
            }
        },

        async savePosSettings(payload) {
            this.saving = true;
            try {
                const res = await api.post('/settings/pos', payload);
                if (res.data) {
                    this.posSettings = res.data;
                }
                return res;
            } catch (e) {
                throw e;
            } finally {
                this.saving = false;
            }
        },

        // Number Series Settings
        async fetchNumberSeries() {
            this.loading = true;
            try {
                const res = await api.get('/settings/number-series');
                if (res.data) {
                    this.numberSeries = res.data;
                }
                return res.data;
            } catch (e) {
                console.error('Failed to fetch number series:', e);
                throw e;
            } finally {
                this.loading = false;
            }
        },

        async saveNumberSeries(payload) {
            this.saving = true;
            try {
                const res = await api.post('/settings/number-series', { series: payload });
                if (res.data) {
                    this.numberSeries = res.data;
                }
                return res;
            } catch (e) {
                throw e;
            } finally {
                this.saving = false;
            }
        },

        // General Settings
        async fetchGeneralSettings() {
            this.loading = true;
            try {
                const res = await api.get('/settings/general');
                if (res.data) {
                    this.generalSettings = res.data;
                }
                return res.data;
            } catch (e) {
                console.error('Failed to fetch general settings:', e);
                throw e;
            } finally {
                this.loading = false;
            }
        },

        async saveGeneralSettings(payload) {
            this.saving = true;
            try {
                const res = await api.post('/settings/general', payload);
                if (res.data) {
                    this.generalSettings = res.data;
                }
                return res;
            } catch (e) {
                throw e;
            } finally {
                this.saving = false;
            }
        },
    },
});
