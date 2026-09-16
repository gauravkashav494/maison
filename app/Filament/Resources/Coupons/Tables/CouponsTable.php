<?php

namespace App\Filament\Resources\Coupons\Tables;

use App\Models\Coupon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class CouponsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->weight('bold')->copyable(),
                TextColumn::make('description')->limit(40)->color('gray'),
                TextColumn::make('type')->formatStateUsing(fn (Coupon $r) => $r->describe())->badge()->color('info'),
                TextColumn::make('min_subtotal')->label('Min. order')->formatStateUsing(fn (int $state) => $state ? money($state) : '—'),
                TextColumn::make('used_count')->label('Used')->formatStateUsing(fn (Coupon $r) => $r->used_count.($r->usage_limit ? " / {$r->usage_limit}" : ''))->alignRight(),
                TextColumn::make('ends_at')->label('Expires')->date('d M Y')->placeholder('Never'),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
