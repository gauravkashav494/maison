<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\Storefront;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->description(fn (User $r) => $r->email),
                TextColumn::make('role')->badge()
                    ->formatStateUsing(fn (?string $state) => User::ROLES[$state] ?? 'Customer')
                    ->color(fn (?string $state) => match ($state) { User::ROLE_SUPER_ADMIN => 'danger', User::ROLE_STORE_OWNER => 'info', default => 'gray' })
                    ->sortable(),
                TextColumn::make('storefront.name')->label('Store')->placeholder('—')->sortable(),
                TextColumn::make('phone')->color('gray')->placeholder('—')->toggleable(),
                TextColumn::make('orders_count')->counts('orders')->label('Orders')->alignRight()->toggleable()
                    ->visible(fn () => ! app(\App\Stores\TenantManager::class)->isolated()), // a store database cannot be joined from here
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('created_at')->label('Joined')->date('d M Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')->options(User::ROLES),
                SelectFilter::make('storefront_id')->label('Store')->options(fn () => Storefront::orderBy('name')->pluck('name', 'id')),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
