<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Clients\ClientProvisioner;
use App\Filament\Resources\Clients\ClientResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Retry (or top up) the client's database: missing tables are added, nothing is duplicated.
            Action::make('provision')
                ->label('Provision database')
                ->icon('heroicon-o-circle-stack')
                ->color('gray')
                ->visible(fn () => app(ClientProvisioner::class)->isNeeded())
                ->requiresConfirmation()
                ->modalDescription('Creates this client\'s database if it is missing and applies any missing tables. Existing content is kept.')
                ->schema([
                    // Only a database built from scratch is seeded, so the choice only exists then.
                    Toggle::make('sample_products')->label('Add sample products and services')->default(false)
                        ->visible(fn () => ! app(ClientProvisioner::class)->databaseExists($this->getRecord())),
                ])
                ->action(function (array $data) {
                    $result = app(ClientProvisioner::class)->provision($this->getRecord(), (bool) ($data['sample_products'] ?? false));

                    $result['ok']
                        ? Notification::make()->success()->title('Database ready')->body($result['message'])->send()
                        : Notification::make()->danger()->persistent()->title('Provisioning failed')->body(str($result['message'])->limit(300))->send();
                }),
        ];
    }
}
