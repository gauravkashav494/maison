<?php

namespace App\Filament\Resources\Users\Tables;

use App\Admin\PlatformModules;
use App\Filament\Support\PasswordHintColumn;
use App\Filament\Support\RowActions;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->description(fn (User $r) => $r->email),
                TextColumn::make('is_super_admin')->label('Access')->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Super Admin' : 'Platform Staff')
                    ->color(fn (bool $state) => $state ? 'danger' : 'info')
                    ->sortable(),
                TextColumn::make('admin_modules')->label('Modules')->badge()->color('gray')
                    ->formatStateUsing(fn (?string $state) => PlatformModules::ALL[$state] ?? $state)
                    ->placeholder(fn (User $r) => $r->isSuperAdmin() ? 'All' : '—')
                    ->toggleable(),
                TextColumn::make('phone')->color('gray')->placeholder('—')->toggleable(),
                PasswordHintColumn::make(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('created_at')->label('Joined')->date('d M Y')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_super_admin')->label('Access')->trueLabel('Super Admins')->falseLabel('Platform staff'),
                TrashedFilter::make(),
            ])
            ->recordActions([RowActions::group([EditAction::make(), ...RowActions::trash()])])
            ->toolbarActions([BulkActionGroup::make([...RowActions::trashBulk()])]);
    }
}
