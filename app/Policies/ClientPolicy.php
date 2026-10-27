<?php

namespace App\Policies;

use App\Contracts\AdminIdentity;
use App\Models\Client;

/**
 * Clients are created, assigned a store and given their limits by the platform operator only.
 * A client's own staff never reach this module: they cannot see other clients exist, let
 * alone edit one.
 */
class ClientPolicy
{
    public function viewAny(AdminIdentity $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(AdminIdentity $user, Client $record): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(AdminIdentity $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(AdminIdentity $user, Client $record): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(AdminIdentity $user, Client $record): bool
    {
        // Deleting a client removes its staff and customers with it; deactivate instead.
        return false;
    }

    public function deleteAny(AdminIdentity $user): bool
    {
        return false;
    }
}
