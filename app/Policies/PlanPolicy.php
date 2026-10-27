<?php

namespace App\Policies;

use App\Contracts\AdminIdentity;
use App\Models\Plan;

/** Plans (and therefore the default limits) belong to the platform operator. */
class PlanPolicy
{
    public function viewAny(AdminIdentity $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(AdminIdentity $user, Plan $record): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(AdminIdentity $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(AdminIdentity $user, Plan $record): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(AdminIdentity $user, Plan $record): bool
    {
        return $user->isSuperAdmin() && ! $record->clients()->exists();
    }

    public function deleteAny(AdminIdentity $user): bool
    {
        return $user->isSuperAdmin();
    }
}
