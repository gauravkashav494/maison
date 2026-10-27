<?php

namespace App\Filament\Resources\ClientUsers\Pages;

use App\Filament\Resources\ClientUsers\ClientUserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditClientUser extends EditRecord
{
    protected static string $resource = ClientUserResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /** The client of an existing account never changes through this form. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['client_id']);

        return $data;
    }
}
