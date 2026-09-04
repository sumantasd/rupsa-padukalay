<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\CmsSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FrontWebsiteSettingController extends Controller
{
    /**
     * Get default website settings schema.
     */
    private function getDefaultSettings(): array
    {
        return [
            'general' => [
                'site_title' => 'RUPSA PADUKALAYA',
                'tagline' => 'STEP INTO COMFORT',
                'favicon_url' => '',
            ],
            'header' => [
                'logo_url' => '',
                'logo_width' => 180,
                'logo_height' => 45,
                'layout' => 'logo-left',
                'bg_color' => '#ffffff',
                'sticky' => true,
                'show_search_icon' => true,
                'show_account_icon' => true,
                'show_cart_icon' => false,
                'header_button' => [
                    'enabled' => true,
                    'text' => 'BUY ON WHATSAPP',
                    'url' => 'https://wa.me/919735125112',
                    'target' => '_blank',
                    'style' => 'solid-red',
                    'size' => 'md',
                    'show_desktop' => true,
                    'show_mobile' => true,
                ],
                'navigation_styling' => [
                    'font_size' => 15,
                    'font_weight' => '700',
                    'item_spacing' => 16,
                    'letter_spacing' => '0.02em',
                    'active_color' => '#dc2626',
                    'hover_color' => '#b91c1c',
                ],
            ],
            'announcement_bar' => [
                'enabled' => true,
                'bg_color' => '#ea580c',
                'text_color' => '#ffffff',
                'speed_seconds' => 25,
                'items' => [
                    '🚚 FREE SHIPPING ABOVE ₹500',
                    '↩️ EASY 15-DAY RETURNS',
                    '💵 COD AVAILABLE NATIONWIDE',
                    '💳 SAVE 5% EXTRA ON PREPAID ORDERS',
                    '🔥 UP TO 50% OFF ON SELECTED STYLES',
                ],
            ],
            'homepage_content' => [
                'section1_title' => 'NEW FOOTWEAR ARRIVALS',
                'section2_title' => 'TRENDING FOOTWEAR',
                'carousel_title' => 'BEST SELLING FOOTWEAR',
                'small_banners' => [
                    [
                        'id' => 1,
                        'image_url' => '',
                        'title' => 'Executive Leather',
                        'subtitle' => 'Oxfords & Brogues',
                        'button_text' => 'Explore',
                        'url' => '/categories/men',
                        'enabled' => true,
                    ],
                    [
                        'id' => 2,
                        'image_url' => '',
                        'title' => 'Comfort Ortho',
                        'subtitle' => 'Daily Slippers',
                        'button_text' => 'Explore',
                        'url' => '/categories/slippers',
                        'enabled' => true,
                    ],
                    [
                        'id' => 3,
                        'image_url' => '',
                        'title' => 'Ethnic Sandals',
                        'subtitle' => 'Festive Collection',
                        'button_text' => 'Explore',
                        'url' => '/categories/sandals',
                        'enabled' => true,
                    ],
                    [
                        'id' => 4,
                        'image_url' => '',
                        'title' => 'Pro Sport Sneakers',
                        'subtitle' => 'Air Cushion Soles',
                        'button_text' => 'Explore',
                        'url' => '/categories/sports-shoes',
                        'enabled' => true,
                    ],
                ],
                'whatsapp_settings' => [
                    'enabled' => true,
                    'phone_number' => '919735125112',
                    'button_text' => 'BUY ON WHATSAPP',
                    'default_message' => "Hello RUPSA PADUKALAYA,\nI am interested in purchasing:\n\nProduct: {product_name}\nArticle Number: {article_number}\nPrice: ₹{selling_price}\n\nPlease provide availability and purchase details.",
                ],
                'full_banner1' => [
                    'image_url' => '',
                    'title' => 'MONSOON FOOTWEAR DEALS — UP TO 50% OFF',
                    'subtitle' => 'Genuine leather shoes, ethnic sandals & daily ortho slippers',
                    'button_text' => 'Explore Monsoon Deals',
                    'url' => '/categories/men',
                    'enabled' => true,
                ],
                'full_banner2' => [
                    'image_url' => '',
                    'title' => 'WOMEN\'S ETHNIC & DAILY COMFORT COLLECTION',
                    'subtitle' => 'Hand-stitched footwear engineered for maximum arch support',
                    'button_text' => 'Explore Women Collection',
                    'url' => '/categories/women',
                    'enabled' => true,
                ],
                'promo_banners' => [
                    [
                        'id' => 1,
                        'image_url' => '',
                        'title' => 'MEN\'S EXECUTIVE LEATHER',
                        'subtitle' => 'Oxfords & Formal Brogues',
                        'button_text' => 'Shop Men',
                        'url' => '/categories/men',
                        'enabled' => true,
                    ],
                    [
                        'id' => 2,
                        'image_url' => '',
                        'title' => 'WOMEN\'S CHIC HEELS & SANDALS',
                        'subtitle' => 'Elegance for every occasion',
                        'button_text' => 'Shop Women',
                        'url' => '/categories/women',
                        'enabled' => true,
                    ],
                    [
                        'id' => 3,
                        'image_url' => '',
                        'title' => 'KIDS & DAILY ORTHO CASUALS',
                        'subtitle' => 'Durable & lightweight comfort',
                        'button_text' => 'Shop Kids',
                        'url' => '/categories/kids',
                        'enabled' => true,
                    ],
                ],
                'reels' => [
                    [
                        'id' => 1,
                        'poster_url' => '',
                        'video_url' => '',
                        'title' => 'Pure Leather Craftsmanship',
                        'tag' => '#HandcraftedLeather',
                        'url' => '/categories/men',
                        'enabled' => true,
                    ],
                    [
                        'id' => 2,
                        'poster_url' => '',
                        'video_url' => '',
                        'title' => 'Comfort Cushioning Test',
                        'tag' => '#OrthoComfort',
                        'url' => '/categories/women',
                        'enabled' => true,
                    ],
                    [
                        'id' => 3,
                        'poster_url' => '',
                        'video_url' => '',
                        'title' => 'Monsoon Waterproof Test',
                        'tag' => '#WaterproofSeries',
                        'url' => '/categories/others',
                        'enabled' => true,
                    ],
                    [
                        'id' => 4,
                        'poster_url' => '',
                        'video_url' => '',
                        'title' => 'Flexible Sole Technology',
                        'tag' => '#ErgonomicFit',
                        'url' => '/categories/kids',
                        'enabled' => true,
                    ],
                ],
                'section_visibility' => [
                    'small_banners' => true,
                    'category_carousel' => true,
                    'section1' => true,
                    'full_banner1' => true,
                    'promo_banners' => true,
                    'section2' => true,
                    'reels' => true,
                    'full_banner2' => true,
                    'carousel' => true,
                ],
            ],
            'navigation' => [
                ['id' => 1, 'label' => 'HOME', 'url' => '/', 'enabled' => true, 'target' => '_self'],
                ['id' => 2, 'label' => 'ABOUT', 'url' => '/about', 'enabled' => true, 'target' => '_self'],
                ['id' => 3, 'label' => 'MEN', 'url' => '/categories/men', 'enabled' => true, 'target' => '_self'],
                ['id' => 4, 'label' => 'WOMEN', 'url' => '/categories/women', 'enabled' => true, 'target' => '_self'],
                ['id' => 5, 'label' => 'KIDS', 'url' => '/categories/kids', 'enabled' => true, 'target' => '_self'],
                ['id' => 6, 'label' => 'OTHERS', 'url' => '/categories/others', 'enabled' => true, 'target' => '_self'],
                ['id' => 7, 'label' => 'CONTACT', 'url' => '/contact', 'enabled' => true, 'target' => '_self'],
            ],
            'footer' => [
                'enabled' => true,
                'logo_url' => '',
                'col1' => [
                    'title' => 'SHOP AT RUPSA PADUKALAYA',
                    'links' => [
                        ['label' => 'Men', 'url' => '/categories/men'],
                        ['label' => 'Women', 'url' => '/categories/women'],
                        ['label' => 'Kids', 'url' => '/categories/kids'],
                        ['label' => 'Accessories', 'url' => '/categories/others'],
                        ['label' => 'Athleisure', 'url' => '/categories/sports-shoes'],
                        ['label' => 'Premium Leather 🔹', 'url' => '/categories/formal-shoes', 'is_special' => true],
                        ['label' => 'Exclusive Online', 'url' => '/categories/casual-shoes'],
                        ['label' => 'On Sale', 'url' => '/offers'],
                        ['label' => 'Online Shopping Policy', 'url' => '/about'],
                        ['label' => 'FAQs', 'url' => '/about'],
                    ],
                ],
                'col2' => [
                    'title' => 'IT\'S WOW! IT\'S RUPSA',
                    'links' => [
                        ['label' => 'About Us', 'url' => '/about'],
                        ['label' => 'Brands', 'url' => '/categories/men'],
                        ['label' => 'Media Centre', 'url' => '/about'],
                        ['label' => 'Careers', 'url' => '/about'],
                        ['label' => 'Blog', 'url' => '/about'],
                    ],
                    'keep_in_touch_title' => 'KEEP IN TOUCH',
                    'social_links' => [
                        ['platform' => 'facebook', 'url' => 'https://facebook.com', 'enabled' => true],
                        ['platform' => 'instagram', 'url' => 'https://instagram.com', 'enabled' => true],
                        ['platform' => 'twitter', 'url' => 'https://twitter.com', 'enabled' => true],
                        ['platform' => 'youtube', 'url' => 'https://youtube.com', 'enabled' => true],
                        ['platform' => 'linkedin', 'url' => 'https://linkedin.com', 'enabled' => true],
                    ],
                ],
                'col3' => [
                    'title' => 'CORPORATE',
                    'links' => [
                        ['label' => 'Investor Relations', 'url' => '/about'],
                        ['label' => 'Corporate Social Responsibility (CSR)', 'url' => '/about'],
                        ['label' => 'Company Policies', 'url' => '/about'],
                        ['label' => 'Franchisee Partnership', 'url' => '/contact'],
                    ],
                ],
                'col4' => [
                    'title' => 'SUPPORT',
                    'links' => [
                        ['label' => 'Contact Us', 'url' => '/contact'],
                        ['label' => 'Store Locator', 'url' => '/stores'],
                        ['label' => 'FAQs', 'url' => '/about'],
                        ['label' => 'Shipping / Delivery Information', 'url' => '/about'],
                        ['label' => 'Return & Exchange Policy', 'url' => '/about'],
                        ['label' => 'Privacy Policy', 'url' => '/about'],
                        ['label' => 'Terms & Conditions', 'url' => '/about'],
                    ],
                    'sitemap' => ['label' => 'Sitemap', 'url' => '/about', 'enabled' => true],
                ],
                'trust_features' => [
                    [
                        'id' => 1,
                        'icon' => '🚚',
                        'title' => 'FREE DELIVERY',
                        'subtitle' => 'On all orders above ₹999',
                    ],
                    [
                        'id' => 2,
                        'icon' => '🔄',
                        'title' => 'EASY RETURNS',
                        'subtitle' => '15 days return policy',
                    ],
                    [
                        'id' => 3,
                        'icon' => '🛡️',
                        'title' => '100% ORIGINAL',
                        'subtitle' => 'Authentic products only',
                    ],
                    [
                        'id' => 4,
                        'icon' => '🎧',
                        'title' => 'CUSTOMER SUPPORT',
                        'subtitle' => 'We are here to help you',
                    ],
                ],
                'payment_methods' => ['Visa', 'Mastercard', 'RuPay', 'UPI', 'Paytm', 'PhonePe', 'G Pay', 'Apple Pay'],
                'copyright_text' => '© {CURRENT_YEAR}, Rupsa Padukalaya. All rights reserved.',
                'bg_color' => '#f8fafc',
                'text_color' => '#1e293b',
            ],
            'contact_info' => [
                'business_name' => 'RUPSA PADUKALAYA',
                'address_line' => 'DHANTALA BAZAR, DHANTALA',
                'district' => 'NADIA',
                'pin_code' => '741202',
                'state' => 'WEST BENGAL',
                'country' => 'INDIA',
                'full_address' => 'DHANTALA BAZAR, DHANTALA, NADIA, 741202, WEST BENGAL, INDIA',
                'phone' => '+91 9735125112',
                'whatsapp_phone' => '+91 9735125112',
                'email' => 'support@rupsapadukalaya.com',
                'maps_url' => 'https://maps.google.com/?q=RUPSA+PADUKALAYA+DHANTALA+BAZAR+DHANTALA+NADIA+741202+WEST+BENGAL',
            ],
            'social_links' => [
                ['platform' => 'Instagram', 'url' => 'https://instagram.com', 'enabled' => true],
                ['platform' => 'Facebook', 'url' => 'https://facebook.com', 'enabled' => true],
                ['platform' => 'YouTube', 'url' => 'https://youtube.com', 'enabled' => true],
                ['platform' => 'WhatsApp', 'url' => 'https://wa.me/919735125112', 'enabled' => true],
            ],
        ];
    }

    /**
     * Get published website settings for public website consumption.
     */
    public function getPublicSettings(): JsonResponse
    {
        $raw = CmsSetting::getSetting('front_website_settings');
        $saved = $raw ? json_decode($raw, true) : [];
        $settings = array_replace_recursive($this->getDefaultSettings(), $saved ?: []);

        // Dynamically replace {CURRENT_YEAR} in copyright
        if (isset($settings['footer']['copyright_text'])) {
            $settings['footer']['copyright_text'] = str_replace(
                '{CURRENT_YEAR}',
                date('Y'),
                $settings['footer']['copyright_text']
            );
        }

        return response()->json([
            'success' => true,
            'data' => $settings,
        ])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
          ->header('Pragma', 'no-cache')
          ->header('Expires', '0');
    }

    /**
     * Get website settings for Admin ERP dashboard.
     */
    public function getAdminSettings(): JsonResponse
    {
        $raw = CmsSetting::getSetting('front_website_settings');
        $saved = $raw ? json_decode($raw, true) : [];
        $settings = array_replace_recursive($this->getDefaultSettings(), $saved ?: []);

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Update website settings in database.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $payload = $request->input('settings', $request->all());

        // Validate structure
        if (empty($payload)) {
            return response()->json([
                'success' => false,
                'message' => 'Settings payload cannot be empty.',
            ], 422);
        }

        // Sanitize navigation URLs to prevent accidental /admin/* links
        if (isset($payload['navigation']) && is_array($payload['navigation'])) {
            foreach ($payload['navigation'] as &$item) {
                if (isset($item['url']) && str_starts_with(trim($item['url']), '/admin')) {
                    $item['url'] = '/';
                }
            }
        }

        CmsSetting::setSetting('front_website_settings', json_encode($payload));

        return response()->json([
            'success' => true,
            'message' => 'Front website settings published successfully.',
            'data' => $payload,
        ]);
    }

    /**
     * Upload logo binary image file and immediately persist to settings.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp,svg', 'max:5120'],
        ]);

        $path = $request->file('logo')->store('website', 'public');
        $logoUrl = '/storage/'.$path;

        // Persist immediately to CmsSetting so public website updates without extra steps
        $raw = CmsSetting::getSetting('front_website_settings');
        $saved = $raw ? json_decode($raw, true) : [];
        $settings = array_replace_recursive($this->getDefaultSettings(), $saved ?: []);
        $settings['header']['logo_url'] = $logoUrl;
        $settings['footer']['logo_url'] = $logoUrl;
        CmsSetting::setSetting('front_website_settings', json_encode($settings));

        return response()->json([
            'success' => true,
            'message' => 'Website logo uploaded and published successfully.',
            'logo_url' => $logoUrl,
            'data' => $settings,
        ]);
    }

    /**
     * Upload favicon binary image file and immediately persist to settings.
     */
    public function uploadFavicon(Request $request): JsonResponse
    {
        $request->validate([
            'favicon' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,svg,ico', 'max:5120'],
        ]);

        $path = $request->file('favicon')->store('website', 'public');
        $faviconUrl = '/storage/'.$path;

        // Persist immediately to CmsSetting
        $raw = CmsSetting::getSetting('front_website_settings');
        $saved = $raw ? json_decode($raw, true) : [];
        $settings = array_replace_recursive($this->getDefaultSettings(), $saved ?: []);
        $settings['general']['favicon_url'] = $faviconUrl;
        CmsSetting::setSetting('front_website_settings', json_encode($settings));

        return response()->json([
            'success' => true,
            'message' => 'Website favicon uploaded and published successfully.',
            'favicon_url' => $faviconUrl,
            'data' => $settings,
        ]);
    }

    /**
     * Upload media binary file (image or video) for homepage banners & reels.
     */
    public function uploadMedia(Request $request): JsonResponse
    {
        $request->validate([
            'media' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,svg,mp4,webm', 'max:20480'],
        ]);

        $path = $request->file('media')->store('website', 'public');
        $mediaUrl = '/storage/'.$path;

        return response()->json([
            'success' => true,
            'message' => 'Media uploaded successfully.',
            'media_url' => $mediaUrl,
        ]);
    }
}
