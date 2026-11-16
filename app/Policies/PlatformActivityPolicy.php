<?php

namespace App\Policies;

use App\Contracts\AdminIdentity;

/**
 * The activity log is read-only and open to every platform account: a Super Admin sees
 * everyone's activity, platform staff only their own (PlatformActivityResource::getEloquentQuery).
 * A client's staff never reach it.
 */
class PlatformActivityPolicy
{
    public function viewAny(AdminIdentity $user): bool
    {
        return $user->isPlatformAccount() && $user->isActiveAccount();
    }
}
