<?php

namespace App\Models;

use App\Clients\ClientContext;
use App\Models\Concerns\UsesPlatformConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use UsesPlatformConnection;

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected $casts = ['value' => 'array'];

    /** Groups every client owns outright: without its own copy a client gets config('client_defaults'), never the platform's. */
    public const CLIENT_OWNED = ['site', 'seo', 'checkout', 'login', 'reviews'];

    /** Get a setting group (e.g. "site") or a nested value via dot notation ("site.name"). */
    public static function get(string $key, mixed $default = null): mixed
    {
        [$group, $path] = array_pad(explode('.', $key, 2), 2, null);
        $all = static::allCached();
        $own = static::clientKey($group);
        $value = match (true) {
            $own === null => $all[$group] ?? null,
            in_array($group, self::CLIENT_OWNED, true) => $all[$own] ?? static::clientDefaults($group),
            default => $all[$own] ?? $all[$group] ?? null,
        };

        if ($path === null) {
            return $value ?? $default;
        }

        $found = Arr::get($value, $path);

        return ($found === null || $found === '') ? $default : $found;
    }

    /** A specific client's own saved copy of a group (null if it never saved one), regardless of the request. */
    public static function forClient(int $clientId, string $group): ?array
    {
        $value = static::allCached()['client:'.$clientId.':'.$group] ?? null;

        return is_array($value) ? $value : null;
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
     * homepage. A group the client has not saved yet falls back to the platform-wide value,
     * except the CLIENT_OWNED groups, which start from config('client_defaults').
     */
    private static function clientKey(string $group): ?string
    {
        $clients = app(ClientContext::class);

        return $clients->isLocked() && $clients->id() ? 'client:'.$clients->id().':'.$group : null;
    }

    /** Starting values for a client-owned group, with ":name" filled in from the current client. */
    private static function clientDefaults(string $group): ?array
    {
        $defaults = config('client_defaults.'.$group);
        if (! is_array($defaults)) {
            return null;
        }
        $name = (string) (app(ClientContext::class)->client()?->name ?? '');

        array_walk_recursive($defaults, function (&$value) use ($name) {
            if (is_string($value)) {
                $value = str_replace(':name', $name, $value);
            }
        });

        return $defaults;
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
