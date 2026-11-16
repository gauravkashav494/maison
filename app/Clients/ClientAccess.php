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
 * A platform account has no access to a client's data at all. A Client Admin holds every action
 * of every module, whatever is ticked on the client (the policies still hide modules the store
 * does not support, e.g. services on a catalogue store). Admin Staff need both: the module
 * switched on for the client and their role granting the action. Anything unknown — no
 * account, no client, no such module — is refused.
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

        if ($user->isClientAdmin()) {
            return true;
        }

        return $user->client->hasModule($module) && $user->hasPermission($module.'.'.$action);
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
