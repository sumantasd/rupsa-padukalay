<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-16 antialiased font-sans">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Thermal Printer & Bill Settings</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">
          Central control panel for 80mm/58mm thermal receipts across Sales Invoices, Returns, Exchanges, POS & Payments for Main Outlet (STR-001).
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          @click="saveSettings"
          :disabled="saving"
          class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs shadow-md shadow-red-600/20 transition-all uppercase tracking-wider flex items-center gap-1.5"
        >
          <span>💾</span>
          <span>{{ saving ? 'Saving...' : 'Save Settings' }}</span>
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
      <!-- LEFT COLUMN: CONTROLS & TOGGLES (7 Cols) -->
      <div class="lg:col-span-7 space-y-6">
        <!-- 1. BASIC & HARDWARE SETTINGS -->
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
          <div class="px-5 py-3 bg-slate-900 text-white flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="text-base">🛠️</span>
              <h2 class="text-xs font-extrabold uppercase tracking-wider">1. Basic & Hardware Settings</h2>
            </div>
            <span class="text-[10px] font-bold bg-slate-800 px-2 py-0.5 rounded text-slate-300">Hardware Config</span>
          </div>

          <div class="p-5 space-y-4 text-xs">
            <!-- Printer Enabled -->
            <div class="flex items-center justify-between py-1">
              <div>
                <span class="font-black text-slate-900 block">Enable Thermal Printing</span>
                <p class="text-[11px] text-slate-500">Master switch to enable receipt generation and printer dialogs.</p>
              </div>
              <input type="checkbox" v-model="form.printer_enabled" class="w-5 h-5 accent-red-600 rounded cursor-pointer" />
            </div>

            <!-- Printer Width -->
            <div class="flex items-center justify-between py-1 border-t border-slate-100 pt-3">
              <div>
                <span class="font-black text-slate-900 block">Thermal Paper Width</span>
                <p class="text-[11px] text-slate-500">Standard 80mm POS receipt printers vs 58mm compact printers.</p>
              </div>
              <select v-model="form.printer_width" class="bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 font-bold text-slate-900">
                <option value="80mm">80mm (Standard POS)</option>
                <option value="58mm">58mm (Compact Mobile)</option>
              </select>
            </div>

            <!-- Auto Print Switches -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 border-t border-slate-100 pt-3">
              <label class="flex items-center gap-2 p-2 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
                <input type="checkbox" v-model="form.auto_print_sale" class="w-4 h-4 accent-red-600 rounded" />
                <span class="font-bold text-slate-800 text-[11px]">Auto Print Sales Sale</span>
              </label>
              <label class="flex items-center gap-2 p-2 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
                <input type="checkbox" v-model="form.auto_print_return" class="w-4 h-4 accent-red-600 rounded" />
                <span class="font-bold text-slate-800 text-[11px]">Auto Print Return</span>
              </label>
              <label class="flex items-center gap-2 p-2 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
                <input type="checkbox" v-model="form.auto_print_exchange" class="w-4 h-4 accent-red-600 rounded" />
                <span class="font-bold text-slate-800 text-[11px]">Auto Print Exchange</span>
              </label>
              <label class="flex items-center gap-2 p-2 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
                <input type="checkbox" v-model="form.auto_print_payment" class="w-4 h-4 accent-red-600 rounded" />
                <span class="font-bold text-slate-800 text-[11px]">Auto Print Payment</span>
              </label>
            </div>
          </div>
        </div>

        <!-- 2. STORE PRINTER LOGO MANAGEMENT -->
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
          <div class="px-5 py-3 bg-slate-900 text-white flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="text-base">🖼️</span>
              <h2 class="text-xs font-extrabold uppercase tracking-wider">2. Store Printer Logo</h2>
            </div>
            <span class="text-[10px] font-bold bg-slate-800 px-2 py-0.5 rounded text-slate-300">Logo Branding</span>
          </div>

          <div class="p-5 space-y-4 text-xs">
            <div class="flex flex-col sm:flex-row items-center gap-6 bg-slate-50 p-4 rounded-xl border border-slate-200">
              <!-- Preview Box -->
              <div class="w-28 h-28 bg-white rounded-2xl border-2 border-dashed border-slate-300 flex items-center justify-center p-2 relative shadow-xs shrink-0 overflow-hidden">
                <img
                  v-if="form.printer_logo_url"
                  :src="form.printer_logo_url"
                  alt="Current Store Logo"
                  class="max-w-full max-h-full object-contain filter grayscale contrast-125"
                />
                <div v-else class="text-center text-slate-400">
                  <div class="text-2xl mb-1">🖼️</div>
                  <span class="text-[10px] font-bold block">No Custom Logo</span>
                  <span class="text-[9px] block">Using RP Default</span>
                </div>
              </div>

              <!-- Action & Instructions -->
              <div class="space-y-3 flex-1 text-center sm:text-left">
                <div>
                  <span class="font-black text-slate-900 block text-sm">Store Printer Logo</span>
                  <p class="text-[11px] text-slate-500 font-medium">
                    Upload transparent PNG, JPG, JPEG or WEBP (Max 2MB). Recommended size: ~300×300 px.
                  </p>
                </div>

                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                  <!-- File Input Button -->
                  <label class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs cursor-pointer shadow-xs transition-all flex items-center gap-1.5">
                    <span>{{ uploadingLogo ? 'Uploading...' : form.printer_logo_url ? 'Replace Logo' : 'Upload Store Logo' }}</span>
                    <input type="file" ref="logoFileInput" accept="image/png,image/jpeg,image/jpg,image/webp" @change="handleLogoUpload" class="hidden" :disabled="uploadingLogo" />
                  </label>

                  <!-- Delete Button -->
                  <button
                    v-if="form.printer_logo_url"
                    @click="handleRemoveLogo"
                    :disabled="uploadingLogo"
                    class="px-4 py-2 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 font-bold rounded-xl text-xs transition-all flex items-center gap-1"
                  >
                    <span>🗑️ Remove Logo</span>
                  </button>
                </div>

                <!-- Toggle switch -->
                <label class="flex items-center gap-2 cursor-pointer pt-1 inline-flex">
                  <input type="checkbox" v-model="form.show_logo" class="w-4 h-4 accent-red-600 rounded" />
                  <span class="font-bold text-slate-800 text-xs">Enable "Show Logo on Bills"</span>
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- 3. STORE HEADER SETTINGS -->
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
          <div class="px-5 py-3 bg-red-600 text-white flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="text-base">🏬</span>
              <h2 class="text-xs font-extrabold uppercase tracking-wider">3. Store Header Settings</h2>
            </div>
            <span class="text-[10px] font-bold bg-red-700 px-2 py-0.5 rounded text-red-100">Header Branding</span>
          </div>

          <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
            <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
              <span class="font-bold text-slate-800">Show Logo Icon</span>
              <input type="checkbox" v-model="form.show_logo" class="w-4 h-4 accent-red-600 rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
              <span class="font-bold text-slate-800">Show Store Name</span>
              <input type="checkbox" v-model="form.show_store_name" class="w-4 h-4 accent-red-600 rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
              <span class="font-bold text-slate-800">Show Outlet Name (STR-001)</span>
              <input type="checkbox" v-model="form.show_outlet_name" class="w-4 h-4 accent-red-600 rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
              <span class="font-bold text-slate-800">Show Address</span>
              <input type="checkbox" v-model="form.show_address" class="w-4 h-4 accent-red-600 rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
              <span class="font-bold text-slate-800">Show Phone Number</span>
              <input type="checkbox" v-model="form.show_phone" class="w-4 h-4 accent-red-600 rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
              <span class="font-bold text-slate-800">Show GSTIN</span>
              <input type="checkbox" v-model="form.show_gstin" class="w-4 h-4 accent-red-600 rounded" />
            </label>
          </div>
        </div>

        <!-- 3. CUSTOMER DETAILS SETTINGS -->
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
          <div class="px-5 py-3 bg-slate-800 text-white flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="text-base">👤</span>
              <h2 class="text-xs font-extrabold uppercase tracking-wider">3. Customer Details Settings</h2>
            </div>
          </div>

          <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
            <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
              <span class="font-bold text-slate-800">Customer Name</span>
              <input type="checkbox" v-model="form.show_customer_name" class="w-4 h-4 accent-red-600 rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
              <span class="font-bold text-slate-800">Mobile Number</span>
              <input type="checkbox" v-model="form.show_customer_mobile" class="w-4 h-4 accent-red-600 rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
              <span class="font-bold text-slate-800">Address</span>
              <input type="checkbox" v-model="form.show_customer_address" class="w-4 h-4 accent-red-600 rounded" />
            </label>
          </div>
        </div>

        <!-- 4. PRODUCT LINE DETAILS SETTINGS -->
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
          <div class="px-5 py-3 bg-slate-800 text-white flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="text-base">👟</span>
              <h2 class="text-xs font-extrabold uppercase tracking-wider">4. Product Details Settings</h2>
            </div>
            <span class="text-[10px] font-bold bg-slate-700 px-2 py-0.5 rounded text-slate-200">IND Sizing</span>
          </div>

          <div class="p-5 grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
            <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
              <span class="font-bold text-slate-800">Article #</span>
              <input type="checkbox" v-model="form.show_article_number" class="w-4 h-4 accent-red-600 rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
              <span class="font-bold text-slate-800">Product Name</span>
              <input type="checkbox" v-model="form.show_product_name" class="w-4 h-4 accent-red-600 rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
              <span class="font-bold text-slate-800">Brand</span>
              <input type="checkbox" v-model="form.show_brand" class="w-4 h-4 accent-red-600 rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
              <span class="font-bold text-slate-800">Color</span>
              <input type="checkbox" v-model="form.show_color" class="w-4 h-4 accent-red-600 rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
              <span class="font-bold text-slate-800">Size (IND)</span>
              <input type="checkbox" v-model="form.show_size" class="w-4 h-4 accent-red-600 rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
              <span class="font-bold text-slate-800">Quantity</span>
              <input type="checkbox" v-model="form.show_quantity" class="w-4 h-4 accent-red-600 rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
              <span class="font-bold text-slate-800">MRP</span>
              <input type="checkbox" v-model="form.show_mrp" class="w-4 h-4 accent-red-600 rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
              <span class="font-bold text-slate-800">Selling Price</span>
              <input type="checkbox" v-model="form.show_selling_price" class="w-4 h-4 accent-red-600 rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
              <span class="font-bold text-slate-800">Line Discount</span>
              <input type="checkbox" v-model="form.show_line_discount" class="w-4 h-4 accent-red-600 rounded" />
            </label>
          </div>
        </div>

        <!-- 5. FINANCIAL SUMMARY SETTINGS -->
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
          <div class="px-5 py-3 bg-slate-800 text-white flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="text-base">💰</span>
              <h2 class="text-xs font-extrabold uppercase tracking-wider">5. Financial Summary Settings</h2>
            </div>
          </div>

          <div class="p-5 grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
            <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
              <span class="font-bold text-slate-800">Subtotal</span>
              <input type="checkbox" v-model="form.show_subtotal" class="w-4 h-4 accent-red-600 rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
              <span class="font-bold text-slate-800">Discount</span>
              <input type="checkbox" v-model="form.show_discount" class="w-4 h-4 accent-red-600 rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
              <span class="font-bold text-slate-800">GST Tax</span>
              <input type="checkbox" v-model="form.show_tax" class="w-4 h-4 accent-red-600 rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
              <span class="font-bold text-slate-800">Grand Total</span>
              <input type="checkbox" v-model="form.show_grand_total" class="w-4 h-4 accent-red-600 rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
              <span class="font-bold text-slate-800">Payment Method</span>
              <input type="checkbox" v-model="form.show_payment_method" class="w-4 h-4 accent-red-600 rounded" />
            </label>
            <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
              <span class="font-bold text-slate-800">Due Amount</span>
              <input type="checkbox" v-model="form.show_due_amount" class="w-4 h-4 accent-red-600 rounded" />
            </label>
          </div>
        </div>

        <!-- 6. FOOTER & QR SETTINGS -->
        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
          <div class="px-5 py-3 bg-slate-800 text-white flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="text-base">📝</span>
              <h2 class="text-xs font-extrabold uppercase tracking-wider">6. Footer & QR Code Settings</h2>
            </div>
          </div>

          <div class="p-5 space-y-4 text-xs">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
                <span class="font-bold text-slate-800">Show Thank You Message</span>
                <input type="checkbox" v-model="form.show_thank_you_message" class="w-4 h-4 accent-red-600 rounded" />
              </label>
              <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
                <span class="font-bold text-slate-800">Show Return Policy</span>
                <input type="checkbox" v-model="form.show_return_policy" class="w-4 h-4 accent-red-600 rounded" />
              </label>
              <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
                <span class="font-bold text-slate-800">Show QR Code</span>
                <input type="checkbox" v-model="form.show_qr_code" class="w-4 h-4 accent-red-600 rounded" />
              </label>
              <label class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
                <span class="font-bold text-slate-800">Show Developer Credit</span>
                <input type="checkbox" v-model="form.show_developed_by_credit" class="w-4 h-4 accent-red-600 rounded" />
              </label>
            </div>

            <!-- Thank you text -->
            <div class="space-y-1">
              <label class="font-black text-slate-800 block">Thank You Message Text</label>
              <input
                type="text"
                v-model="form.thank_you_message"
                class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 font-medium text-slate-900"
              />
            </div>

            <!-- Return policy text -->
            <div class="space-y-1">
              <label class="font-black text-slate-800 block">Return Policy Text</label>
              <input
                type="text"
                v-model="form.return_policy_text"
                class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 font-medium text-slate-900"
              />
            </div>

            <!-- QR Code Mode -->
            <div v-if="form.show_qr_code" class="space-y-1">
              <label class="font-black text-slate-800 block">QR Code Target Mode</label>
              <select v-model="form.qr_code_mode" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 font-bold text-slate-900">
                <option value="invoice_ref">Scan Invoice Reference</option>
                <option value="upi_payment">Scan UPI Payment Reference</option>
                <option value="website">Scan RUPSA Website</option>
                <option value="whatsapp">Scan WhatsApp Support</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- RIGHT COLUMN: LIVE THERMAL RECEIPT PREVIEW (5 Cols) -->
      <div class="lg:col-span-5 sticky top-6 space-y-4">
        <div class="bg-slate-900 rounded-3xl p-5 text-white shadow-xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <div class="flex items-center gap-2">
              <span class="text-lg">🧾</span>
              <span class="font-black text-xs uppercase tracking-wider">Live Receipt Preview</span>
            </div>
            <button
              @click="triggerTestPrint"
              class="px-3.5 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-black text-xs transition-colors flex items-center gap-1 shadow-sm"
            >
              <span>🖨️</span>
              <span>TEST PRINT</span>
            </button>
          </div>

          <!-- Document Type Switcher Tabs -->
          <div class="grid grid-cols-4 gap-1 p-1 bg-slate-800 rounded-xl text-[10px] font-bold text-center">
            <button
              @click="activeDocTab = 'invoice'"
              :class="['py-1.5 rounded-lg transition-colors', activeDocTab === 'invoice' ? 'bg-red-600 text-white font-black' : 'text-slate-400 hover:text-white']"
            >
              Sales Invoice
            </button>
            <button
              @click="activeDocTab = 'return'"
              :class="['py-1.5 rounded-lg transition-colors', activeDocTab === 'return' ? 'bg-red-600 text-white font-black' : 'text-slate-400 hover:text-white']"
            >
              Sales Return
            </button>
            <button
              @click="activeDocTab = 'exchange'"
              :class="['py-1.5 rounded-lg transition-colors', activeDocTab === 'exchange' ? 'bg-red-600 text-white font-black' : 'text-slate-400 hover:text-white']"
            >
              Exchange
            </button>
            <button
              @click="activeDocTab = 'payment'"
              :class="['py-1.5 rounded-lg transition-colors', activeDocTab === 'payment' ? 'bg-red-600 text-white font-black' : 'text-slate-400 hover:text-white']"
            >
              Payment
            </button>
          </div>

          <!-- Thermal Receipt Component Live Preview Container -->
          <div class="bg-amber-50/20 p-4 rounded-2xl border border-slate-800 overflow-x-auto flex justify-center">
            <ThermalReceipt
              :document-type="activeDocTab"
              :data="sampleData"
              :settings="form"
            />
          </div>

          <div class="text-[10px] text-slate-400 text-center font-medium">
            Live preview reflects exact configured toggles and paper width ({{ form.printer_width }}).
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { usePrinterStore } from '../../stores/printerStore';
import ThermalReceipt from '../../components/printing/ThermalReceipt.vue';
import api from '../../services/api';

const printerStore = usePrinterStore();
const saving = ref(false);
const activeDocTab = ref('invoice');

const form = reactive({ ...printerStore.settings });

const sampleData = reactive({
  invoice_number: 'INV-20260903-8899',
  return_number: 'RET-20260903-1002',
  exchange_number: 'EXC-20260903-5544',
  receipt_number: 'PAY-20260903-3321',
  original_invoice_number: 'INV-20260903-8899',
  created_at: new Date().toISOString(),
  store: {
    code: 'STR-001',
    name: 'RUPSA PADUKALAYA - Main Outlet',
    address: 'DHANTALA BAZAR, DHANTALA, NADIA - 741202, WEST BENGAL, INDIA',
    phone: '+91 9735125112',
    gstin: '19ABCDE1234F1Z5',
    email: 'contact@rupsapadukalaya.com',
  },
  customer: {
    name: 'Sourav Ganguly',
    mobile_number: '9830098300',
    address: 'Dhantala, Nadia, WB',
  },
  cashier_name: 'Counter Staff',
  items: [
    {
      id: 1,
      article_number: 'RP-008',
      product_name: 'Executive Leather Oxford',
      brand_name: 'Bata India',
      color_name: 'Black',
      size_number: '8',
      quantity: 1,
      mrp: 1899.00,
      unit_price: 1699.00,
      discount_amount: 200.00,
      subtotal: 1699.00,
    },
    {
      id: 2,
      article_number: 'RP-012',
      product_name: 'Comfort Casual Loafer',
      brand_name: 'Apex Footwear',
      color_name: 'Tan Brown',
      size_number: '9',
      quantity: 1,
      mrp: 1499.00,
      unit_price: 1299.00,
      discount_amount: 200.00,
      subtotal: 1299.00,
    },
  ],
  returned_items: [
    {
      id: 1,
      article_number: 'RP-008',
      product_name: 'Executive Leather Oxford',
      brand_name: 'Bata India',
      color_name: 'Black',
      size_number: '8',
      quantity: 1,
      unit_price: 1699.00,
      subtotal: 1699.00,
    },
  ],
  replacement_items: [
    {
      id: 10,
      article_number: 'RP-008',
      product_name: 'Executive Leather Oxford',
      brand_name: 'Bata India',
      color_name: 'Black',
      size_number: '9',
      quantity: 1,
      unit_price: 1699.00,
      subtotal: 1699.00,
    },
  ],
  subtotal: 2998.00,
  discount_amount: 400.00,
  total_tax: 142.76,
  grand_total: 2998.00,
  paid_amount: 2998.00,
  due_amount: 0.00,
  total_refund_amount: 1699.00,
  refund_mode: 'store_credit',
  returned_total: 1699.00,
  replacement_total: 1699.00,
  price_difference: 0.00,
  payment_method: 'upi',
  amount_paid: 0.00,
  previous_due: 2000.00,
  amount_received: 2000.00,
  remaining_due: 0.00,
  reason: 'Customer Size Exchange',
});

const logoFileInput = ref(null);
const uploadingLogo = ref(false);

async function handleLogoUpload(event) {
  const file = event.target.files?.[0];
  if (!file) return;

  if (file.size > 2 * 1024 * 1024) {
    alert('File size exceeds maximum allowed limit of 2 MB.');
    if (logoFileInput.value) logoFileInput.value.value = '';
    return;
  }

  const allowed = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
  if (!allowed.includes(file.type)) {
    alert('Invalid image format. Allowed formats: PNG, JPG, JPEG, WEBP.');
    if (logoFileInput.value) logoFileInput.value.value = '';
    return;
  }

  uploadingLogo.value = true;
  try {
    const updated = await printerStore.uploadLogo(file);
    Object.assign(form, updated);
    alert('Store logo uploaded successfully!');
  } catch (err) {
    console.error('Logo upload error:', err);
    alert(err.response?.data?.message || 'Failed to upload logo.');
  } finally {
    uploadingLogo.value = false;
    if (logoFileInput.value) logoFileInput.value.value = '';
  }
}

async function handleRemoveLogo() {
  if (!confirm('Are you sure you want to remove the store logo from thermal bills?')) return;

  uploadingLogo.value = true;
  try {
    const updated = await printerStore.removeLogo();
    Object.assign(form, updated);
    alert('Store logo removed successfully.');
  } catch (err) {
    console.error('Logo removal error:', err);
    alert(err.response?.data?.message || 'Failed to remove logo.');
  } finally {
    uploadingLogo.value = false;
  }
}

async function saveSettings() {
  saving.value = true;
  try {
    const updated = await printerStore.updateSettings(form);
    Object.assign(form, updated);
    alert('Printer settings saved successfully.');
  } catch (err) {
    console.error('Failed to save printer settings:', err);
    alert(err.response?.data?.message || 'Failed to save printer settings.');
  } finally {
    saving.value = false;
  }
}

function triggerTestPrint() {
  window.print();
}

onMounted(async () => {
  await printerStore.fetchSettings(true);
  Object.assign(form, printerStore.settings);
});
</script>
