<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExchangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $refundModeValue = is_object($this->refund_mode) && property_exists($this->refund_mode, 'value')
            ? $this->refund_mode->value
            : (string) $this->refund_mode;

        $returnedItems = $this->relationLoaded('items')
            ? ExchangeItemResource::collection($this->items)->toArray($request)
            : [];
        
        $returnedTotal = (float) ($this->relationLoaded('items') ? $this->items->sum('subtotal') : 0.0);
        
        $replacementItems = [];
        if ($this->relationLoaded('replacementMovements')) {
            $replacementItems = $this->replacementMovements->map(function ($movement) {
                $unitPrice = (float) ($movement->variantSize?->selling_price ?? 0.0);
                $mrp = (float) ($movement->variantSize?->mrp ?? $unitPrice);
                $qty = abs((int) $movement->quantity_change);
                $lineSubtotal = round($qty * $unitPrice, 2);

                return [
                    'id' => $movement->id,
                    'product_variant_size_id' => $movement->product_variant_size_id,
                    'sku' => $movement->variantSize?->sku ?? 'N/A',
                    'article_number' => $movement->variantSize?->variant?->product?->article_number ?? 'N/A',
                    'product_name' => $movement->variantSize?->variant?->product?->name ?? 'Footwear Item',
                    'brand_name' => $movement->variantSize?->variant?->product?->brand?->name ?? '',
                    'color' => $movement->variantSize?->variant?->color?->name ?? 'Std',
                    'color_name' => $movement->variantSize?->variant?->color?->name ?? 'Std',
                    'size' => $movement->variantSize?->size?->size_number ?? 'N/A',
                    'size_number' => $movement->variantSize?->size?->size_number ?? 'N/A',
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'selling_price' => $unitPrice,
                    'mrp' => $mrp,
                    'subtotal' => $lineSubtotal,
                ];
            })->toArray();
        }

        $replacementTotal = ! empty($replacementItems)
            ? (float) collect($replacementItems)->sum('subtotal') 
            : 0.0;

        $priceDifference = isset($this->price_difference) ? (float) $this->price_difference : round($replacementTotal - $returnedTotal, 2);

        $summary = 'equal_exchange';
        if ($priceDifference > 0) {
            $summary = 'additional_payment';
        } elseif ($priceDifference < 0) {
            $summary = 'refund';
        }

        return [
            'id' => $this->id,
            'exchange_number' => $this->return_number,
            'client_exchange_uuid' => $this->client_return_uuid,
            'original_invoice_id' => $this->original_invoice_id,
            'original_invoice_number' => $this->originalInvoice?->invoice_number,
            'store_id' => $this->store_id,
            'store_code' => $this->store?->code ?? 'STR-001',
            'store_name' => $this->store?->name ?? 'RUPSA PADUKALAYA - Main Outlet',
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer?->name ?? 'Walk-in Customer',
            'customer_mobile' => $this->customer?->mobile_number,
            'customer_address' => $this->customer?->address,
            'returned_total' => $returnedTotal,
            'replacement_total' => $replacementTotal,
            'price_difference' => $priceDifference,
            'amount_paid' => (float) ($this->amount_paid ?? ($priceDifference > 0 ? $priceDifference : 0.0)),
            'total_refund_amount' => (float) ($this->total_refund_amount ?? ($priceDifference < 0 ? abs($priceDifference) : 0.0)),
            'payment_status_summary' => $summary,
            'refund_mode' => $refundModeValue,
            'payment_method' => $this->payment_method ?? $refundModeValue,
            'reason' => $this->reason,
            'processed_by' => $this->processed_by,
            'processed_by_name' => $this->processor?->name ?? 'Staff',
            'returned_items' => $returnedItems,
            'replacement_items' => $replacementItems,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }
}
