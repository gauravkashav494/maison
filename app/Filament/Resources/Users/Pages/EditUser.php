<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(), RestoreAction::make(), ForceDeleteAction::make()->label('Delete permanently'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Your own active state and Super Admin flag are disabled in the form (and therefore not
        // submitted): you can neither lock yourself out nor drop your own Super Admin access.
        if ($this->record->is(filament()->auth()->user())) {
            $data['is_active'] = true;
            $data['is_super_admin'] = $this->record->isSuperAdmin();
        }

        return UserForm::normalise($data);
    }
}
