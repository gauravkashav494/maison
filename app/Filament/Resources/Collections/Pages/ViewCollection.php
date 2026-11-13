<?php

namespace App\Filament\Resources\Collections\Pages;

use App\Filament\Resources\Collections\CollectionResource;
use App\Filament\Support\StorefrontLink;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCollection extends ViewRecord
{
    protected static string $resource = CollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('storefront')
                ->label('View on store')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->hidden(fn () => $this->getRecord()->trashed() || ! $this->getRecord()->is_active)
                ->url(fn () => StorefrontLink::to($this->getRecord(), 'collections/'.$this->getRecord()->slug), shouldOpenInNewTab: true),
            RestoreAction::make(),
            EditAction::make(),
        ];
    }
}
