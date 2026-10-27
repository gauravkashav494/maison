<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Clients\ClientDatabase;
use App\Filament\Resources\Clients\ClientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    public function getSubheading(): ?string
    {
        return app(ClientDatabase::class)->isolated()
            ? 'Every client has its own database. After creating one, run "php artisan clients:provision <slug>" to build it.'
            : 'All clients share one database; their rows are kept apart by client. Set CLIENT_ISOLATION=database for a database each.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
