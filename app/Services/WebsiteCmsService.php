<?php

namespace App\Services;

use App\Models\Banner;
use App\Models\Brand;
use App\Models\CmsSetting;
use App\Models\Product;
use App\Models\WebsiteLead;

class WebsiteCmsService
{
    /**
     * Get active homepage banners and brand-wise published products showcase (~8-10 products per brand).
     */
    public function getHomepageData(): array
    {
        $banners = Banner::where('is_active', true)->orderBy('sort_order')->get();
        
        $brands = Brand::with(['products' => function ($q) {
            $q->where('is_active', true)
              ->where('is_visible_on_web', true)
              ->with(['variants.sizes.size', 'images'])
              ->limit(10);
        }])
        ->where('is_active', true)
        ->where('is_featured_on_web', true)
        ->get();

        $whatsappNumber = CmsSetting::getSetting('whatsapp_number', '919876543210');

        return [
            'banners' => $banners,
            'brands' => $brands,
            'whatsapp_number' => $whatsappNumber,
        ];
    }

    /**
     * Submit a website product inquiry / lead and generate WhatsApp enquiry link.
     */
    public function submitLead(array $data): array
    {
        $leadNumber = 'LEAD-'.date('Ymd').'-'.str_pad((string) (WebsiteLead::count() + 1), 5, '0', STR_PAD_LEFT);

        $lead = WebsiteLead::create([
            'lead_number' => $leadNumber,
            'customer_name' => trim($data['customer_name']),
            'mobile' => trim($data['mobile']),
            'email' => $data['email'] ?? null,
            'brand_name' => $data['brand_name'] ?? null,
            'product_id' => $data['product_id'] ?? null,
            'product_variant_id' => $data['product_variant_id'] ?? null,
            'preferred_size' => $data['preferred_size'] ?? null,
            'enquiry_message' => $data['enquiry_message'] ?? null,
            'source' => $data['source'] ?? 'website_whatsapp',
            'status' => 'new',
        ]);

        $whatsappNumber = CmsSetting::getSetting('whatsapp_number', '919876543210');
        $message = "Hello RUPSA PADUKALAYA, I am interested in: ";
        if (! empty($data['brand_name'])) {
            $message .= "Brand: {$data['brand_name']} | ";
        }
        if (! empty($data['article_number'])) {
            $message .= "Article: {$data['article_number']} | ";
        }
        if (! empty($data['preferred_size'])) {
            $message .= "Size: {$data['preferred_size']} | ";
        }
        $message .= "My Name: {$lead->customer_name}, Mobile: {$lead->mobile}. {$lead->enquiry_message}";

        $whatsappUrl = "https://wa.me/{$whatsappNumber}?text=".rawurlencode($message);

        return [
            'lead' => $lead,
            'whatsapp_url' => $whatsappUrl,
        ];
    }
}
