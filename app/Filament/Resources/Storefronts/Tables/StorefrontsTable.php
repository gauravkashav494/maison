<?php

namespace App\Filament\Resources\Storefronts\Tables;

use App\Models\Storefront;
use App\Templates\TemplateManager;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StorefrontsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('bold'),
                TextColumn::make('template')->label('Template')->formatStateUsing(fn (Storefront $r) => $r->template_name)->badge()->color('gray')
                    ->description(fn (Storefront $r) => $r->supportsServices() ? 'Service business' : 'Catalogue store'),
                TextColumn::make('public_url')->label('Address')->state(fn (Storefront $r) => $r->publicUrl())->url(fn (Storefront $r) => $r->publicUrl(), shouldOpenInNewTab: true)->color('info')->copyable()->icon('heroicon-o-arrow-top-right-on-square'),
                TextColumn::make('owners.name')->label('Owners')->listWithLineBreaks()->limitList(3)->placeholder('No owner yet'),
                IconColumn::make('is_live')->label('Main domain')->boolean()->state(fn (Storefront $r) => app(TemplateManager::class)->activeId() === $r->template)->tooltip('Also served on the main domain (Appearance → Templates)'),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('created_at')->label('Created')->date('d M Y')->sortable(),
            ])
            ->recordActions([
                Action::make('manage')->label('Manage')->icon('heroicon-o-arrow-right-circle')->url(fn (Storefront $r) => url('/admin?switch_store='.$r->slug))->tooltip('Switch the admin to this store'),
                Action::make('toggle')->label(fn (Storefront $r) => $r->is_active ? 'Deactivate' : 'Activate')->icon(fn (Storefront $r) => $r->is_active ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')->color(fn (Storefront $r) => $r->is_active ? 'gray' : 'success')->requiresConfirmation()->action(fn (Storefront $r) => $r->update(['is_active' => ! $r->is_active])),
                EditAction::make(),
            ]);
    }
}
