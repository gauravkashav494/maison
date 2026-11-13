<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Category;
use App\Templates\TemplateManager;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Read-only overview of a category. Its products are listed below the page. */
class CategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 2])->schema([
                Section::make('Category')
                    ->icon('heroicon-o-squares-2x2')
                    ->columns(2)
                    ->schema([
                        ImageEntry::make('image_url')->hiddenLabel()->imageHeight(160)->columnSpanFull()
                            ->visible(fn (Category $r) => filled($r->image)),
                        TextEntry::make('name')->weight('bold')->size('lg')->columnSpanFull(),
                        TextEntry::make('tagline')->columnSpanFull()->placeholder('—'),
                        TextEntry::make('parent.name')->label('Parent')->badge()->color('gray')->placeholder('Top level')
                            ->url(fn (Category $r) => $r->parent ? CategoryResource::getUrl('view', ['record' => $r->parent]) : null),
                        TextEntry::make('children.name')->label('Sub-categories')->badge()->color('gray')->placeholder('None'),
                        TextEntry::make('description')->columnSpanFull()->placeholder('—'),
                        TextEntry::make('content')->label('Page content')->html()->columnSpanFull()
                            ->visible(fn (Category $r) => filled(strip_tags((string) $r->content))),
                    ]),
            ]),

            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 1])->schema([
                Section::make('Storefront')
                    ->icon('heroicon-o-building-storefront')
                    ->schema([
                        IconEntry::make('is_active')->label('Visible')->inlineLabel()->boolean(),
                        IconEntry::make('show_in_menu')->label('On homepage')->inlineLabel()->boolean(),
                        TextEntry::make('products_total')->label('Products')->inlineLabel()
                            ->state(fn (Category $r) => $r->products()->count()),
                        TextEntry::make('products_visible')->label('Visible products')->inlineLabel()
                            ->state(fn (Category $r) => $r->products()->where('is_active', true)->count()),
                        TextEntry::make('template')->label('Visible in')->inlineLabel()
                            ->formatStateUsing(fn ($state) => app(TemplateManager::class)->options()[$state] ?? $state)
                            ->placeholder('All templates'),
                        TextEntry::make('slug')->label('URL')->inlineLabel()->prefix('/shop/')->copyable()->color('gray'),
                    ]),

                Section::make('Record')
                    ->icon('heroicon-o-clock')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('sort_order')->label('Sort order')->inlineLabel(),
                        TextEntry::make('created_at')->label('Created')->inlineLabel()->dateTime('d M Y, H:i'),
                        TextEntry::make('updated_at')->label('Updated')->inlineLabel()->since(),
                        TextEntry::make('deleted_at')->label('Deleted')->inlineLabel()->dateTime('d M Y, H:i')->color('danger')
                            ->visible(fn (Category $r) => $r->trashed()),
                        TextEntry::make('meta_title')->label('SEO title')->placeholder('Uses the category name'),
                        TextEntry::make('meta_description')->label('SEO description')->placeholder('Uses the description'),
                    ]),
            ]),
        ]);
    }
}
