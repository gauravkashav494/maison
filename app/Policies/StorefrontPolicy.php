<?php

namespace App\Policies;

use App\Models\Storefront;
use App\Models\User;

/** Stores (storefronts) are managed by super admins only. */
class StorefrontPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, Storefront $record): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Storefront $record): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Storefront $record): bool
    {
        return false; // a storefront mirrors a registered template; deactivate instead
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
