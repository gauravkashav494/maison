<?php

namespace App\Filament\Resources\ServiceAreas\Pages;

use App\Filament\Resources\ServiceAreas\ServiceAreaResource;
use App\Filament\Support\Pages\EditRecord;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;

class EditServiceArea extends EditRecord
{
    protected static string $resource = ServiceAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(), RestoreAction::make(), ForceDeleteAction::make()->label('Delete permanently'),
        ];
    }
}
