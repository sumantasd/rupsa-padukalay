<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\SaleType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'client_trans_uuid',
        'store_id',
        'pos_session_id',
        'customer_id',
        'subtotal',
        'discount_amount',
        'is_gst_enabled',
        'taxable_amount',
        'total_cgst',
        'total_sgst',
        'total_igst',
        'total_tax',
        'grand_total',
        'paid_amount',
        'change_returned',
        'payment_status',
        'sale_type',
        'status',
        'created_by',
    ];

    protected $casts = [
        'is_gst_enabled' => 'boolean',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'total_cgst' => 'decimal:2',
        'total_sgst' => 'decimal:2',
        'total_igst' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'change_returned' => 'decimal:2',
        'sale_type' => SaleType::class,
        'status' => InvoiceStatus::class,
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ReturnSale::class, 'original_invoice_id');
    }
}
