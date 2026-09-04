<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false; // Only created_at column exists

    protected $fillable = [
        'audit_uuid',
        'user_id',
        'store_id',
        'pos_session_id',
        'pos_register_id',
        'module',
        'event_type',
        'auditable_type',
        'auditable_id',
        'client_trans_uuid',
        'before_state',
        'after_state',
        'changed_fields',
        'status',
        'ip_address',
        'user_agent',
        'reason_notes',
        'created_at',
    ];

    protected $casts = [
        'before_state' => 'array',
        'after_state' => 'array',
        'changed_fields' => 'array',
        'created_at' => 'datetime',
    ];

    // Enforce Immutability: Prevent update or delete
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \RuntimeException('Audit log entries are strictly immutable and cannot be updated or deleted.', 403);
    }

    public function delete(): ?bool
    {
        throw new \RuntimeException('Audit log entries are strictly immutable and cannot be updated or deleted.', 403);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'pos_session_id');
    }

    public function posRegister(): BelongsTo
    {
        return $this->belongsTo(PosRegister::class, 'pos_register_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
