import { defineStore } from 'pinia';
import api from '../services/api';

export const useWebsiteStore = defineStore('website', {
    state: () => ({
        isLoaded: false,
        isLoading: false,
        settings: {
            general: { site_title: 'RUPSA PADUKALAYA', tagline: 'STEP INTO COMFORT', favicon_url: '' },
            header: {
                logo_url: '',
                logo_width: 180,
                logo_height: 45,
                layout: 'logo-left',
                bg_color: '#ffffff',
                sticky: true,
                show_search_icon: true,
                show_account_icon: true,
                show_cart_icon: false,
                header_button: {
                    enabled: true,
                    text: 'BUY ON WHATSAPP',
                    url: 'https://wa.me/919876543210',
                    target: '_blank',
                    style: 'solid-red',
                    size: 'md',
                    show_desktop: true,
                    show_mobile: true,
                },
                navigation_styling: {
                    font_size: 16,
                    font_weight: '700',
                    item_spacing: 16,
                    letter_spacing: '0.02em',
                    active_color: '#dc2626',
                    hover_color: '#b91c1c',
                },
            },
            announcement_bar: {
                enabled: true,
                bg_color: '#ea580c',
                text_color: '#ffffff',
                speed_seconds: 25,
                items: ['🚚 FREE SHIPPING ABOVE ₹500', '↩️ EASY 15-DAY RETURNS'],
            },
            homepage_content: {
                section1_title: 'NEW FOOTWEAR ARRIVALS',
                section2_title: 'TRENDING FOOTWEAR',
                carousel_title: 'BEST SELLING FOOTWEAR',
                small_banners: [],
                whatsapp_settings: {
                    enabled: true,
                    phone_number: '919876543210',
                    button_text: 'BUY ON WHATSAPP',
                    default_message: "Hello RUPSA PADUKALAYA,\nI am interested in purchasing:\n\nProduct: {product_name}\nArticle Number: {article_number}\nPrice: ₹{selling_price}\n\nPlease provide availability and purchase details.",
                },
                full_banner1: { image_url: '', title: '', subtitle: '', button_text: '', url: '', enabled: true },
                full_banner2: { image_url: '', title: '', subtitle: '', button_text: '', url: '', enabled: true },
                promo_banners: [],
                reels: [],
                section_visibility: { small_banners: true, category_carousel: true, section1: true, full_banner1: true, promo_banners: true, section2: true, reels: true, full_banner2: true, carousel: true },
            },
            navigation: [],
            footer: {
                enabled: true,
                logo_url: '',
                col1: {
                    title: 'SHOP AT RUPSA PADUKALAYA',
                    links: [
                        { label: 'Men', url: '/categories/men' },
                        { label: 'Women', url: '/categories/women' },
                        { label: 'Kids', url: '/categories/kids' },
                        { label: 'Accessories', url: '/categories/others' },
                        { label: 'Athleisure', url: '/categories/sports-shoes' },
                        { label: 'Premium Leather 🔹', url: '/categories/formal-shoes', is_special: true },
                        { label: 'Exclusive Online', url: '/categories/casual-shoes' },
                        { label: 'On Sale', url: '/offers' },
                        { label: 'Online Shopping Policy', url: '/about' },
                        { label: 'FAQs', url: '/about' },
                    ],
                },
                col2: {
                    title: 'IT\'S WOW! IT\'S RUPSA',
                    links: [
                        { label: 'About Us', url: '/about' },
                        { label: 'Brands', url: '/categories/men' },
                        { label: 'Media Centre', url: '/about' },
                        { label: 'Careers', url: '/about' },
                        { label: 'Blog', url: '/about' },
                    ],
                    keep_in_touch_title: 'KEEP IN TOUCH',
                    social_links: [
                        { platform: 'facebook', url: 'https://facebook.com', enabled: true },
                        { platform: 'instagram', url: 'https://instagram.com', enabled: true },
                        { platform: 'twitter', url: 'https://twitter.com', enabled: true },
                        { platform: 'youtube', url: 'https://youtube.com', enabled: true },
                        { platform: 'linkedin', url: 'https://linkedin.com', enabled: true },
                    ],
                },
                col3: {
                    title: 'CORPORATE',
                    links: [
                        { label: 'Investor Relations', url: '/about' },
                        { label: 'Corporate Social Responsibility (CSR)', url: '/about' },
                        { label: 'Company Policies', url: '/about' },
                        { label: 'Franchisee Partnership', url: '/contact' },
                    ],
                },
                col4: {
                    title: 'SUPPORT',
                    links: [
                        { label: 'Contact Us', url: '/contact' },
                        { label: 'Store Locator', url: '/stores' },
                        { label: 'FAQs', url: '/about' },
                        { label: 'Shipping / Delivery Information', url: '/about' },
                        { label: 'Return & Exchange Policy', url: '/about' },
                        { label: 'Privacy Policy', url: '/about' },
                        { label: 'Terms & Conditions', url: '/about' },
                    ],
                    sitemap: { label: 'Sitemap', url: '/about', enabled: true },
                },
                trust_features: [
                    { id: 1, icon: '🚚', title: 'FREE DELIVERY', subtitle: 'On all orders above ₹999' },
                    { id: 2, icon: '🔄', title: 'EASY RETURNS', subtitle: '15 days return policy' },
                    { id: 3, icon: '🛡️', title: '100% ORIGINAL', subtitle: 'Authentic products only' },
                    { id: 4, icon: '🎧', title: 'CUSTOMER SUPPORT', subtitle: 'We are here to help you' },
                ],
                payment_methods: ['Visa', 'Mastercard', 'RuPay', 'UPI', 'Paytm', 'PhonePe', 'G Pay', 'Apple Pay'],
                copyright_text: '© ' + new Date().getFullYear() + ', Rupsa Padukalaya. All rights reserved.',
                bg_color: '#f8fafc',
                text_color: '#1e293b',
            },
            contact_info: {
                business_name: 'RUPSA PADUKALAYA',
                address_line: 'DHANTALA BAZAR, DHANTALA',
                district: 'NADIA',
                pin_code: '741202',
                state: 'WEST BENGAL',
                country: 'INDIA',
                full_address: 'DHANTALA BAZAR, DHANTALA, NADIA, 741202, WEST BENGAL, INDIA',
                phone: '+91 9735125112',
                whatsapp_phone: '+91 9735125112',
                email: 'support@rupsapadukalaya.com',
                maps_url: 'https://maps.google.com/?q=RUPSA+PADUKALAYA+DHANTALA+BAZAR+DHANTALA+NADIA+741202+WEST+BENGAL',
            },
            social_links: [],
        },
    }),

    actions: {
        async fetchSettings(force = false) {
            if (this.isLoaded && !force) return;
            this.isLoading = true;
            try {
                const res = await api.get('/public/website-settings');
                const data = res.data || res;
                if (data && typeof data === 'object') {
                    if (data.general) Object.assign(this.settings.general, data.general);
                    if (data.header) {
                        Object.assign(this.settings.header, data.header);
                        if (data.header.header_button) {
                            this.settings.header.header_button = { ...this.settings.header.header_button, ...data.header.header_button };
                        }
                        if (data.header.navigation_styling) {
                            this.settings.header.navigation_styling = { ...this.settings.header.navigation_styling, ...data.header.navigation_styling };
                        }
                    }
                    if (data.announcement_bar) Object.assign(this.settings.announcement_bar, data.announcement_bar);
                    if (data.homepage_content) {
                        this.settings.homepage_content = {
                            ...this.settings.homepage_content,
                            ...data.homepage_content,
                        };
                    }
                    if (data.navigation && Array.isArray(data.navigation)) this.settings.navigation = data.navigation;
                    if (data.footer) {
                        this.settings.footer = {
                            ...this.settings.footer,
                            ...data.footer,
                            col1: { ...this.settings.footer.col1, ...(data.footer.col1 || {}) },
                            col2: { ...this.settings.footer.col2, ...(data.footer.col2 || {}) },
                            col3: { ...this.settings.footer.col3, ...(data.footer.col3 || {}) },
                            col4: { ...this.settings.footer.col4, ...(data.footer.col4 || {}) },
                            trust_features: data.footer.trust_features || this.settings.footer.trust_features,
                            payment_methods: data.footer.payment_methods || this.settings.footer.payment_methods,
                        };
                    }
                    if (data.contact_info) Object.assign(this.settings.contact_info, data.contact_info);
                    if (data.social_links) this.settings.social_links = data.social_links;

                    // Dynamically apply favicon to HTML <head> with cache buster
                    if (this.settings.general?.favicon_url) {
                        const url = this.settings.general.favicon_url;
                        const cacheBusterUrl = url.includes('?') ? url : `${url}?v=${Date.now()}`;
                        let iconLink = document.querySelector("link[rel*='icon']");
                        if (!iconLink) {
                            iconLink = document.createElement('link');
                            iconLink.rel = 'icon';
                            document.head.appendChild(iconLink);
                        }
                        iconLink.href = cacheBusterUrl;

                        let appleLink = document.querySelector("link[rel='apple-touch-icon']");
                        if (!appleLink) {
                            appleLink = document.createElement('link');
                            appleLink.rel = 'apple-touch-icon';
                            document.head.appendChild(appleLink);
                        }
                        appleLink.href = cacheBusterUrl;
                    }
                }
            } catch (err) {
                console.error('Failed to load public website settings:', err);
            } finally {
                this.isLoaded = true;
                this.isLoading = false;
            }
        },
    },
});
