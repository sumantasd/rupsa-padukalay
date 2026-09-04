import { defineStore } from 'pinia';
import api from '../services/api';

export const usePrinterStore = defineStore('printer', {
  state: () => ({
    settings: {
      printer_enabled: true,
      printer_width: '80mm',
      auto_print_sale: false,
      auto_print_return: false,
      auto_print_exchange: false,
      auto_print_payment: false,
      show_print_preview: true,

      printer_logo_path: null,
      printer_logo_url: null,
      show_logo: true,
      show_store_name: true,
      show_outlet_name: true,
      show_address: true,
      show_phone: true,
      show_gstin: true,
      show_email: false,

      show_customer_name: true,
      show_customer_mobile: true,
      show_customer_address: false,

      show_article_number: true,
      show_product_name: true,
      show_brand: true,
      show_color: true,
      show_size: true,
      show_quantity: true,
      show_mrp: true,
      show_selling_price: true,
      show_line_discount: true,
      show_tax: true,
      show_sku: false,

      show_subtotal: true,
      show_discount: true,
      show_tax: true,
      show_grand_total: true,
      show_payment_method: true,
      show_amount_paid: true,
      show_due_amount: true,
      show_transaction_id: true,
      show_price_difference: true,
      show_store_credit: true,

      show_thank_you_message: true,
      thank_you_message: 'Thank you for shopping at RUPSA PADUKALAYA! We hope to serve you again.',
      show_return_policy: true,
      return_policy_text: '*** GOODS ONCE SOLD WILL NOT BE TAKEN BACK WITHOUT ORIGINAL RECEIPT ***',
      show_qr_code: true,
      qr_code_mode: 'invoice_ref',
      show_developed_by_credit: true,
    },
    loaded: false,
    loading: false,
    error: null,
  }),

  actions: {
    async fetchSettings(force = false) {
      if (this.loaded && !force) {
        return this.settings;
      }

      this.loading = true;
      this.error = null;
      try {
        const res = await api.get('/settings/printer');
        const payload = res.data?.data || res.data || res;
        if (payload && typeof payload === 'object') {
          this.settings = { ...this.settings, ...payload };
          this.loaded = true;
        }
      } catch (err) {
        console.error('Failed to load printer settings:', err);
        this.error = err.response?.data?.message || 'Failed to load printer settings.';
      } finally {
        this.loading = false;
      }
      return this.settings;
    },

    async updateSettings(newSettings) {
      this.loading = true;
      try {
        const payload = { ...this.settings, ...newSettings };
        const res = await api.post('/settings/printer', payload);
        const updated = res.data?.data || res.data || res;
        if (updated && typeof updated === 'object') {
          this.settings = { ...this.settings, ...updated };
          this.loaded = true;
        }
        return this.settings;
      } catch (err) {
        console.error('Failed to update printer settings:', err);
        throw err;
      } finally {
        this.loading = false;
      }
    },

    async uploadLogo(file) {
      this.loading = true;
      try {
        const formData = new FormData();
        formData.append('logo', file);
        const res = await api.post('/settings/printer/logo', formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        const updated = res.data?.data || res.data || res;
        if (updated && typeof updated === 'object') {
          this.settings = { ...this.settings, ...updated };
          this.loaded = true;
        }
        return this.settings;
      } catch (err) {
        console.error('Failed to upload store logo:', err);
        throw err;
      } finally {
        this.loading = false;
      }
    },

    async removeLogo() {
      this.loading = true;
      try {
        const res = await api.delete('/settings/printer/logo');
        const updated = res.data?.data || res.data || res;
        if (updated && typeof updated === 'object') {
          this.settings = { ...this.settings, ...updated };
          this.loaded = true;
        }
        return this.settings;
      } catch (err) {
        console.error('Failed to remove store logo:', err);
        throw err;
      } finally {
        this.loading = false;
      }
    },
  },
});
