<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockDamageItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_damage_transaction_id',
        'product_variant_size_id',
        'quantity',
        'available_stock_before',
        'available_stock_after',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(StockDamageTransaction::class, 'stock_damage_transaction_id');
    }

    public function productVariantSize(): BelongsTo
    {
        return $this->belongsTo(ProductVariantSize::class, 'product_variant_size_id');
    }
}
