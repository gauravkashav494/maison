<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

/** Store locations (the boutique locator) have no template owner, so only super admins manage them. */
class StorePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, Store $record): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Store $record): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Store $record): bool
    {
        return $user->isSuperAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
