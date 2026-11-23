<?php

namespace App\Filament\Resources\Menus\Pages;

use App\Filament\Resources\Menus\MenuResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class EditMenu extends EditRecord
{
    protected static string $resource = MenuResource::class;

    /** "Footer — Shop · In the footer of every page, as its own column" under the title. */
    public function getSubheading(): ?string
    {
        return MenuResource::placement($this->getRecord()->location);
    }

    /** The menu's name and location, its items, then "Where it shows": the store section holding it. */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
            $this->getRelationManagersContentComponent(),
            View::make('filament.menus.store-preview')
                ->visible(fn () => MenuResource::previewUrl($this->getRecord()->location) !== null)
                ->viewData(fn () => [
                    'url' => MenuResource::previewUrl($this->getRecord()->location),
                    'pageUrl' => MenuResource::previewUrl($this->getRecord()->location, fullPage: true),
                    'placement' => MenuResource::placement($this->getRecord()->location),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewOnStore')->label('View on storefront')->icon('heroicon-o-arrow-top-right-on-square')->color('primary')
                ->url(fn () => MenuResource::previewUrl($this->getRecord()->location, fullPage: true) ?? MenuResource::storefrontUrl(), shouldOpenInNewTab: true)
                ->visible(fn () => MenuResource::storefrontUrl() !== null),
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make()->label('Delete permanently'),
        ];
    }
}
