<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnSale extends Model
{
    use HasFactory;

    protected $table = 'returns';

    protected $fillable = [
        'return_number',
        'client_return_uuid',
        'original_invoice_id',
        'store_id',
        'customer_id',
        'total_refund_amount',
        'price_difference',
        'payment_method',
        'amount_paid',
        'refund_mode',
        'reason',
        'processed_by',
    ];

    protected $casts = [
        'total_refund_amount' => 'decimal:2',
        'price_difference' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'original_invoice_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }
}
