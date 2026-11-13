<?php

namespace App\Filament\Resources\ClientRoles\Pages;

use App\Filament\Resources\ClientRoles\ClientRoleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditClientRole extends EditRecord
{
    protected static string $resource = ClientRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make(), RestoreAction::make(), ForceDeleteAction::make()->label('Delete permanently')];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['grid'] = ClientRoleResource::toGrid($data['permissions'] ?? []);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['permissions'] = ClientRoleResource::fromGrid($data['grid'] ?? []);
        unset($data['grid']);

        return $data;
    }
}
