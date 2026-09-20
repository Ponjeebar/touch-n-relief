<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function valueFor(string $key, ?string $default = null): ?string
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('site_settings')) {
            return $default;
        }

        $value = static::query()->where('key', $key)->value('value');

        return $value !== null && $value !== '' ? (string) $value : $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }
}
