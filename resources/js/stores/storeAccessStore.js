import { defineStore } from 'pinia';
import api from '../services/api';

export const useStoreAccessStore = defineStore('storeAccess', {
    state: () => ({
        activeStoreId: Number(localStorage.getItem('rupsa_active_store_id')) || null,
        availableStores: JSON.parse(localStorage.getItem('rupsa_available_stores') || '[]'),
        usersMatrix: [],
        storesList: [],
        loading: false,
        saving: false,
    }),

    getters: {
        activeStore: (state) => {
            if (!state.activeStoreId) return state.availableStores[0] || null;
            return state.availableStores.find(s => s.id === state.activeStoreId) || state.availableStores[0] || null;
        },
    },

    actions: {
        setActiveStore(storeId) {
            this.activeStoreId = storeId;
            localStorage.setItem('rupsa_active_store_id', String(storeId));
        },

        async fetchAvailableStores() {
            this.loading = true;
            try {
                const res = await api.get('/stores');
                if (res.success && res.data) {
                    this.availableStores = res.data;
                    localStorage.setItem('rupsa_available_stores', JSON.stringify(this.availableStores));
                    if (!this.activeStoreId && this.availableStores.length > 0) {
                        this.setActiveStore(this.availableStores[0].id);
                    }
                }
            } catch (e) {
                console.error('Failed to fetch stores:', e);
            } finally {
                this.loading = false;
            }
        },

        async fetchMatrix() {
            this.loading = true;
            try {
                const res = await api.get('/store-access');
                if (res.data) {
                    this.usersMatrix = res.data.users || [];
                    this.storesList = res.data.stores || [];
                }
                return res.data;
            } catch (e) {
                console.error('Failed to fetch store access matrix:', e);
                throw e;
            } finally {
                this.loading = false;
            }
        },

        async assignUserStores(userId, payload) {
            this.saving = true;
            try {
                const res = await api.post(`/store-access/assign/${userId}`, payload);
                await this.fetchMatrix();
                return res;
            } catch (e) {
                throw e;
            } finally {
                this.saving = false;
            }
        },
    },
});
