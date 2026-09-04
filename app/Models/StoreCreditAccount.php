<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreCreditAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'current_balance',
        'total_issued',
        'total_used',
        'status',
    ];

    protected $casts = [
        'current_balance' => 'decimal:2',
        'total_issued' => 'decimal:2',
        'total_used' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(StoreCreditTransaction::class, 'customer_id', 'customer_id');
    }
}
