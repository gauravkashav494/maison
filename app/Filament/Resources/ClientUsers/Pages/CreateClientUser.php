<?php

namespace App\Filament\Resources\ClientUsers\Pages;

use App\Clients\ClientContext;
use App\Filament\Resources\ClientUsers\ClientUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateClientUser extends CreateRecord
{
    protected static string $resource = ClientUserResource::class;

    /** The client is taken from the resolved context, never from the submitted form. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $clients = app(ClientContext::class);
        if ($clients->id()) {
            $data['client_id'] = $clients->id();
        }

        return $data;
    }
}
