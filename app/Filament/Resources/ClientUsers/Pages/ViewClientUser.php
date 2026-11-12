<?php

namespace App\Filament\Resources\ClientUsers\Pages;

use App\Filament\Resources\ClientUsers\ClientUserResource;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewClientUser extends ViewRecord
{
    protected static string $resource = ClientUserResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make(), RestoreAction::make()];
    }
}
