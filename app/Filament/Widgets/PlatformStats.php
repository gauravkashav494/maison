<?php

namespace App\Filament\Widgets;

use App\Admin\StoreContext;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Filament\Resources\Storefronts\StorefrontResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Page;
use App\Models\Product;
use App\Models\ServiceRequest;
use App\Models\Storefront;
use App\Models\User;
use App\Templates\TemplateManager;
use Filament\Widgets\Widget;

/** Platform-wide numbers for super admins viewing all stores. */
class PlatformStats extends Widget
{
    protected string $view = 'filament.widgets.store-stats';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        $context = app(StoreContext::class);

        return $context->isSuperAdmin() && $context->template() === null;
    }

    protected function getViewData(): array
    {
        $stores = Storefront::count();
        $owners = User::where('role', User::ROLE_STORE_OWNER)->count();
        $leads = ContactMessage::count() + ServiceRequest::where('type', 'quote')->count();
        $bookings = ServiceRequest::whereIn('type', ['booking', 'emergency'])->count();

        return ['stats' => [
            ['label' => 'Stores', 'value' => $stores, 'description' => app(TemplateManager::class)->all()->count().' templates · '.Storefront::active()->count().' active', 'icon' => 'building-storefront', 'color' => 'is-blue', 'url' => StorefrontResource::getUrl('index')],
            ['label' => 'Users', 'value' => User::count(), 'description' => $owners.' store '.($owners === 1 ? 'owner' : 'owners').' · '.User::whereNull('role')->count().' customers', 'icon' => 'users', 'color' => 'is-purple', 'url' => UserResource::getUrl('index')],
            ['label' => 'Catalogue', 'value' => Product::count(), 'description' => Category::count().' categories · '.Page::count().' pages', 'icon' => 'squares-2x2', 'color' => 'is-green', 'url' => ProductResource::getUrl('index'), 'description_url' => PageResource::getUrl('index')],
            ['label' => 'Leads & bookings', 'value' => $leads + $bookings, 'description' => $leads.' leads · '.$bookings.' bookings', 'icon' => 'inbox-arrow-down', 'color' => 'is-orange', 'url' => ServiceRequestResource::getUrl('index'), 'description_url' => ContactMessageResource::getUrl('index')],
        ]];
    }
}
