import { ref, onMounted, onUnmounted } from 'vue';
import Dexie from 'dexie';

// Initialize Dexie IndexedDB instance for offline POS queueing
export const db = new Dexie('RupsaPosOfflineDB');
db.version(1).stores({
    offlineInvoices: '++id, client_trans_uuid, store_id, created_at, status',
    catalogCache: 'sku, product_name, barcode, unit_price',
});

export function useOfflineSync() {
    const isOnline = ref(navigator.onLine);
    const pendingCount = ref(0);
    const isSyncing = ref(false);

    function updateOnlineStatus() {
        isOnline.value = navigator.onLine;
    }

    async function checkPendingCount() {
        try {
            pendingCount.value = await db.offlineInvoices.where('status').equals('pending').count();
        } catch (e) {
            console.error('Dexie count error:', e);
        }
    }

    onMounted(() => {
        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);
        checkPendingCount();
    });

    onUnmounted(() => {
        window.removeEventListener('online', updateOnlineStatus);
        window.removeEventListener('offline', updateOnlineStatus);
    });

    return {
        isOnline,
        pendingCount,
        isSyncing,
        checkPendingCount,
    };
}
