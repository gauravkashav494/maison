<?php

namespace App\Filament\Resources\Taxes\Pages;

use App\Filament\Resources\Taxes\TaxResource;
use App\Filament\Support\Pages\EditRecord;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;

class EditTax extends EditRecord
{
    protected static string $resource = TaxResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make(), RestoreAction::make(), ForceDeleteAction::make()->label('Delete permanently')];
    }
}
