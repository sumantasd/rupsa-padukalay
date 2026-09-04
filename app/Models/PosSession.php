<?php

namespace App\Models;

use App\Enums\PosSessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_session_uuid',
        'pos_register_id',
        'store_id',
        'user_id',
        'opened_at',
        'closed_at',
        'opening_cash',
        'closing_cash_system',
        'closing_cash_actual',
        'cash_difference',
        'status',
        'notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_cash' => 'decimal:2',
        'closing_cash_system' => 'decimal:2',
        'closing_cash_actual' => 'decimal:2',
        'cash_difference' => 'decimal:2',
        'status' => PosSessionStatus::class,
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function posRegister(): BelongsTo
    {
        return $this->belongsTo(PosRegister::class, 'pos_register_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(PosRegisterCashMovement::class, 'pos_session_id');
    }
}
