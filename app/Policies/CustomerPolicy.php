<?php

namespace App\Policies;

use App\Clients\ClientContext;
use App\Contracts\AdminIdentity;
use App\Models\Customer;
use App\Models\ClientUser;

/**
 * A client's shoppers. Both of a client's staff roles may look after their own customers;
 * nobody sees another client's, whichever store the two clients happen to share.
 */
class CustomerPolicy
{
    public function __construct(private readonly ClientContext $clients) {}

    public function viewAny(AdminIdentity $user): bool
    {
        return $user->isSuperAdmin() || ($user instanceof ClientUser && $user->isActiveAccount());
    }

    public function view(AdminIdentity $user, Customer $record): bool
    {
        return $this->owns($user, $record);
    }

    public function create(AdminIdentity $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(AdminIdentity $user, Customer $record): bool
    {
        return $this->owns($user, $record);
    }

    public function delete(AdminIdentity $user, Customer $record): bool
    {
        return $this->owns($user, $record) && ($user->isSuperAdmin() || ($user instanceof ClientUser && $user->isClientAdmin()));
    }

    public function deleteAny(AdminIdentity $user): bool
    {
        return $user->isSuperAdmin() || ($user instanceof ClientUser && $user->isClientAdmin());
    }

    private function owns(AdminIdentity $user, Customer $record): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $user->isSuperAdmin()
            ? $this->clients->owns($record->client_id)
            : $user->clientId() === $record->client_id && $this->clients->owns($record->client_id);
    }
}
