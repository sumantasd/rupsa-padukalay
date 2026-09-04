<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_variant_size_id',
        'store_id',
        'warehouse_id',
        'stock_location_id',
        'stock_quantity',
        'reorder_level',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'warehouse_id' => 'integer',
        'stock_location_id' => 'integer',
        'stock_quantity' => 'integer',
        'reorder_level' => 'integer',
    ];

    public function variantSize(): BelongsTo
    {
        return $this->belongsTo(ProductVariantSize::class, 'product_variant_size_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }
}
