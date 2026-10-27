<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /** A customer never moves to another client. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['client_id']);

        return $data;
    }
}
