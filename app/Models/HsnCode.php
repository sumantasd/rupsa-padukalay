<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HsnCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'default_gst_rate',
    ];

    protected $casts = [
        'default_gst_rate' => 'decimal:2',
    ];
}
