import { defineStore } from 'pinia';
import api from '../services/api';

function getValidInitialToken() {
    const raw = localStorage.getItem('rupsa_token');
    if (!raw || raw === 'undefined' || raw === 'null') {
        localStorage.removeItem('rupsa_token');
        return null;
    }
    return raw;
}

function getValidInitialUser() {
    const raw = localStorage.getItem('rupsa_user');
    if (!raw || raw === 'undefined' || raw === 'null') {
        localStorage.removeItem('rupsa_user');
        return null;
    }
    try {
        return JSON.parse(raw);
    } catch (e) {
        localStorage.removeItem('rupsa_user');
        return null;
    }
}

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: getValidInitialUser(),
        token: getValidInitialToken(),
        loading: false,
    }),

    getters: {
        isAuthenticated: (state) => !!state.token && !!state.user,

        isSuperAdmin: (state) => {
            if (!state.user) return false;
            if (state.user.is_super_admin) return true;
            if (!state.user.roles || !Array.isArray(state.user.roles)) return false;
            return state.user.roles.some(role => {
                if (typeof role === 'string') {
                    return role === 'Super Admin' || role === 'super_admin';
                }
                if (role && role.name) {
                    return role.name === 'Super Admin' || role.name === 'super_admin';
                }
                return false;
            });
        },

        permissions: (state) => {
            if (!state.user) return [];
            if (Array.isArray(state.user.permissions) && state.user.permissions.length > 0) {
                return state.user.permissions;
            }
            const perms = new Set();
            if (Array.isArray(state.user.roles)) {
                state.user.roles.forEach(role => {
                    if (role && Array.isArray(role.permissions)) {
                        role.permissions.forEach(p => {
                            if (typeof p === 'string') perms.add(p);
                            else if (p && p.name) perms.add(p.name);
                        });
                    }
                });
            }
            return Array.from(perms);
        },

        userStores: (state) => {
            if (!state.user) return [];
            return state.user.stores || [];
        },
    },

    actions: {
        async login(credentials) {
            this.loading = true;
            try {
                const res = await api.post('/auth/login', credentials);
                if (res.success && res.data) {
                    const token = res.data.access_token || res.data.token;
                    if (!token) {
                        throw new Error('Authentication token not received from server.');
                    }
                    this.token = token;
                    this.user = res.data.user;
                    localStorage.setItem('rupsa_token', this.token);
                    localStorage.setItem('rupsa_user', JSON.stringify(this.user));
                    return res;
                }
                throw new Error(res.message || 'Login failed');
            } finally {
                this.loading = false;
            }
        },

        async logout() {
            try {
                if (this.token && this.token !== 'undefined') {
                    await api.post('/auth/logout');
                }
            } catch (e) {
                // Ignore network errors during logout
            } finally {
                this.token = null;
                this.user = null;
                localStorage.removeItem('rupsa_token');
                localStorage.removeItem('rupsa_user');
            }
        },

        async fetchMe() {
            if (!this.token || this.token === 'undefined') return;
            try {
                const res = await api.get('/auth/me');
                if (res.success && res.data) {
                    this.user = res.data;
                    localStorage.setItem('rupsa_user', JSON.stringify(this.user));
                }
            } catch (e) {
                if (e.status === 401) {
                    this.logout();
                }
            }
        },

        hasPermission(permissionName) {
            if (this.isSuperAdmin) return true;
            if (!permissionName) return true;
            return this.permissions.includes(permissionName);
        },
    },
});
