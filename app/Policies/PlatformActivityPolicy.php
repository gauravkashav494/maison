<?php

namespace App\Policies;

use App\Contracts\AdminIdentity;

/** The activity log is read-only, and only for a SuperAdmin. */
class PlatformActivityPolicy
{
    public function viewAny(AdminIdentity $user): bool
    {
        return $user->isSuperAdmin();
    }
}
