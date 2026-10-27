<?php

namespace App\Policies;

use App\Clients\ClientContext;
use App\Contracts\AdminIdentity;
use App\Models\ClientUser;

/**
 * Staff accounts: the platform operator manages all of them, a Client Admin manages its own
 * client's accounts, and Admin Staff manage none. Every record check compares the account's
 * client with the client resolved for this request, so an id from another client is refused
 * even if it reaches the route.
 */
class ClientUserPolicy
{
    public function __construct(private readonly ClientContext $clients) {}

    public function viewAny(AdminIdentity $user): bool
    {
        return $user->isSuperAdmin() || $this->isClientAdmin($user);
    }

    public function view(AdminIdentity $user, ClientUser $record): bool
    {
        return $this->mayManage($user, $record);
    }

    public function create(AdminIdentity $user): bool
    {
        return $user->isSuperAdmin() || $this->isClientAdmin($user);
    }

    public function update(AdminIdentity $user, ClientUser $record): bool
    {
        return $this->mayManage($user, $record);
    }

    public function delete(AdminIdentity $user, ClientUser $record): bool
    {
        if (! $this->mayManage($user, $record)) {
            return false;
        }
        if ($user instanceof ClientUser && $record->is($user)) {
            return false; // never delete the account you are signed in with
        }

        // A client must keep at least one active Client Admin.
        return ! ($record->isClientAdmin() && ClientUser::where('client_id', $record->client_id)
            ->where('role', ClientUser::ROLE_CLIENT_ADMIN)->where('is_active', true)->count() <= 1);
    }

    public function deleteAny(AdminIdentity $user): bool
    {
        return $user->isSuperAdmin() || $this->isClientAdmin($user);
    }

    private function isClientAdmin(AdminIdentity $user): bool
    {
        return $user instanceof ClientUser && $user->isActiveAccount() && $user->isClientAdmin();
    }

    private function mayManage(AdminIdentity $user, ClientUser $record): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $this->isClientAdmin($user)
            && $user->clientId() === $record->client_id
            && $this->clients->owns($record->client_id);
    }
}
