<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosInvoiceReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statusValue = is_object($this->status) && property_exists($this->status, 'value')
            ? $this->status->value
            : (string) $this->status;

        $saleTypeValue = is_object($this->sale_type) && property_exists($this->sale_type, 'value')
            ? $this->sale_type->value
            : (string) $this->sale_type;

        $paymentStatusValue = is_object($this->payment_status) && property_exists($this->payment_status, 'value')
            ? $this->payment_status->value
            : (string) $this->payment_status;

        $grandTotal = (float) $this->grand_total;
        $paidAmount = (float) $this->paid_amount;
        $remainingBalance = max(0.0, round($grandTotal - $paidAmount, 2));

        return [
            'header' => [
                'invoice_id' => $this->id,
                'invoice_number' => $this->invoice_number,
                'client_trans_uuid' => $this->client_trans_uuid,
                'invoice_date' => $this->created_at ? $this->created_at->toIso8601String() : null,
                'sale_type' => $saleTypeValue,
                'status' => $statusValue,
                'payment_status' => $paymentStatusValue,
            ],
            'store' => [
                'id' => $this->store_id,
                'code' => $this->store?->code,
                'name' => $this->store?->name,
                'address' => $this->store?->address,
                'phone' => $this->store?->phone,
                'email' => $this->store?->email,
                'gstin' => $this->store?->gstin,
            ],
            'cashier' => [
                'id' => $this->created_by,
                'name' => $this->creator?->name,
                'username' => $this->creator?->username,
            ],
            'customer' => $this->customer_id ? [
                'id' => $this->customer_id,
                'name' => $this->customer?->name,
                'mobile_number' => $this->customer?->mobile_number,
                'city' => $this->customer?->city,
                'address' => $this->customer?->address,
                'gstin' => $this->customer?->gstin,
            ] : [
                'id' => null,
                'name' => 'Walk-in Customer',
                'mobile_number' => null,
                'city' => null,
                'address' => null,
                'gstin' => null,
            ],
            'items' => $this->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_variant_size_id' => $item->product_variant_size_id,
                    'sku' => $item->sku_snapshot,
                    'article_number' => $item->article_number_snapshot,
                    'product_name' => $item->product_name_snapshot,
                    'color' => $item->color_name_snapshot,
                    'size' => $item->size_number_snapshot,
                    'hsn_code' => $item->hsn_code_snapshot,
                    'quantity' => (int) $item->quantity,
                    'cost_price' => (float) $item->cost_price,
                    'mrp' => (float) $item->mrp,
                    'unit_price' => (float) $item->unit_price,
                    'discount_amount' => (float) $item->discount_amount,
                    'tax_rate_percentage' => (float) $item->tax_rate_percentage,
                    'taxable_value' => (float) $item->taxable_value,
                    'cgst_amount' => (float) $item->cgst_amount,
                    'sgst_amount' => (float) $item->sgst_amount,
                    'igst_amount' => (float) $item->igst_amount,
                    'total_tax_amount' => (float) $item->total_tax_amount,
                    'subtotal' => (float) $item->subtotal,
                ];
            }),
            'tax_summary' => [
                'is_gst_enabled' => (bool) $this->is_gst_enabled,
                'taxable_amount' => (float) $this->taxable_amount,
                'total_cgst' => (float) $this->total_cgst,
                'total_sgst' => (float) $this->total_sgst,
                'total_igst' => (float) $this->total_igst,
                'total_tax' => (float) $this->total_tax,
            ],
            'financial_totals' => [
                'subtotal' => (float) $this->subtotal,
                'discount_amount' => (float) $this->discount_amount,
                'tax_total' => (float) $this->total_tax,
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'remaining_balance' => $remainingBalance,
                'change_returned' => (float) $this->change_returned,
            ],
            'payments' => $this->payments->map(function ($p) {
                $methodVal = is_object($p->payment_method) && property_exists($p->payment_method, 'value')
                    ? $p->payment_method->value
                    : (string) $p->payment_method;

                return [
                    'id' => $p->id,
                    'payment_method' => $methodVal,
                    'amount' => (float) $p->amount,
                    'transaction_reference' => $p->transaction_reference,
                    'notes' => $p->notes,
                    'payment_time' => $p->payment_time ? (is_string($p->payment_time) ? $p->payment_time : $p->payment_time->toIso8601String()) : null,
                ];
            }),
            'receipt_footer' => [
                'note' => 'Thank you for shopping at Rupsa Padukalaya!',
                'terms' => 'Goods once sold can be exchanged within 7 days with valid original invoice.',
            ],
        ];
    }
}
