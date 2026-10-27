<?php

namespace App\Policies;

use App\Contracts\AdminIdentity;
use App\Models\Store;

/**
 * Store locations (the boutique locator) are platform content with no client of their own,
 * so only a SuperAdmin manages them.
 */
class StorePolicy
{
    public function viewAny(AdminIdentity $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(AdminIdentity $user, Store $record): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(AdminIdentity $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(AdminIdentity $user, Store $record): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(AdminIdentity $user, Store $record): bool
    {
        return $user->isSuperAdmin();
    }

    public function deleteAny(AdminIdentity $user): bool
    {
        return $user->isSuperAdmin();
    }
}
