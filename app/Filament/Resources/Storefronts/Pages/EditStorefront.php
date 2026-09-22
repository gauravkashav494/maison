<?php

namespace App\Filament\Resources\Storefronts\Pages;

use App\Filament\Resources\Storefronts\Schemas\StorefrontForm;
use App\Filament\Resources\Storefronts\StorefrontResource;
use Filament\Resources\Pages\EditRecord;

class EditStorefront extends EditRecord
{
    protected static string $resource = StorefrontResource::class;

    protected function afterSave(): void
    {
        StorefrontForm::syncOwners($this->record, (array) ($this->form->getRawState()['owner_ids'] ?? []));
    }
}
