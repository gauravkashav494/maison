<?php

namespace App\Filament\Widgets;

use App\Clients\ClientContext;
use App\Clients\ClientDatabase;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Filament\Resources\Storefronts\StorefrontResource;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\ContactMessage;
use App\Models\Customer;
use App\Models\Page;
use App\Models\Product;
use App\Models\ServiceRequest;
use App\Models\Storefront;
use App\Templates\TemplateManager;
use Filament\Widgets\Widget;

/** Platform-wide numbers for the operator: clients, stores, catalogue and demand. */
class PlatformStats extends Widget
{
    protected string $view = 'filament.widgets.store-stats';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return app(ClientContext::class)->isSuperAdmin();
    }

    /** Count across every client, whether they share a database or each have their own. */
    private function acrossClients(callable $count): int
    {
        $databases = app(ClientDatabase::class);
        if (! $databases->isolated()) {
            return (int) $count();
        }

        return (int) $databases->clients()->sum(fn (Client $client) => $databases->forClient($client, fn () => $count()));
    }

    protected function getViewData(): array
    {
        $clients = Client::count();
        $activeClients = Client::where('is_active', true)->count();
        $staff = ClientUser::count();
        $leads = $this->acrossClients(fn () => ContactMessage::withoutGlobalScopes()->count() + ServiceRequest::withoutGlobalScopes()->where('type', 'quote')->count());
        $bookings = $this->acrossClients(fn () => ServiceRequest::withoutGlobalScopes()->whereIn('type', ['booking', 'emergency'])->count());
        $products = $this->acrossClients(fn () => Product::withoutGlobalScopes()->count());
        $categories = $this->acrossClients(fn () => Category::withoutGlobalScopes()->count());
        $pages = $this->acrossClients(fn () => Page::withoutGlobalScopes()->count());

        return ['stats' => [
            ['label' => 'Clients', 'value' => $clients, 'description' => $activeClients.' active · '.$staff.' staff '.($staff === 1 ? 'account' : 'accounts'), 'icon' => 'briefcase', 'color' => 'is-purple', 'url' => ClientResource::getUrl('index')],
            ['label' => 'Stores', 'value' => Storefront::count(), 'description' => app(TemplateManager::class)->all()->count().' templates · '.Storefront::active()->count().' active', 'icon' => 'building-storefront', 'color' => 'is-blue', 'url' => StorefrontResource::getUrl('index')],
            ['label' => 'Catalogue', 'value' => $products, 'description' => $categories.' categories · '.$pages.' pages', 'icon' => 'squares-2x2', 'color' => 'is-green', 'url' => ProductResource::getUrl('index'), 'description_url' => PageResource::getUrl('index')],
            ['label' => 'Customers & demand', 'value' => Customer::count(), 'description' => $leads.' leads · '.$bookings.' bookings', 'icon' => 'inbox-arrow-down', 'color' => 'is-orange', 'url' => ServiceRequestResource::getUrl('index'), 'description_url' => ContactMessageResource::getUrl('index')],
        ]];
    }
}
