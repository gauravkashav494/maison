<?php

namespace App\Clients;

use App\Admin\PlatformModules;
use App\Admin\StoreContext;
use App\Contracts\AdminIdentity;
use App\Models\ClientUser;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Model;

/**
 * May this account use this module? Every check on a client's business data ends here.
 *
 * A platform account has no access to a client's data at all. The modules ticked on the client
 * are the ceiling for its whole team: a module switched off is gone for the Client Admin too
 * (and the policies still hide modules the store does not support, e.g. services on a catalogue
 * store). Within that ceiling the Client Admin holds every action, while Admin Staff get only
 * what their role grants. Anything unknown — no account, no client, no such module — is refused.
 */
class ClientAccess
{
    public static function allows(?AdminIdentity $user, string $module, string $action): bool
    {
        if (! $user instanceof ClientUser || ! $user->isActiveAccount()) {
            return false;
        }

        if (ClientModules::get($module) === null) {
            return false;
        }

        if (! $user->client->hasModule($module)) {
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
        // Platform staff edit inline only in the platform modules they were given.
        if ($context->user()?->isPlatformAccount()) {
            $module = $record ? PlatformModules::forModel($record::class) : null;

            return $module !== null && PlatformModules::allows($context->user(), $module);
        }

        $model = $record instanceof MenuItem ? Menu::class : ($record ? $record::class : null);
        $module = $model ? ClientModules::forModel($model) : null;

        return $module !== null && self::current($module, 'update');
    }
}
