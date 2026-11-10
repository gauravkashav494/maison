<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Clients\ClientProvisioner;
use App\Filament\Resources\Clients\ClientResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    /** A new client gets its own database straight away, built from the store it runs. */
    protected function afterCreate(): void
    {
        $provisioner = app(ClientProvisioner::class);
        if (! $provisioner->isNeeded()) {
            return;
        }

        $result = $provisioner->provision($this->getRecord(), (bool) ($this->data['sample_products'] ?? false));

        $result['ok']
            ? Notification::make()->success()->title('Client database created')->body($result['message'])->send()
            : Notification::make()->danger()->persistent()
                ->title('Client saved, but its database could not be created')
                ->body('Use "Provision database" on the client to retry. '.str($result['message'])->limit(300))
                ->send();
    }
}
