<?php

namespace App\Filament\Resources\Brands\Schemas;

use App\Admin\StoreContext;
use App\Models\Brand;
use App\Support\Media;
use App\Templates\TemplateManager;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Read-only overview of a brand. Its products are listed below the page. */
class BrandInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 2])->schema([
                Section::make('Brand')
                    ->icon('heroicon-o-check-badge')
                    ->schema([
                        ImageEntry::make('logo_image')->label('Logo')->imageHeight(80)
                            ->state(fn (Brand $r) => Media::url($r->logo))
                            ->visible(fn (Brand $r) => filled($r->logo)),
                        TextEntry::make('name')->weight('bold')->size('lg'),
                        TextEntry::make('description')->placeholder('—'),
                    ]),
            ]),

            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 1])->schema([
                Section::make('Details')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        TextEntry::make('products_total')->label('Products')->inlineLabel()
                            ->state(fn (Brand $r) => $r->products()->count()),
                        TextEntry::make('slug')->inlineLabel()->copyable()->color('gray'),
                        TextEntry::make('template')->label('Visible in')->inlineLabel()
                            ->hidden(fn () => app(StoreContext::class)->isLocked())
                            ->formatStateUsing(fn ($state) => app(TemplateManager::class)->options()[$state] ?? $state)
                            ->placeholder('All templates'),
                        TextEntry::make('updated_at')->label('Updated')->inlineLabel()->since(),
                        TextEntry::make('deleted_at')->label('Deleted')->inlineLabel()->dateTime('d M Y, H:i')->color('danger')
                            ->visible(fn (Brand $r) => $r->trashed()),
                    ]),
            ]),
        ]);
    }
}
