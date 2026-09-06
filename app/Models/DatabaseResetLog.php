<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseResetLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reset_type',
        'selected_categories',
        'backup_filename',
        'backup_file_size',
        'cleared_tables',
        'preserved_tables',
        'status',
        'error_message',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'selected_categories' => 'array',
        'cleared_tables' => 'array',
        'preserved_tables' => 'array',
        'backup_file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
