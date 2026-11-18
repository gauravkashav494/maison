<?php

namespace App\Filament\Resources\ActivityLog\Pages;

use App\Filament\Resources\ActivityLog\ActivityLogResource;
use App\Models\ClientUser;
use App\Models\PlatformActivity;
use Filament\Resources\Pages\ListRecords;

class ListActivityLog extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    public function getSubheading(): ?string
    {
        $user = filament()->auth()->user();
        $months = PlatformActivity::KEEP_MONTHS;

        return $user instanceof ClientUser && $user->isClientAdmin()
            ? "Everything your team did in this panel over the last {$months} months."
            : "Everything you did in this panel over the last {$months} months.";
    }
}
