<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariantSize extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_variant_id',
        'size_id',
        'sku',
        'barcode',
        'cost_price',
        'mrp',
        'selling_price',
        'low_stock_threshold',
        'reorder_quantity',
        'is_active',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'mrp' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'low_stock_threshold' => 'integer',
        'reorder_quantity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->variant();
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function inventoryStocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function lowStockNotifications(): HasMany
    {
        return $this->hasMany(LowStockNotification::class);
    }
}
