<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getTabs(): array
    {
        return [
            'staff' => Tab::make('Staff')->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('role'))->badge(fn () => User::whereNotNull('role')->count()),
            'owners' => Tab::make('Store owners')->modifyQueryUsing(fn (Builder $query) => $query->where('role', User::ROLE_STORE_OWNER))->badge(fn () => User::where('role', User::ROLE_STORE_OWNER)->count()),
            'customers' => Tab::make('Customers')->modifyQueryUsing(fn (Builder $query) => $query->whereNull('role'))->badge(fn () => User::whereNull('role')->count()),
            'all' => Tab::make('All'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'staff';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
