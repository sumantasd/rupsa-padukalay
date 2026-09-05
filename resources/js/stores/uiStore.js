import { defineStore } from 'pinia';

export const useUiStore = defineStore('ui', {
    state: () => ({
        isSidebarOpen: typeof window !== 'undefined' ? window.innerWidth >= 1024 : false,
        isMoreMenuOpen: false,
        isDarkMode: localStorage.getItem('rupsa_theme') === 'dark',
        toasts: [],
    }),

    actions: {
        toggleSidebar() {
            this.isSidebarOpen = !this.isSidebarOpen;
        },

        closeSidebar() {
            this.isSidebarOpen = false;
        },

        openMoreMenu() {
            this.isMoreMenuOpen = true;
        },

        closeMoreMenu() {
            this.isMoreMenuOpen = false;
        },

        toggleMoreMenu() {
            this.isMoreMenuOpen = !this.isMoreMenuOpen;
        },

        closeAllDrawers() {
            if (typeof window !== 'undefined' && window.innerWidth < 1024) {
                this.isSidebarOpen = false;
            }
            this.isMoreMenuOpen = false;
        },

        initTheme() {
            if (typeof window === 'undefined') return;
            const saved = localStorage.getItem('rupsa_theme');
            if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                this.isDarkMode = true;
                document.documentElement.classList.add('dark');
            } else {
                this.isDarkMode = false;
                document.documentElement.classList.remove('dark');
            }
        },

        toggleDarkMode() {
            this.isDarkMode = !this.isDarkMode;
            localStorage.setItem('rupsa_theme', this.isDarkMode ? 'dark' : 'light');
            if (this.isDarkMode) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        },

        addToast({ type = 'info', title = '', message = '', timeout = 4000 }) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, type, title, message });

            if (timeout > 0) {
                setTimeout(() => {
                    this.removeToast(id);
                }, timeout);
            }
        },

        removeToast(id) {
            this.toasts = this.toasts.filter(t => t.id !== id);
        },
    },
});
