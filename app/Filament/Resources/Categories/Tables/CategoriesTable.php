<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Admin\StoreContext;
use App\Filament\Support\RowActions;
use App\Models\Category;
use App\Support\Media;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('image')->label('')->state(fn (Category $r) => Media::url($r->image))->square()->imageSize(44),
                TextColumn::make('name')->searchable()->sortable()->description(fn (Category $r) => $r->tagline),
                // The parent with the categories above it ("Men › T-shirts & Polos"), so two "Shirts"
                // are told apart.
                TextColumn::make('parent.name')->label('Parent')->badge()->color('gray')->placeholder('Top level')
                    ->formatStateUsing(fn (Category $r) => $r->loadMissing(Category::PATH)->parent?->pathName()),
                // The categories directly under this one (Shirts, Pants, Suits under Men), in their order.
                TextColumn::make('children.name')->label('Child categories')->badge()->color('info')
                    ->limitList(4)->expandableLimitedList()->placeholder('None'),
                TextColumn::make('products_count')->counts('products')->label('Products')->alignRight(),
                ToggleColumn::make('is_active')->label('Visible on storefront'),
                // Not in a Bulk Order store, which lists every visible category; greyed out on a Bulk
                // category when a super admin views every store.
                ToggleColumn::make('show_in_menu')->label('Menu & homepage')
                    ->hidden(fn () => ! Category::featurableIn(app(StoreContext::class)->template()))
                    ->disabled(fn (Category $r) => ! Category::featurableIn($r->template)),
            ])
            ->filters([
                TernaryFilter::make('parent_id')->label('Level')->nullable()->trueLabel('Child categories')->falseLabel('Top level')->queries(
                    true: fn ($q) => $q->whereNotNull('parent_id'),
                    false: fn ($q) => $q->whereNull('parent_id'),
                ),
                TrashedFilter::make(),
            ])
            ->recordActions([RowActions::group([ViewAction::make(), EditAction::make(), ...RowActions::trash()])])
            ->toolbarActions([BulkActionGroup::make(RowActions::trashBulk())]);
    }
}
