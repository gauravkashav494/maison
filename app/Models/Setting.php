<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected $casts = ['value' => 'array'];

    /** Get a setting group (e.g. "site") or a nested value via dot notation ("site.name"). */
    public static function get(string $key, mixed $default = null): mixed
    {
        [$group, $path] = array_pad(explode('.', $key, 2), 2, null);
        $value = static::allCached()[$group] ?? null;

        if ($path === null) {
            return $value ?? $default;
        }

        $found = Arr::get($value, $path);

        return ($found === null || $found === '') ? $default : $found;
    }

    public static function set(string $group, array $value): void
    {
        static::updateOrCreate(['key' => $group], ['value' => $value]);
        Cache::forget('settings.all');
    }

    public static function allCached(): array
    {
        return Cache::rememberForever('settings.all', fn () => static::query()->pluck('value', 'key')->all());
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('settings.all'));
        static::deleted(fn () => Cache::forget('settings.all'));
    }
}
