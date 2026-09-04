<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_id',
        'invoice_item_id',
        'product_variant_size_id',
        'quantity',
        'refund_unit_price',
        'restock_condition',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'refund_unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function returnSale(): BelongsTo
    {
        return $this->belongsTo(ReturnSale::class, 'return_id');
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function variantSize(): BelongsTo
    {
        return $this->belongsTo(ProductVariantSize::class, 'product_variant_size_id');
    }
}
