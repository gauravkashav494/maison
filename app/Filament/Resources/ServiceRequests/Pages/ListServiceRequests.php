<?php

namespace App\Filament\Resources\ServiceRequests\Pages;

use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\ServiceRequest;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListServiceRequests extends ListRecords
{
    protected static string $resource = ServiceRequestResource::class;

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('All')];
        $tabs['new'] = Tab::make('New')->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'new'))->badge(fn () => ServiceRequest::where('status', 'new')->count() ?: null)->badgeColor('danger');
        foreach (ServiceRequest::TYPES as $key => $label) {
            $tabs[$key] = Tab::make($label)->modifyQueryUsing(fn (Builder $q) => $q->where('type', $key))->badge(fn () => ServiceRequest::where('type', $key)->count());
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
