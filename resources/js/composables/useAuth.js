import { computed } from 'vue';
import { useAuthStore } from '../stores/authStore';

export function useAuth() {
    const authStore = useAuthStore();

    const user = computed(() => authStore.user);
    const isAuthenticated = computed(() => authStore.isAuthenticated);
    const isSuperAdmin = computed(() => authStore.isSuperAdmin);

    function hasPermission(permissionName) {
        return authStore.hasPermission(permissionName);
    }

    return {
        user,
        isAuthenticated,
        isSuperAdmin,
        hasPermission,
        login: authStore.login,
        logout: authStore.logout,
        fetchMe: authStore.fetchMe,
    };
}
