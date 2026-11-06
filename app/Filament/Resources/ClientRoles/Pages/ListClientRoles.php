<?php

namespace App\Filament\Resources\ClientRoles\Pages;

use App\Filament\Resources\ClientRoles\ClientRoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClientRoles extends ListRecords
{
    protected static string $resource = ClientRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
