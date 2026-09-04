<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosSyncConflict extends Model
{
    use HasFactory;

    protected $table = 'pos_sync_conflicts';

    protected $fillable = [
        'client_trans_uuid',
        'store_id',
        'pos_session_id',
        'user_id',
        'invoice_id',
        'conflict_type',
        'conflict_reason',
        'payload_snapshot',
        'status',
        'resolution_action',
        'resolution_notes',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'payload_snapshot' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
