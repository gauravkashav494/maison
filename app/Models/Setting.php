<?php

namespace App\Models;

use App\Clients\ClientContext;
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
        $all = static::allCached();
        $own = static::clientKey($group);
        $value = ($own !== null ? ($all[$own] ?? null) : null) ?? $all[$group] ?? null;

        if ($path === null) {
            return $value ?? $default;
        }

        $found = Arr::get($value, $path);

        return ($found === null || $found === '') ? $default : $found;
    }

    public static function set(string $group, array $value): void
    {
        static::updateOrCreate(['key' => static::clientKey($group) ?? $group], ['value' => $value]);
        Cache::forget('settings.all');
    }

    /**
     * Key of the current client's own copy of a group, or null outside a client's request.
     *
     * Settings are stored per client, so two clients on one store never edit each other's
     * homepage. A group the client has not saved yet falls back to the platform-wide value.
     */
    private static function clientKey(string $group): ?string
    {
        $clients = app(ClientContext::class);

        return $clients->isLocked() && $clients->id() ? 'client:'.$clients->id().':'.$group : null;
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
