<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\CmsSetting;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PrinterSettingController extends Controller
{
    use ApiResponse;

    public function getDefaultSettings(): array
    {
        return [
            // Basic & Hardware Settings
            'printer_enabled' => true,
            'printer_width' => '80mm', // '80mm' or '58mm'
            'auto_print_sale' => false,
            'auto_print_return' => false,
            'auto_print_exchange' => false,
            'auto_print_payment' => false,
            'show_print_preview' => true,

            // Logo Settings
            'printer_logo_path' => null,
            'printer_logo_url' => null,
            'show_logo' => true,

            // Store Header Settings
            'show_store_name' => true,
            'show_outlet_name' => true,
            'show_address' => true,
            'show_phone' => true,
            'show_gstin' => true,
            'show_email' => false,

            // Customer Details Settings
            'show_customer_name' => true,
            'show_customer_mobile' => true,
            'show_customer_address' => false,

            // Product Details Settings
            'show_article_number' => true,
            'show_product_name' => true,
            'show_brand' => true,
            'show_color' => true,
            'show_size' => true,
            'show_quantity' => true,
            'show_mrp' => true,
            'show_selling_price' => true,
            'show_line_discount' => true,
            'show_tax' => true,
            'show_sku' => false,

            // Financial Summary Settings
            'show_subtotal' => true,
            'show_discount' => true,
            'show_tax' => true,
            'show_grand_total' => true,
            'show_payment_method' => true,
            'show_amount_paid' => true,
            'show_due_amount' => true,
            'show_transaction_id' => true,
            'show_price_difference' => true,
            'show_store_credit' => true,

            // Footer & QR Settings
            'show_thank_you_message' => true,
            'thank_you_message' => 'Thank you for shopping at RUPSA PADUKALAYA! We hope to serve you again.',
            'show_return_policy' => true,
            'return_policy_text' => '*** GOODS ONCE SOLD WILL NOT BE TAKEN BACK WITHOUT ORIGINAL RECEIPT ***',
            'show_qr_code' => true,
            'qr_code_mode' => 'invoice_ref', // 'invoice_ref', 'upi_payment', 'website', 'whatsapp'
            'show_developed_by_credit' => true,
        ];
    }

    public function getSettings(): JsonResponse
    {
        $raw = CmsSetting::getSetting('thermal_printer_settings');
        $saved = $raw ? json_decode($raw, true) : [];
        $settings = array_replace_recursive($this->getDefaultSettings(), is_array($saved) ? $saved : []);

        if (! empty($settings['printer_logo_path'])) {
            $settings['printer_logo_url'] = Storage::disk('public')->url($settings['printer_logo_path']);
        } else {
            $settings['printer_logo_url'] = null;
        }

        return $this->successResponse($settings, 'Thermal printer settings retrieved successfully.');
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $payload = $request->all();
        if (empty($payload)) {
            return $this->errorResponse('Printer settings payload cannot be empty.', 422);
        }

        $raw = CmsSetting::getSetting('thermal_printer_settings');
        $saved = $raw ? json_decode($raw, true) : [];
        $merged = array_replace_recursive($this->getDefaultSettings(), is_array($saved) ? $saved : [], $payload);

        if (! empty($merged['printer_logo_path'])) {
            $merged['printer_logo_url'] = Storage::disk('public')->url($merged['printer_logo_path']);
        } else {
            $merged['printer_logo_url'] = null;
        }

        CmsSetting::setSetting('thermal_printer_settings', json_encode($merged));

        return $this->successResponse($merged, 'Thermal printer settings updated successfully.');
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        $file = $request->file('logo');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');
        $filename = 'logo_' . uniqid() . '_' . time() . '.' . $extension;
        $path = $file->storeAs('printer', $filename, 'public');

        $raw = CmsSetting::getSetting('thermal_printer_settings');
        $saved = $raw ? json_decode($raw, true) : [];
        $settings = array_replace_recursive($this->getDefaultSettings(), is_array($saved) ? $saved : []);

        // Delete previous logo file if exists
        if (! empty($settings['printer_logo_path'])) {
            Storage::disk('public')->delete($settings['printer_logo_path']);
        }

        $settings['printer_logo_path'] = $path;
        $settings['printer_logo_url'] = Storage::disk('public')->url($path);
        $settings['show_logo'] = true;

        CmsSetting::setSetting('thermal_printer_settings', json_encode($settings));

        return $this->successResponse($settings, 'Store logo uploaded successfully.');
    }

    public function deleteLogo(Request $request): JsonResponse
    {
        $raw = CmsSetting::getSetting('thermal_printer_settings');
        $saved = $raw ? json_decode($raw, true) : [];
        $settings = array_replace_recursive($this->getDefaultSettings(), is_array($saved) ? $saved : []);

        if (! empty($settings['printer_logo_path'])) {
            Storage::disk('public')->delete($settings['printer_logo_path']);
        }

        $settings['printer_logo_path'] = null;
        $settings['printer_logo_url'] = null;
        $settings['show_logo'] = false;

        CmsSetting::setSetting('thermal_printer_settings', json_encode($settings));

        return $this->successResponse($settings, 'Store logo removed successfully.');
    }

    public function testPrintData(Request $request): JsonResponse
    {
        $docType = $request->input('doc_type', 'invoice');
        $raw = CmsSetting::getSetting('thermal_printer_settings');
        $saved = $raw ? json_decode($raw, true) : [];
        $settings = array_replace_recursive($this->getDefaultSettings(), is_array($saved) ? $saved : []);

        $sampleData = [
            'store' => [
                'code' => 'STR-001',
                'name' => 'RUPSA PADUKALAYA - Main Outlet',
                'address' => 'DHANTALA BAZAR, DHANTALA, NADIA - 741202, WEST BENGAL, INDIA',
                'phone' => '+91 9735125112',
                'gstin' => '19ABCDE1234F1Z5',
                'email' => 'contact@rupsapadukalaya.com',
            ],
            'customer' => [
                'name' => 'Sourav Ganguly',
                'mobile' => '9830098300',
                'address' => 'Dhantala, Nadia, WB',
            ],
            'cashier_name' => 'Counter Staff',
            'created_at' => now()->toIso8601String(),
        ];

        if ($docType === 'invoice') {
            $sampleData['invoice_number'] = 'INV-20260903-8899';
            $sampleData['items'] = [
                [
                    'id' => 1,
                    'article_number' => 'RP-008',
                    'product_name' => 'Executive Leather Oxford',
                    'brand_name' => 'Bata India',
                    'color_name' => 'Black',
                    'size_number' => '8',
                    'quantity' => 1,
                    'mrp' => 1899.00,
                    'unit_price' => 1699.00,
                    'discount_amount' => 200.00,
                    'subtotal' => 1699.00,
                ],
                [
                    'id' => 2,
                    'article_number' => 'RP-012',
                    'product_name' => 'Comfort Casual Loafer',
                    'brand_name' => 'Apex Footwear',
                    'color_name' => 'Tan Brown',
                    'size_number' => '9',
                    'quantity' => 1,
                    'mrp' => 1499.00,
                    'unit_price' => 1299.00,
                    'discount_amount' => 200.00,
                    'subtotal' => 1299.00,
                ],
            ];
            $sampleData['subtotal'] = 2998.00;
            $sampleData['discount_amount'] = 400.00;
            $sampleData['total_tax'] = 142.76;
            $sampleData['grand_total'] = 2998.00;
            $sampleData['paid_amount'] = 2998.00;
            $sampleData['due_amount'] = 0.00;
            $sampleData['payments'] = [
                ['payment_method' => 'upi', 'amount' => 2000.00, 'transaction_reference' => 'UTR9988776655'],
                ['payment_method' => 'cash', 'amount' => 998.00, 'transaction_reference' => null],
            ];
        } elseif ($docType === 'return') {
            $sampleData['return_number'] = 'RET-20260903-1002';
            $sampleData['original_invoice_number'] = 'INV-20260903-8899';
            $sampleData['returned_items'] = [
                [
                    'id' => 1,
                    'article_number' => 'RP-008',
                    'product_name' => 'Executive Leather Oxford',
                    'brand_name' => 'Bata India',
                    'color_name' => 'Black',
                    'size_number' => '8',
                    'quantity' => 1,
                    'unit_price' => 1699.00,
                    'subtotal' => 1699.00,
                    'restock_condition' => 'resellable',
                ],
            ];
            $sampleData['total_refund_amount'] = 1699.00;
            $sampleData['refund_mode'] = 'store_credit';
            $sampleData['reason'] = 'Customer Size Preference';
        } elseif ($docType === 'exchange') {
            $sampleData['exchange_number'] = 'EXC-20260903-5544';
            $sampleData['original_invoice_number'] = 'INV-20260903-8899';
            $sampleData['returned_items'] = [
                [
                    'id' => 1,
                    'article_number' => 'RP-008',
                    'product_name' => 'Executive Leather Oxford',
                    'brand_name' => 'Bata India',
                    'color_name' => 'Black',
                    'size_number' => '8',
                    'quantity' => 1,
                    'unit_price' => 1699.00,
                    'subtotal' => 1699.00,
                ],
            ];
            $sampleData['replacement_items'] = [
                [
                    'id' => 10,
                    'article_number' => 'RP-008',
                    'product_name' => 'Executive Leather Oxford',
                    'brand_name' => 'Bata India',
                    'color_name' => 'Black',
                    'size_number' => '9',
                    'quantity' => 1,
                    'unit_price' => 1699.00,
                    'subtotal' => 1699.00,
                ],
            ];
            $sampleData['returned_total'] = 1699.00;
            $sampleData['replacement_total'] = 1699.00;
            $sampleData['price_difference'] = 0.00;
            $sampleData['payment_method'] = 'exchange_offset';
            $sampleData['amount_paid'] = 0.00;
            $sampleData['reason'] = 'IND 8 to IND 9 Size Exchange';
        } elseif ($docType === 'payment') {
            $sampleData['receipt_number'] = 'PAY-20260903-3321';
            $sampleData['reference_number'] = 'INV-20260903-8899';
            $sampleData['previous_due'] = 2000.00;
            $sampleData['amount_received'] = 2000.00;
            $sampleData['remaining_due'] = 0.00;
            $sampleData['payment_method'] = 'upi';
            $sampleData['transaction_reference'] = 'PAYUTR77665544';
        }

        return $this->successResponse([
            'settings' => $settings,
            'doc_type' => $docType,
            'sample_data' => $sampleData,
        ], 'Test print sample payload generated.');
    }
}
