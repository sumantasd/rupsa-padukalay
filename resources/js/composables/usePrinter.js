import { ref } from 'vue';

export function usePrinter() {
    const isPrinting = ref(false);
    const printerStatus = ref('ready'); // ready, printing, error

    // Abstraction support for dual output: Browser Print / WebUSB / WebSerial
    async function printReceipt(receiptData, options = { mode: 'html' }) {
        isPrinting.value = true;
        printerStatus.value = 'printing';

        try {
            if (options.mode === 'webusb' && 'usb' in navigator) {
                // Future WebUSB ESC/POS direct raw pulse
                console.log('WebUSB receipt printing trigger:', receiptData);
            } else if (options.mode === 'webserial' && 'serial' in navigator) {
                // Future WebSerial ESC/POS direct raw pulse
                console.log('WebSerial receipt printing trigger:', receiptData);
            } else {
                // Default: HTML/PDF iframe print
                window.print();
            }
            printerStatus.value = 'ready';
            return { success: true };
        } catch (err) {
            printerStatus.value = 'error';
            console.error('Print failure:', err);
            return { success: false, error: err.message };
        } finally {
            isPrinting.value = false;
        }
    }

    return {
        isPrinting,
        printerStatus,
        printReceipt,
    };
}
