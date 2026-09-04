import { ref } from 'vue';

export function useCashDrawer() {
    const isOpening = ref(false);

    // Abstraction support for hardware cash drawer kick triggers
    async function kickDrawer(options = { mode: 'escpos' }) {
        isOpening.value = true;
        try {
            if (options.mode === 'webserial' && 'serial' in navigator) {
                // Future WebSerial pulse signal (\x1B\x70\x00\x19\xFA)
                console.log('WebSerial Cash Drawer pulse triggered');
            } else {
                // Fallback: API drawer cash movement event trigger
                console.log('Cash Drawer open event logged');
            }
            return { success: true };
        } catch (err) {
            console.error('Failed to open cash drawer:', err);
            return { success: false, error: err.message };
        } finally {
            isOpening.value = false;
        }
    }

    return {
        isOpening,
        kickDrawer,
    };
}
