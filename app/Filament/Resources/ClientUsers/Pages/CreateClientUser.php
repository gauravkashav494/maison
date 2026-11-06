<?php

namespace App\Filament\Resources\ClientUsers\Pages;

use App\Clients\ClientContext;
use App\Filament\Resources\ClientUsers\ClientUserResource;
use App\Models\ClientUser;
use Filament\Resources\Pages\CreateRecord;

class CreateClientUser extends CreateRecord
{
    protected static string $resource = ClientUserResource::class;

    /**
     * The client is taken from the resolved context, never from the submitted form. A SuperAdmin
     * chooses the client but can only create its Client Admin; the rest of the team is theirs.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $clients = app(ClientContext::class);
        if ($clients->id()) {
            $data['client_id'] = $clients->id();
        }
        if ($clients->isSuperAdmin()) {
            $data['role'] = ClientUser::ROLE_CLIENT_ADMIN;
            $data['client_role_id'] = null;
        }

        return $data;
    }
}
