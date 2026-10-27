<?php

namespace App\Policies;

use App\Contracts\AdminIdentity;
use App\Models\User;

/**
 * SuperAdmin accounts: a platform-only module. A client's staff account reaching this policy
 * fails every check, because only App\Models\User answers true to isSuperAdmin(). Nobody may
 * delete themselves or the last active super admin.
 */
class UserPolicy
{
    public function viewAny(AdminIdentity $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(AdminIdentity $user, User $record): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(AdminIdentity $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(AdminIdentity $user, User $record): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(AdminIdentity $user, User $record): bool
    {
        if (! $user->isSuperAdmin() || ($user instanceof User && $record->is($user))) {
            return false;
        }

        return ! ($record->isSuperAdmin() && User::where('role', User::ROLE_SUPER_ADMIN)->where('is_active', true)->count() <= 1);
    }

    public function deleteAny(AdminIdentity $user): bool
    {
        return $user->isSuperAdmin();
    }
}
