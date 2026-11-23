<?php

namespace App\Policies;

use App\Admin\PlatformModules;
use App\Contracts\AdminIdentity;
use App\Models\Plan;

/** Plans (and therefore the default limits) belong to the platform operator. */
class PlanPolicy
{
    public function viewAny(AdminIdentity $user): bool
    {
        return PlatformModules::allows($user, PlatformModules::PLANS);
    }

    public function view(AdminIdentity $user, Plan $record): bool
    {
        return PlatformModules::allows($user, PlatformModules::PLANS);
    }

    public function create(AdminIdentity $user): bool
    {
        return PlatformModules::allows($user, PlatformModules::PLANS);
    }

    public function update(AdminIdentity $user, Plan $record): bool
    {
        return PlatformModules::allows($user, PlatformModules::PLANS);
    }

    public function delete(AdminIdentity $user, Plan $record): bool
    {
        return PlatformModules::allows($user, PlatformModules::PLANS) && ! $record->clients()->exists();
    }

    public function deleteAny(AdminIdentity $user): bool
    {
        return PlatformModules::allows($user, PlatformModules::PLANS);
    }

    public function restore(AdminIdentity $user, Plan $record): bool
    {
        return PlatformModules::allows($user, PlatformModules::PLANS);
    }

    public function restoreAny(AdminIdentity $user): bool
    {
        return $this->deleteAny($user);
    }

    /** Deleting permanently follows the same rules as deleting. */
    public function forceDelete(AdminIdentity $user, Plan $record): bool
    {
        return $this->delete($user, $record);
    }

    public function forceDeleteAny(AdminIdentity $user): bool
    {
        return $this->deleteAny($user);
    }
}
