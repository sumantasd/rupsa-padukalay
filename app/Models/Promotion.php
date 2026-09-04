<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'promotion_type',
        'discount_scope',
        'discount_value',
        'buy_quantity',
        'get_quantity',
        'get_discount_percentage',
        'min_cart_amount',
        'max_discount_amount',
        'store_id',
        'start_date',
        'end_date',
        'priority',
        'allow_stacking',
        'usage_limit',
        'usage_count',
        'usage_limit_per_customer',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'get_discount_percentage' => 'decimal:2',
        'min_cart_amount' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'allow_stacking' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(PromotionTarget::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PromotionUsage::class);
    }
}
