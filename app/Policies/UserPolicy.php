<?php

namespace App\Policies;

use App\Models\User;

/** User management is a super-admin module; nobody may delete themselves or the last super admin. */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, User $record): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, User $record): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, User $record): bool
    {
        if (! $user->isSuperAdmin() || $record->is($user)) {
            return false;
        }

        return ! ($record->isSuperAdmin() && User::where('role', User::ROLE_SUPER_ADMIN)->where('is_active', true)->count() <= 1);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
