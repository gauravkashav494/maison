<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Clients\ClientProvisioner;
use App\Clients\ClientSetup;
use App\Filament\Resources\Clients\ClientResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('visit')
                ->label('Visit store')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->hidden(fn () => $this->getRecord()->trashed())
                ->url(fn () => $this->getRecord()->publicUrl(), shouldOpenInNewTab: true),
            $this->repairAction(),
            RestoreAction::make(),
            EditAction::make(),
        ];
    }

    /**
     * Only when something is wrong with the client's database: builds what is missing in the
     * background (existing content is kept), and the page shows the setup banner meanwhile.
     */
    protected function repairAction(): Action
    {
        $reason = fn () => $this->getRecord()->trashed() || ClientSetup::isRunning($this->getRecord())
            ? null
            : app(ClientProvisioner::class)->repairReason($this->getRecord());

        return Action::make('repairDatabase')
            ->label(fn () => $reason() === 'missing' ? 'Finish setup' : 'Repair database')
            ->icon('heroicon-o-wrench-screwdriver')
            ->color('warning')
            ->visible(fn () => $reason() !== null)
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-wrench-screwdriver')
            ->modalHeading(fn () => $reason() === 'missing' ? 'Finish setting up this store?' : 'Repair this store\'s database?')
            ->modalDescription(fn () => match ($reason()) {
                'missing' => 'Its database was never built. This creates it and fills it with the store\'s pages, menus and settings.',
                'outdated' => 'New tables were added to the platform since this store was built. This adds them; existing content is kept.',
                default => 'The last setup did not finish. This creates whatever is missing; existing content is kept.',
            })
            ->modalSubmitActionLabel(fn () => $reason() === 'missing' ? 'Finish setup' : 'Repair')
            ->schema([
                // Only a database built from scratch is seeded, so the choice only exists then.
                Toggle::make('sample_products')->label('Add sample products and services')->default(false)
                    ->visible(fn () => ! app(ClientProvisioner::class)->databaseExists($this->getRecord())),
            ])
            ->action(function (array $data) {
                app(ClientSetup::class)->startInBackground($this->getRecord(), (bool) ($data['sample_products'] ?? false));

                Notification::make()->success()->title('Repair started')
                    ->body('This page shows when the store is ready.')->send();
            });
    }
}
