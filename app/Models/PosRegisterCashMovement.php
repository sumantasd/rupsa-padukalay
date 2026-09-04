<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosRegisterCashMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'pos_session_id',
        'pos_register_id',
        'user_id',
        'movement_type',
        'amount',
        'reason',
        'reference_number',
        'client_trans_uuid',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'pos_session_id');
    }

    public function posRegister(): BelongsTo
    {
        return $this->belongsTo(PosRegister::class, 'pos_register_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
