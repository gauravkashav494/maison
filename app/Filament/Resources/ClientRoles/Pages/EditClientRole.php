<?php

namespace App\Filament\Resources\ClientRoles\Pages;

use App\Filament\Resources\ClientRoles\ClientRoleResource;
use App\Filament\Support\Pages\EditRecord;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;

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
