<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryDeleteActions;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Support\Pages\EditRecord;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ViewAction;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), CategoryDeleteActions::delete(), CategoryDeleteActions::restore(), ForceDeleteAction::make()->label('Delete permanently')];
    }
}
