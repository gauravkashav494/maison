<?php

namespace App\Admin;

use App\Contracts\AdminIdentity;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Storefront;
use App\Models\User;

/**
 * The modules of the platform panel (/admin) that can be handed to platform staff.
 *
 * A Super Admin (`users.is_super_admin`) has every module, plus the ones that are never
 * delegated: platform accounts themselves and platform settings. Platform staff have only the
 * modules ticked on their account. A client's staff account never has any of them.
 */
class PlatformModules
{
    public const CLIENTS = 'clients';

    public const STOREFRONTS = 'storefronts';

    public const PLANS = 'plans';

    public const TEMPLATES = 'templates';

    public const REPORTS = 'reports';

    public const ALL = [
        self::CLIENTS => 'Clients and client admins',
        self::STOREFRONTS => 'Stores',
        self::PLANS => 'Plans',
        self::TEMPLATES => 'Templates',
        self::REPORTS => 'Client totals report',
    ];

    /** May this account use the module on /admin? Unknown modules and client accounts are refused. */
    public static function allows(?AdminIdentity $user, string $module): bool
    {
        if (! $user instanceof User || ! $user->isActiveAccount() || ! array_key_exists($module, self::ALL)) {
            return false;
        }

        return $user->isSuperAdmin() || in_array($module, $user->admin_modules ?? [], true);
    }

    /** The same check for whoever is signed into /admin on this request. */
    public static function current(string $module): bool
    {
        $user = filament()->auth()->user();

        return self::allows($user instanceof AdminIdentity ? $user : null, $module);
    }

    /** The platform module a model's records belong to, for checks that only have a record. */
    public static function forModel(string $model): ?string
    {
        return match ($model) {
            Client::class, ClientUser::class => self::CLIENTS,
            Storefront::class => self::STOREFRONTS,
            Plan::class => self::PLANS,
            default => null,
        };
    }

    /** Keeps only known module keys, in a stable order. */
    public static function clean(?array $modules): array
    {
        return array_values(array_intersect(array_keys(self::ALL), $modules ?? []));
    }
}
