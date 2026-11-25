<?php

namespace App\Filament\RelationManagers;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Support\RowActions;
use App\Models\Product;
use App\Services\Inventory;
use App\Support\Media;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The products in a category or a collection, on that record's detail page. Read-only: ⋮ → View
 * opens the product, and products are added from the product form (category) or the
 * collection form.
 */
class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    protected static ?string $title = 'Products';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return is_subclass_of($pageClass, ViewRecord::class) && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            // Newest first. reorder() drops the collection's built-in pivot order, which would
            // otherwise sort ahead of this; the column is qualified because of the pivot join.
            ->modifyQueryUsing(fn (Builder $query) => $query->reorder())
            ->defaultSort('products.created_at', 'desc')
            ->columns([
                ImageColumn::make('image')->label('')->state(fn (Product $r) => Media::url($r->images[0] ?? null))->square()->imageSize(40),
                TextColumn::make('name')->searchable()->description(fn (Product $r) => $r->sku),
                TextColumn::make('price')->formatStateUsing(fn (float $state) => money($state))->sortable()
                    ->description(fn (Product $r) => $r->compare_at_price ? 'was '.money($r->compare_at_price) : null),
                TextColumn::make('stock')->sortable()->alignRight()
                    ->color(fn (Product $r) => $r->stock <= 0 ? 'danger' : ($r->stock <= Inventory::lowStockLevel() ? 'warning' : null)),
                IconColumn::make('is_active')->label('Visible on storefront')->boolean(),
            ])
            ->recordActions([RowActions::group([
                Action::make('view')->label('View')->icon('heroicon-m-eye')
                    ->url(fn (Product $r) => ProductResource::getUrl('view', ['record' => $r]))
                    ->visible(fn (Product $r) => ProductResource::canView($r)),
            ])])
            ->emptyStateHeading('No products yet');
    }
}
