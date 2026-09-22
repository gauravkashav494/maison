<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Own role/active state is disabled in the form (and therefore not submitted): keep current values.
        if ($this->record->is(auth()->user())) {
            $data['role'] = $this->record->role;
            $data['is_active'] = true;
        }

        return UserForm::normalise($data);
    }
}
