<?php

namespace App\Filament\Widgets;

use App\Clients\ClientContext;
use App\Templates\TemplateManager;
use Filament\Widgets\Widget;

/** The public address of the client the session works in, ready to share with its customers. */
class StoreLink extends Widget
{
    protected string $view = 'filament.widgets.store-link';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected static ?int $sort = -1;

    public static function canView(): bool
    {
        return app(ClientContext::class)->client() !== null;
    }

    protected function getViewData(): array
    {
        $client = app(ClientContext::class)->client();

        return [
            'store' => $client,
            'url' => $client->publicUrl(),
            'onMainDomain' => app(TemplateManager::class)->activeId() === $client->templateId(),
            'mainUrl' => rtrim(config('app.url'), '/'),
        ];
    }
}
