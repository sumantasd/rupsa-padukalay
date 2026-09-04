<?php

namespace App\Http\Resources;

use App\Enums\StockMovementType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $variantSize = $this->variantSize;
        $variant = $variantSize?->variant;
        $product = $variant?->product;

        $movementTypeValue = is_object($this->movement_type) && property_exists($this->movement_type, 'value')
            ? $this->movement_type->value
            : (string) $this->movement_type;

        $enum = StockMovementType::tryFrom($movementTypeValue);
        $movementLabel = $enum ? $enum->label() : strtoupper(str_replace('_', ' ', $movementTypeValue));

        $sizeNum = $variantSize?->size?->size_number ?? 'N/A';
        $sizeDisplay = is_numeric($sizeNum) || str_starts_with(strtoupper($sizeNum), 'IND') ? (str_starts_with(strtoupper($sizeNum), 'IND') ? $sizeNum : 'IND ' . $sizeNum) : $sizeNum;

        $refNumber = null;
        if ($this->reference_type && $this->reference_id) {
            try {
                if (str_contains($this->reference_type, 'Invoice')) {
                    $refObj = \App\Models\Invoice::find($this->reference_id);
                    $refNumber = $refObj?->invoice_number;
                } elseif (str_contains($this->reference_type, 'ReturnSale')) {
                    $refObj = \App\Models\ReturnSale::find($this->reference_id);
                    $refNumber = $refObj?->return_number;
                } elseif (str_contains($this->reference_type, 'PurchaseOrder')) {
                    $refObj = \App\Models\PurchaseOrder::find($this->reference_id);
                    $refNumber = $refObj?->order_number ?? $refObj?->po_number;
                } elseif (str_contains($this->reference_type, 'StockAdjustment')) {
                    $refObj = \App\Models\StockAdjustment::find($this->reference_id);
                    $refNumber = $refObj?->adjustment_number;
                }
            } catch (\Throwable $e) {
                // Ignore missing references
            }
        }

        return [
            'id' => $this->id,
            'movement_number' => 'MOV-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT),
            'product_variant_size_id' => $this->product_variant_size_id,
            'sku' => $variantSize?->sku ?? 'N/A',
            'barcode' => $variantSize?->barcode,
            'article_number' => $product?->article_number ?? 'N/A',
            'product_name' => $product?->name ?? 'Footwear Item',
            'brand_name' => $product?->brand?->name ?? '',
            'category_name' => $product?->category?->name ?? '',
            'color_name' => $variant?->color?->name ?? 'Std',
            'size_number' => $sizeNum,
            'size_display' => $sizeDisplay,
            'store_id' => $this->store_id,
            'store_code' => $this->store?->code ?? 'STR-001',
            'store_name' => $this->store?->name ?? 'RUPSA PADUKALAYA - Main Outlet',
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->warehouse?->name,
            'stock_location_id' => $this->stock_location_id,
            'stock_location_name' => $this->stockLocation?->name,
            'movement_type' => $movementTypeValue,
            'movement_label' => $movementLabel,
            'quantity_change' => (int) $this->quantity_change,
            'stock_before' => (int) $this->stock_before,
            'stock_after' => (int) $this->stock_after,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'reference_number' => $refNumber ?? ($this->reference_id ? "#{$this->reference_id}" : 'N/A'),
            'notes' => $this->notes ?? 'Stock movement recorded',
            'created_by' => $this->created_by,
            'creator_name' => $this->creator?->name ?? 'System Staff',
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
