<?php

namespace App\Policies;

use App\Clients\ClientContext;
use App\Contracts\AdminIdentity;
use App\Models\ClientRole;
use App\Models\ClientUser;

/** Roles decide what staff may do, so only a client's own Client Admin may change them. */
class ClientRolePolicy
{
    public function __construct(private readonly ClientContext $clients) {}

    public function viewAny(AdminIdentity $user): bool
    {
        return $this->isClientAdmin($user);
    }

    public function view(AdminIdentity $user, ClientRole $record): bool
    {
        return $this->mayManage($user, $record);
    }

    public function create(AdminIdentity $user): bool
    {
        return $this->isClientAdmin($user);
    }

    public function update(AdminIdentity $user, ClientRole $record): bool
    {
        return $this->mayManage($user, $record);
    }

    public function delete(AdminIdentity $user, ClientRole $record): bool
    {
        return $this->mayManage($user, $record);
    }

    public function deleteAny(AdminIdentity $user): bool
    {
        return $this->isClientAdmin($user);
    }

    public function restore(AdminIdentity $user, ClientRole $record): bool
    {
        return $this->mayManage($user, $record);
    }

    public function restoreAny(AdminIdentity $user): bool
    {
        return $this->deleteAny($user);
    }

    /** Deleting permanently follows the same rules as deleting. */
    public function forceDelete(AdminIdentity $user, ClientRole $record): bool
    {
        return $this->delete($user, $record);
    }

    public function forceDeleteAny(AdminIdentity $user): bool
    {
        return $this->deleteAny($user);
    }

    private function isClientAdmin(AdminIdentity $user): bool
    {
        return $user instanceof ClientUser && $user->isActiveAccount() && $user->isClientAdmin();
    }

    private function mayManage(AdminIdentity $user, ClientRole $record): bool
    {
        return $this->isClientAdmin($user)
            && $user->clientId() === (int) $record->client_id
            && $this->clients->owns($record->client_id);
    }
}
