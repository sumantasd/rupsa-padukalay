import { useWebsiteStore } from '../stores/websiteStore';

export function useWhatsApp() {
    const websiteStore = useWebsiteStore();

    function getWhatsAppUrl(product, color = '', size = '') {
        const waSettings = websiteStore.settings?.homepage_content?.whatsapp_settings || {};
        let phone = (waSettings.phone_number || '919735125112').replace(/[^0-9]/g, '');
        if (!phone.startsWith('91') && phone.length === 10) {
            phone = '91' + phone;
        }

        const name = product?.name || 'Footwear Article';
        const article = product?.article_number || product?.code || 'RP-100';
        const price = product?.selling_price || product?.mrp || '0';

        let messageTemplate = waSettings.default_message ||
            "Hello RUPSA PADUKALAYA,\nI am interested in purchasing:\n\nProduct: {product_name}\nArticle Number: {article_number}\nPrice: ₹{selling_price}\n\nPlease provide availability and purchase details.";

        let message = messageTemplate
            .replace(/{product_name}/g, name)
            .replace(/{article_number}/g, article)
            .replace(/{selling_price}/g, price);

        if (color) message += `\nColor: ${color}`;
        if (size) message += `\nSize: ${size}`;

        return `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
    }

    function buyOnWhatsApp(product, color = '', size = '') {
        const url = getWhatsAppUrl(product, color, size);
        window.open(url, '_blank');
    }

    return {
        getWhatsAppUrl,
        buyOnWhatsApp,
    };
}
