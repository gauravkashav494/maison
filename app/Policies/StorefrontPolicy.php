<?php

namespace App\Policies;

use App\Contracts\AdminIdentity;
use App\Models\Storefront;

/** Stores (the templates clients rent) are managed by SuperAdmins only. */
class StorefrontPolicy
{
    public function viewAny(AdminIdentity $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(AdminIdentity $user, Storefront $record): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(AdminIdentity $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(AdminIdentity $user, Storefront $record): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(AdminIdentity $user, Storefront $record): bool
    {
        return false; // a storefront mirrors a registered template; deactivate instead
    }

    public function deleteAny(AdminIdentity $user): bool
    {
        return false;
    }
}
