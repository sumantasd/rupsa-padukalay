<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiveItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'goods_receive_id',
        'purchase_order_item_id',
        'product_variant_size_id',
        'quantity_ordered',
        'quantity_received',
        'cost_price',
        'total_cost',
    ];

    protected $casts = [
        'quantity_ordered' => 'integer',
        'quantity_received' => 'integer',
        'cost_price' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function goodsReceive(): BelongsTo
    {
        return $this->belongsTo(GoodsReceive::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function variantSize(): BelongsTo
    {
        return $this->belongsTo(ProductVariantSize::class, 'product_variant_size_id');
    }
}
