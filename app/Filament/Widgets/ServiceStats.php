<?php

namespace App\Filament\Widgets;

use App\Admin\StoreContext;
use App\Clients\ClientAccess;
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

        return $context->template() !== null
            && (bool) $context->templateObject()?->supportsServices()
            && (ClientAccess::current('service_requests', 'view') || ClientAccess::current('services', 'view'));
    }

    protected function getViewData(): array
    {
        $stats = [];

        if (ClientAccess::current('service_requests', 'view')) {
            $monthStart = now()->startOfMonth();
            $new = ServiceRequest::where('status', 'new')->count();
            $unread = ClientAccess::current('messages', 'view') ? ContactMessage::where('is_read', false)->count() : null;

            $stats[] = ['label' => 'Bookings this month', 'value' => ServiceRequest::whereIn('type', ['booking', 'emergency'])->where('created_at', '>=', $monthStart)->count(), 'description' => ServiceRequest::whereIn('type', ['booking', 'emergency'])->count().' all time', 'icon' => 'calendar-days', 'color' => 'is-blue', 'url' => ServiceRequestResource::getUrl('index')];
            $stats[] = ['label' => 'New requests', 'value' => $new, 'description' => 'Awaiting a call back', 'icon' => 'phone-arrow-down-left', 'color' => 'is-orange', 'url' => ServiceRequestResource::getUrl('index', ['activeTab' => 'new'])];
            $stats[] = ['label' => 'Quote requests', 'value' => ServiceRequest::where('type', 'quote')->count(), 'description' => $unread === null ? 'All time' : $unread.' unread '.($unread === 1 ? 'message' : 'messages'), 'icon' => 'document-text', 'color' => 'is-purple', 'url' => ServiceRequestResource::getUrl('index', ['activeTab' => 'quote'])];
        }

        if (ClientAccess::current('services', 'view')) {
            $stats[] = ['label' => 'Services', 'value' => Service::where('is_active', true)->count(), 'description' => ServiceArea::where('is_active', true)->count().' service areas', 'icon' => 'wrench-screwdriver', 'color' => 'is-green', 'url' => ServiceResource::getUrl('index')];
        }

        return ['stats' => $stats];
    }
}
