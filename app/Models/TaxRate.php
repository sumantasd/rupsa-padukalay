<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'rate_percentage',
        'cgst_percentage',
        'sgst_percentage',
        'igst_percentage',
        'is_active',
    ];

    protected $casts = [
        'rate_percentage' => 'decimal:2',
        'cgst_percentage' => 'decimal:2',
        'sgst_percentage' => 'decimal:2',
        'igst_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
