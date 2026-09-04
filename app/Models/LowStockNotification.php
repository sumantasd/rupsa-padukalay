<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LowStockNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'product_id',
        'product_variant_id',
        'product_variant_size_id',
        'notification_type',
        'current_quantity',
        'threshold_quantity',
        'reorder_quantity',
        'title',
        'message',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'current_quantity' => 'integer',
        'threshold_quantity' => 'integer',
        'reorder_quantity' => 'integer',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function variantSize(): BelongsTo
    {
        return $this->belongsTo(ProductVariantSize::class, 'product_variant_size_id');
    }
}
