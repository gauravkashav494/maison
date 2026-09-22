<?php

namespace App\Filament\Resources\Storefronts\Pages;

use App\Filament\Resources\Storefronts\Schemas\StorefrontForm;
use App\Filament\Resources\Storefronts\StorefrontResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStorefront extends CreateRecord
{
    protected static string $resource = StorefrontResource::class;

    protected function afterCreate(): void
    {
        StorefrontForm::syncOwners($this->record, (array) ($this->form->getRawState()['owner_ids'] ?? []));
    }
}
