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

    /** Deleting moves the client to the trash (soft delete): staff, customers and its database are kept, and it can be restored. */
    public function delete(AdminIdentity $user, Client $record): bool
    {
        return $user->isSuperAdmin();
    }

    public function deleteAny(AdminIdentity $user): bool
    {
        return false; // one client at a time
    }

    public function restore(AdminIdentity $user, Client $record): bool
    {
        return $user->isSuperAdmin();
    }

    public function restoreAny(AdminIdentity $user): bool
    {
        return false;
    }

    /** Never: a client row is what names its database, so removing it would orphan that database. */
    public function forceDelete(AdminIdentity $user, Client $record): bool
    {
        return false;
    }

    public function forceDeleteAny(AdminIdentity $user): bool
    {
        return false;
    }
}
