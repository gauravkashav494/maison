<?php

namespace App\Policies;

use App\Clients\ClientAccess;
use App\Clients\ClientContext;
use App\Contracts\AdminIdentity;
use App\Models\Customer;

/**
 * A client's shoppers, reached only by the client's own staff whose role allows it. Nobody sees
 * another client's, whichever store the two clients happen to share, and a SuperAdmin sees
 * totals rather than people.
 */
class CustomerPolicy
{
    public function __construct(private readonly ClientContext $clients) {}

    public function viewAny(AdminIdentity $user): bool
    {
        return ClientAccess::allows($user, 'customers', 'view');
    }

    public function view(AdminIdentity $user, Customer $record): bool
    {
        return ClientAccess::allows($user, 'customers', 'view') && $this->owns($user, $record);
    }

    public function create(AdminIdentity $user): bool
    {
        return ClientAccess::allows($user, 'customers', 'create');
    }

    public function update(AdminIdentity $user, Customer $record): bool
    {
        return ClientAccess::allows($user, 'customers', 'update') && $this->owns($user, $record);
    }

    public function delete(AdminIdentity $user, Customer $record): bool
    {
        return ClientAccess::allows($user, 'customers', 'delete') && $this->owns($user, $record);
    }

    public function deleteAny(AdminIdentity $user): bool
    {
        return ClientAccess::allows($user, 'customers', 'delete');
    }

    private function owns(AdminIdentity $user, Customer $record): bool
    {
        return $user->clientId() === (int) $record->client_id && $this->clients->owns($record->client_id);
    }
}
