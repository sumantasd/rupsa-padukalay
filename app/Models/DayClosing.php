<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DayClosing extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'closing_number',
        'store_id',
        'pos_session_id',
        'closing_date',
        'total_sales_cash',
        'total_sales_card',
        'total_sales_upi',
        'total_sales_bank',
        'total_sales_other',
        'total_sales_grand',
        'total_collections_cash',
        'total_collections_digital',
        'total_collections_grand',
        'total_refunds_cash',
        'total_refunds_digital',
        'total_refunds_grand',
        'total_expenses',
        'opening_cash',
        'cash_received',
        'cash_paid_out',
        'expected_cash',
        'actual_cash',
        'variance',
        'notes',
        'closed_by',
        'reopened_by',
        'reopened_at',
        'reopen_reason',
        'status',
        'snapshot_data',
    ];

    protected $casts = [
        'closing_date' => 'date',
        'total_sales_cash' => 'decimal:2',
        'total_sales_card' => 'decimal:2',
        'total_sales_upi' => 'decimal:2',
        'total_sales_bank' => 'decimal:2',
        'total_sales_other' => 'decimal:2',
        'total_sales_grand' => 'decimal:2',
        'total_collections_cash' => 'decimal:2',
        'total_collections_digital' => 'decimal:2',
        'total_collections_grand' => 'decimal:2',
        'total_refunds_cash' => 'decimal:2',
        'total_refunds_digital' => 'decimal:2',
        'total_refunds_grand' => 'decimal:2',
        'total_expenses' => 'decimal:2',
        'opening_cash' => 'decimal:2',
        'cash_received' => 'decimal:2',
        'cash_paid_out' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'actual_cash' => 'decimal:2',
        'variance' => 'decimal:2',
        'reopened_at' => 'datetime',
        'snapshot_data' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }
}
