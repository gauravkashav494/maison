<?php

namespace App\Filament\Widgets;

use App\Admin\StoreContext;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\ServiceAreas\ServiceAreaResource;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Filament\Resources\Services\ServiceResource;
use App\Models\ContactMessage;
use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\ServiceRequest;
use Filament\Widgets\Widget;

/** Dashboard numbers for a service-business store (bookings, quotes, messages, services). */
class ServiceStats extends Widget
{
    protected string $view = 'filament.widgets.store-stats';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        $context = app(StoreContext::class);

        return $context->template() !== null && (bool) $context->templateObject()?->supportsServices();
    }

    protected function getViewData(): array
    {
        $monthStart = now()->startOfMonth();
        $new = ServiceRequest::where('status', 'new')->count();
        $unread = ContactMessage::where('is_read', false)->count();

        return ['stats' => [
            ['label' => 'Bookings this month', 'value' => ServiceRequest::whereIn('type', ['booking', 'emergency'])->where('created_at', '>=', $monthStart)->count(), 'description' => ServiceRequest::whereIn('type', ['booking', 'emergency'])->count().' all time', 'icon' => 'calendar-days', 'color' => 'is-blue', 'url' => ServiceRequestResource::getUrl('index')],
            ['label' => 'New requests', 'value' => $new, 'description' => 'Awaiting a call back', 'icon' => 'phone-arrow-down-left', 'color' => 'is-orange', 'url' => ServiceRequestResource::getUrl('index', ['activeTab' => 'new'])],
            ['label' => 'Quote requests', 'value' => ServiceRequest::where('type', 'quote')->count(), 'description' => $unread.' unread '.($unread === 1 ? 'message' : 'messages'), 'icon' => 'document-text', 'color' => 'is-purple', 'url' => ServiceRequestResource::getUrl('index', ['activeTab' => 'quote']), 'description_url' => ContactMessageResource::getUrl('index')],
            ['label' => 'Services', 'value' => Service::where('is_active', true)->count(), 'description' => ServiceArea::where('is_active', true)->count().' service areas', 'icon' => 'wrench-screwdriver', 'color' => 'is-green', 'url' => ServiceResource::getUrl('index'), 'description_url' => ServiceAreaResource::getUrl('index')],
        ]];
    }
}
