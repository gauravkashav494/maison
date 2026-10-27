<?php

namespace App\Filament\Resources\ClientUsers\Pages;

use App\Filament\Resources\ClientUsers\ClientUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClientUsers extends ListRecords
{
    protected static string $resource = ClientUserResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
