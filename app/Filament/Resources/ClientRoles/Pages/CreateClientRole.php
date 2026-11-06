<?php

namespace App\Filament\Resources\ClientRoles\Pages;

use App\Clients\ClientContext;
use App\Filament\Resources\ClientRoles\ClientRoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateClientRole extends CreateRecord
{
    protected static string $resource = ClientRoleResource::class;

    /** The client is taken from the resolved context, never from the submitted form. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['client_id'] = app(ClientContext::class)->id();
        $data['permissions'] = ClientRoleResource::fromGrid($data['grid'] ?? []);
        unset($data['grid']);

        return $data;
    }
}
