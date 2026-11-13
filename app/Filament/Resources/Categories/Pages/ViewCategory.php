<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Support\StorefrontLink;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCategory extends ViewRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('storefront')
                ->label('View on store')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->hidden(fn () => $this->getRecord()->trashed() || ! $this->getRecord()->is_active)
                ->url(fn () => StorefrontLink::to($this->getRecord(), 'shop/'.$this->getRecord()->slug), shouldOpenInNewTab: true),
            RestoreAction::make(),
            EditAction::make(),
        ];
    }
}
