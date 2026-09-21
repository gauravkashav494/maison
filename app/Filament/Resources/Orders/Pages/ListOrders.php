<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Setting;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /** Opening the list clears the "new orders" badge for this admin. */
    public function mount(): void
    {
        parent::mount();

        $seen = Setting::get('admin_seen', []);
        $seen['orders_'.auth()->id()] = now()->format('Y-m-d H:i:s');
        Setting::set('admin_seen', $seen);
    }

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
