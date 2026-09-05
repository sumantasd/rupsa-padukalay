import { defineStore } from 'pinia';
import api from '../services/api';

export const useRoleStore = defineStore('roles', {
    state: () => ({
        roles: [],
        permissionGroups: [],
        currentRole: null,
        loading: false,
        saving: false,
        error: null,
    }),

    actions: {
        async fetchRoles() {
            this.loading = true;
            this.error = null;
            try {
                const res = await api.get('/roles');
                if (res.data) {
                    this.roles = res.data;
                }
                return res;
            } catch (e) {
                this.error = e.response?.data?.message || 'Failed to fetch roles.';
                throw e;
            } finally {
                this.loading = false;
            }
        },

        async fetchPermissions() {
            try {
                const res = await api.get('/permissions');
                if (res.data) {
                    this.permissionGroups = res.data;
                }
                return res.data;
            } catch (e) {
                console.error('Failed to fetch permissions:', e);
                throw e;
            }
        },

        async fetchRoleDetails(id) {
            this.loading = true;
            try {
                const res = await api.get(`/roles/${id}`);
                if (res.data) {
                    this.currentRole = res.data;
                }
                return res.data;
            } catch (e) {
                this.error = e.response?.data?.message || 'Failed to fetch role details.';
                throw e;
            } finally {
                this.loading = false;
            }
        },

        async createRole(payload) {
            this.saving = true;
            try {
                const res = await api.post('/roles', payload);
                await this.fetchRoles();
                return res;
            } catch (e) {
                throw e;
            } finally {
                this.saving = false;
            }
        },

        async updateRole(id, payload) {
            this.saving = true;
            try {
                const res = await api.put(`/roles/${id}`, payload);
                await this.fetchRoles();
                return res;
            } catch (e) {
                throw e;
            } finally {
                this.saving = false;
            }
        },

        async deleteRole(id) {
            this.saving = true;
            try {
                const res = await api.delete(`/roles/${id}`);
                await this.fetchRoles();
                return res;
            } catch (e) {
                throw e;
            } finally {
                this.saving = false;
            }
        },
    },
});
