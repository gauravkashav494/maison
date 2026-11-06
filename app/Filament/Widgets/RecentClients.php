<?php

namespace App\Filament\Widgets;

use App\Clients\ClientContext;
use App\Clients\ClientQuota;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use Filament\Widgets\Widget;

/** Newest clients and how much of their product allowance they use. */
class RecentClients extends Widget
{
    protected string $view = 'filament.widgets.recent-clients';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return app(ClientContext::class)->isSuperAdmin();
    }

    protected function getViewData(): array
    {
        return [
            'clients' => Client::with(['storefront', 'plan'])->latest()->limit(6)->get(),
            'indexUrl' => ClientResource::getUrl('index'),
            'editUrl' => fn (Client $client) => ClientResource::getUrl('edit', ['record' => $client]),
            'usage' => fn (Client $client) => ClientQuota::for($client)->productLabel(),
        ];
    }
}
