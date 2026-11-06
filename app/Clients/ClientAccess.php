<?php

namespace App\Clients;

use App\Admin\StoreContext;
use App\Contracts\AdminIdentity;
use App\Models\ClientUser;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Model;

/**
 * May this account use this module? Every check on a client's business data ends here.
 *
 * Three independent answers must all be yes: the SuperAdmin has no access to a client's data at
 * all; the module must be switched on for the client; and a staff account's role must grant the
 * action. A Client Admin holds every action of the modules their client has. Anything unknown
 * — no account, no client, no such module — is refused.
 */
class ClientAccess
{
    public static function allows(?AdminIdentity $user, string $module, string $action): bool
    {
        if (! $user instanceof ClientUser || ! $user->isActiveAccount()) {
            return false;
        }

        if (ClientModules::get($module) === null || ! $user->client->hasModule($module)) {
            return false;
        }

        return $user->isClientAdmin() || $user->hasPermission($module.'.'.$action);
    }

    /** The same check for whoever is signed into the current request. */
    public static function current(string $module, string $action): bool
    {
        return self::allows(app(StoreContext::class)->user(), $module, $action);
    }

    /**
     * Inline table edits (toggles, status selects) write without calling a policy, so they are
     * guarded here. Panel-level records outside the catalogue are the SuperAdmin's own.
     */
    public static function canEditInline(?Model $record): bool
    {
        $context = app(StoreContext::class);
        if ($context->isSuperAdmin()) {
            return true;
        }

        $model = $record instanceof MenuItem ? \App\Models\Menu::class : ($record ? $record::class : null);
        $module = $model ? ClientModules::forModel($model) : null;

        return $module !== null && self::current($module, 'update');
    }
}
