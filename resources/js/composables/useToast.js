import { useUiStore } from '../stores/uiStore';

export function useToast() {
    const uiStore = useUiStore();

    function success(message, title = 'Success') {
        uiStore.addToast({ type: 'success', title, message });
    }

    function error(message, title = 'Error') {
        uiStore.addToast({ type: 'error', title, message });
    }

    function warning(message, title = 'Warning') {
        uiStore.addToast({ type: 'warning', title, message });
    }

    function info(message, title = 'Info') {
        uiStore.addToast({ type: 'info', title, message });
    }

    return {
        success,
        error,
        warning,
        info,
        toasts: uiStore.toasts,
        removeToast: uiStore.removeToast,
    };
}
