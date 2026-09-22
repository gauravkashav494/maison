<?php

namespace App\Filament\Widgets;

use App\Admin\StoreContext;
use App\Templates\TemplateManager;
use Filament\Widgets\Widget;

/** Shows the public address of the store the session manages, ready to share. */
class StoreLink extends Widget
{
    protected string $view = 'filament.widgets.store-link';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected static ?int $sort = -1;

    public static function canView(): bool
    {
        return app(StoreContext::class)->storefront() !== null;
    }

    protected function getViewData(): array
    {
        $store = app(StoreContext::class)->storefront();

        return [
            'store' => $store,
            'url' => $store->publicUrl(),
            'onMainDomain' => app(TemplateManager::class)->activeId() === $store->template,
            'mainUrl' => rtrim(config('app.url'), '/'),
        ];
    }
}
