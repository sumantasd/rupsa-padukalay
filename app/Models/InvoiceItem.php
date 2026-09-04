<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'product_variant_size_id',
        'sku_snapshot',
        'article_number_snapshot',
        'product_name_snapshot',
        'color_name_snapshot',
        'size_number_snapshot',
        'hsn_code_snapshot',
        'cost_price',
        'mrp',
        'unit_price',
        'quantity',
        'discount_amount',
        'tax_rate_percentage',
        'taxable_value',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'total_tax_amount',
        'subtotal',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'mrp' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
        'discount_amount' => 'decimal:2',
        'tax_rate_percentage' => 'decimal:2',
        'taxable_value' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
        'total_tax_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function variantSize(): BelongsTo
    {
        return $this->belongsTo(ProductVariantSize::class, 'product_variant_size_id');
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'invoice_item_id');
    }

    public function getAlreadyReturnedQuantityAttribute(): int
    {
        if (! $this->relationLoaded('returnItems')) {
            return (int) ReturnItem::where('invoice_item_id', $this->id)
                ->whereHas('returnSale', function ($q) {
                    $q->where('refund_mode', '!=', 'exchange_offset')
                        ->where(function ($sq) {
                            $sq->whereNull('reason')->orWhere('reason', 'NOT LIKE', '%Exchange%');
                        });
                })->sum('quantity');
        }

        return (int) $this->returnItems->filter(function ($item) {
            $ret = $item->returnSale;
            if (! $ret) return false;
            return $ret->refund_mode !== 'exchange_offset' && (empty($ret->reason) || stripos($ret->reason, 'Exchange') === false);
        })->sum('quantity');
    }

    public function getAlreadyExchangedQuantityAttribute(): int
    {
        if (! $this->relationLoaded('returnItems')) {
            return (int) ReturnItem::where('invoice_item_id', $this->id)
                ->whereHas('returnSale', function ($q) {
                    $q->where('refund_mode', 'exchange_offset')
                        ->orWhere('reason', 'LIKE', '%Exchange%');
                })->sum('quantity');
        }

        return (int) $this->returnItems->filter(function ($item) {
            $ret = $item->returnSale;
            if (! $ret) return false;
            return $ret->refund_mode === 'exchange_offset' || (isset($ret->reason) && stripos($ret->reason, 'Exchange') !== false);
        })->sum('quantity');
    }

    public function getRemainingExchangeableQuantityAttribute(): int
    {
        $returned = ! $this->relationLoaded('returnItems')
            ? (int) ReturnItem::where('invoice_item_id', $this->id)->sum('quantity')
            : (int) $this->returnItems->sum('quantity');

        return max(0, (int) $this->quantity - $returned);
    }
}
