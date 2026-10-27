<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Clients\ClientContext;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

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
