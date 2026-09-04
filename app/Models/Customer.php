<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'mobile_number',
        'name',
        'email',
        'address',
        'city',
        'pincode',
        'reward_points',
        'total_purchases_count',
        'total_spent_amount',
        'notes',
    ];

    protected $casts = [
        'reward_points' => 'integer',
        'total_purchases_count' => 'integer',
        'total_spent_amount' => 'decimal:2',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function loyaltyAccount(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(LoyaltyAccount::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function getTotalSpentAmountAttribute($value): float
    {
        if ($value !== null) {
            return (float) $value;
        }

        if ($this->relationLoaded('invoices')) {
            return (float) $this->invoices->whereNotIn('status', ['cancelled', 'CANCELLED'])->sum('grand_total');
        }

        return (float) $this->invoices()
            ->where('status', '!=', 'cancelled')
            ->where('status', '!=', 'CANCELLED')
            ->sum('grand_total');
    }

    public function getTotalPurchasesCountAttribute($value): int
    {
        if ($value !== null) {
            return (int) $value;
        }

        if ($this->relationLoaded('invoices')) {
            return (int) $this->invoices->whereNotIn('status', ['cancelled', 'CANCELLED'])->count();
        }

        return (int) $this->invoices()
            ->where('status', '!=', 'cancelled')
            ->where('status', '!=', 'CANCELLED')
            ->count();
    }
}

