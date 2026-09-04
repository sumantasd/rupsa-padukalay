<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key_name',
        'key_value',
    ];

    public static function getSetting(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key_name', $key)->first();
        return $setting ? $setting->key_value : $default;
    }

    public static function setSetting(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key_name' => $key],
            ['key_value' => (string) $value]
        );
    }
}
