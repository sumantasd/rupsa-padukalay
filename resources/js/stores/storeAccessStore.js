import { defineStore } from 'pinia';
import api from '../services/api';

export const useStoreAccessStore = defineStore('storeAccess', {
    state: () => ({
        activeStoreId: Number(localStorage.getItem('rupsa_active_store_id')) || null,
        availableStores: JSON.parse(localStorage.getItem('rupsa_available_stores') || '[]'),
        loading: false,
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
    },
});
