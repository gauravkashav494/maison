<?php

namespace App\Filament\Resources\Collections\Schemas;

use App\Models\Collection;
use App\Support\Media;
use App\Templates\TemplateManager;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Read-only overview of a collection. Its products, in display order, are listed below the page. */
class CollectionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 2])->schema([
                Section::make('Collection')
                    ->icon('heroicon-o-sparkles')
                    ->columns(2)
                    ->schema([
                        ImageEntry::make('card_image')->label('Card image')->imageHeight(160)
                            ->state(fn (Collection $r) => Media::url($r->image))
                            ->visible(fn (Collection $r) => filled($r->image)),
                        ImageEntry::make('banner_image')->label('Banner image')->imageHeight(160)
                            ->state(fn (Collection $r) => Media::url($r->hero_image))
                            ->visible(fn (Collection $r) => filled($r->hero_image)),
                        TextEntry::make('name')->weight('bold')->size('lg')->columnSpanFull(),
                        TextEntry::make('season')->placeholder('—'),
                        TextEntry::make('description')->columnSpanFull()->placeholder('—'),
                        TextEntry::make('body')->label('Page content')->html()->columnSpanFull()
                            ->visible(fn (Collection $r) => filled(strip_tags((string) $r->body))),
                    ]),
            ]),

            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 1])->schema([
                Section::make('Storefront')
                    ->icon('heroicon-o-building-storefront')
                    ->schema([
                        IconEntry::make('is_active')->label('Visible')->inlineLabel()->boolean(),
                        IconEntry::make('is_featured')->label('Featured')->inlineLabel()->boolean(),
                        TextEntry::make('products_total')->label('Products')->inlineLabel()
                            ->state(fn (Collection $r) => $r->products()->count()),
                        TextEntry::make('template')->label('Visible in')->inlineLabel()
                            ->formatStateUsing(fn ($state) => app(TemplateManager::class)->options()[$state] ?? $state)
                            ->placeholder('All templates'),
                        TextEntry::make('slug')->label('URL')->inlineLabel()->prefix('/collections/')->copyable()->color('gray'),
                    ]),

                Section::make('Record')
                    ->icon('heroicon-o-clock')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('sort_order')->label('Sort order')->inlineLabel(),
                        TextEntry::make('created_at')->label('Created')->inlineLabel()->dateTime('d M Y, H:i'),
                        TextEntry::make('updated_at')->label('Updated')->inlineLabel()->since(),
                        TextEntry::make('deleted_at')->label('Deleted')->inlineLabel()->dateTime('d M Y, H:i')->color('danger')
                            ->visible(fn (Collection $r) => $r->trashed()),
                        TextEntry::make('meta_title')->label('SEO title')->placeholder('Uses the collection name'),
                        TextEntry::make('meta_description')->label('SEO description')->placeholder('Uses the description'),
                    ]),
            ]),
        ]);
    }
}
