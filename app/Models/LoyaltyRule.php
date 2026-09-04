<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoyaltyRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'rule_name',
        'earn_rate_amount',
        'earn_points',
        'redeem_point_value',
        'min_qualifying_amount',
        'min_redemption_points',
        'max_redemption_points',
        'is_active',
    ];

    protected $casts = [
        'earn_rate_amount' => 'decimal:2',
        'redeem_point_value' => 'decimal:2',
        'min_qualifying_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
