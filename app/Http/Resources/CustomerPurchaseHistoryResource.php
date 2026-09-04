<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerPurchaseHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statusVal = is_object($this->status) && property_exists($this->status, 'value')
            ? $this->status->value
            : (string) $this->status;

        $paymentStatusVal = is_object($this->payment_status) && property_exists($this->payment_status, 'value')
            ? $this->payment_status->value
            : (string) $this->payment_status;

        $saleTypeVal = is_object($this->sale_type) && property_exists($this->sale_type, 'value')
            ? $this->sale_type->value
            : (string) $this->sale_type;

        $paymentMethods = $this->payments->map(function ($p) {
            $m = $p->payment_method;
            return strtolower(is_object($m) && property_exists($m, 'value') ? $m->value : (string) $m);
        })->unique()->values()->toArray();

        if (empty($paymentMethods) && $this->payment_method) {
            $m = $this->payment_method;
            $paymentMethods = [strtolower(is_object($m) && property_exists($m, 'value') ? $m->value : (string) $m)];
        }

        $items = $this->items->map(function ($item) {
            return [
                'id' => $item->id,
                'product_variant_size_id' => $item->product_variant_size_id,
                'sku_snapshot' => $item->sku_snapshot,
                'article_number_snapshot' => $item->article_number_snapshot,
                'product_name_snapshot' => $item->product_name_snapshot,
                'color_name_snapshot' => $item->color_name_snapshot,
                'size_number_snapshot' => $item->size_number_snapshot,
                'hsn_code_snapshot' => $item->hsn_code_snapshot,
                'cost_price' => (float) $item->cost_price,
                'mrp' => (float) $item->mrp,
                'unit_price' => (float) $item->unit_price,
                'quantity' => (int) $item->quantity,
                'discount_amount' => (float) $item->discount_amount,
                'tax_rate_percentage' => (float) $item->tax_rate_percentage,
                'taxable_value' => (float) $item->taxable_value,
                'cgst_amount' => (float) $item->cgst_amount,
                'sgst_amount' => (float) $item->sgst_amount,
                'igst_amount' => (float) $item->igst_amount,
                'total_tax_amount' => (float) $item->total_tax_amount,
                'subtotal' => (float) $item->subtotal,
            ];
        });

        $payments = $this->payments->map(function ($p) {
            $mVal = is_object($p->payment_method) && property_exists($p->payment_method, 'value')
                ? $p->payment_method->value
                : (string) $p->payment_method;

            return [
                'id' => $p->id,
                'payment_method' => $mVal,
                'amount' => (float) $p->amount,
                'transaction_reference' => $p->transaction_reference,
                'notes' => $p->notes,
                'created_at' => $p->created_at ? $p->created_at->toIso8601String() : null,
            ];
        });


        $returns = $this->relationLoaded('returns') ? $this->returns->map(function ($r) {
            return [
                'id' => $r->id,
                'return_number' => $r->return_number ?? "RET-{$r->id}",
                'total_refund_amount' => (float) $r->total_refund_amount,
                'status' => is_object($r->status) && property_exists($r->status, 'value') ? $r->status->value : (string) $r->status,
                'created_at' => $r->created_at ? $r->created_at->toIso8601String() : null,
            ];
        }) : [];

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'client_trans_uuid' => $this->client_trans_uuid,
            'store_id' => $this->store_id,
            'store_name' => $this->store?->name ?? 'RUPSA PADUKALAYA - Main Outlet',
            'created_by_name' => $this->creator?->name ?? 'POS Staff',
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'is_gst_enabled' => (bool) $this->is_gst_enabled,
            'taxable_amount' => (float) $this->taxable_amount,
            'total_cgst' => (float) $this->total_cgst,
            'total_sgst' => (float) $this->total_sgst,
            'total_igst' => (float) $this->total_igst,
            'total_tax' => (float) $this->total_tax,
            'grand_total' => (float) $this->grand_total,
            'paid_amount' => (float) $this->paid_amount,
            'change_returned' => (float) $this->change_returned,
            'status' => $statusVal,
            'payment_status' => $paymentStatusVal,
            'sale_type' => $saleTypeVal,
            'payment_methods' => $paymentMethods,
            'items_count' => $this->items->count(),
            'total_items_quantity' => (int) $this->items->sum('quantity'),
            'items' => $items,
            'payments' => $payments,
            'returns' => $returns,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
