<?php

namespace App\Filament\Resources\Products\Tables;

use App\Clients\ClientAccess;
use App\Clients\StoreFeatures;
use App\Filament\Resources\Products\ProductRestoreActions;
use App\Filament\Support\CategoryFilter;
use App\Filament\Support\RowActions;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Support\Media;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;

class ProductsTable
{
    /** Filter option for products that belong to no collection. */
    private const NO_COLLECTION = 'none';

    public static function configure(Table $table): Table
    {
        return $table
            // The categories column shows each with its parents ("Men › Shirts").
            ->modifyQueryUsing(fn (Builder $query) => $query->with('categories.'.Category::PATH))
            ->defaultSort('created_at', 'desc')
            ->reorderable('sort_order')
            // Exception to the shared filter bar (AppServiceProvider::registerTableFilterLayout(),
            // up to 6 across): four across on a wide screen, so its eight field slots make two
            // even rows. Product + Variant still takes two of them.
            ->filtersFormColumns(['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 4])
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->state(fn (Product $record) => Media::url($record->images[0] ?? null))
                    ->square()
                    ->imageSize(48),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    // With a variant picked in the Product filter: that variant and its SKU.
                    ->description(fn (Product $record, $livewire) => ($variant = self::filteredVariant($record, $livewire))
                        ? 'Variant: '.$variant['label'].($variant['sku'] ? ' · '.$variant['sku'] : '')
                        : $record->sku),
                // Every category the product is listed under; sorting follows the main category.
                // Shown with the categories above them ("Men › Shirts").
                TextColumn::make('categories.name')->label('Categories')->badge()->color('gray')
                    ->state(fn (Product $record) => $record->categories->map(fn (Category $c) => $c->pathName())->all())
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy(
                        Category::select('name')->whereColumn('categories.id', 'products.category_id'), $direction,
                    )),
                // Off by default, switch it on from the column menu.
                TextColumn::make('collections.name')->label('Collection')->badge()->color('gray')
                    ->visible(fn () => StoreFeatures::enabled('collections'))
                    ->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('price')
                    ->formatStateUsing(fn (float $state) => money($state))
                    ->sortable()
                    ->description(fn (Product $record) => $record->compare_at_price ? 'was '.money($record->compare_at_price) : null),
                // The variant's own stock when one is picked in the Product filter, else the product's total.
                TextColumn::make('stock')->sortable()->alignRight()
                    ->state(fn (Product $record, $livewire) => self::filteredVariant($record, $livewire)['stock'] ?? $record->stock),
                // Switched right here, like Visible: staff mark new arrivals and best sellers often.
                // Greyed out for a role that may only view products (AppServiceProvider).
                // Each in its own colour when on, so the two read apart at a glance (Mark menu matches).
                ToggleColumn::make('is_new')->label('New')->onColor('info')->toggleable(),
                ToggleColumn::make('is_best_seller')->label('Best seller')->onColor('warning')->toggleable(),
                ToggleColumn::make('is_active')->label('Visible on storefront'),
                TextColumn::make('updated_at')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Visible on storefront'),
                // Any category the product is listed under, not only its main one.
                // Also finds the products in the categories below the one picked.
                CategoryFilter::make(),
                self::productFilter(),
                self::collectionFilter(),
                TernaryFilter::make('is_new')->label('New arrival'),
                TernaryFilter::make('is_best_seller')->label('Best seller'),
                TrashedFilter::make(),
            ])
            ->recordActions([RowActions::group([ViewAction::make(), EditAction::make(), ...RowActions::trash(restore: ProductRestoreActions::restore())])])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::markBulk('markNew', 'Mark as new', 'is_new', true, 'heroicon-o-sparkles')->color('info'),
                    self::markBulk('unmarkNew', 'Remove from new', 'is_new', false, 'heroicon-o-minus-circle'),
                    self::markBulk('markBestSeller', 'Mark as best seller', 'is_best_seller', true, 'heroicon-o-fire')->color('warning'),
                    self::markBulk('unmarkBestSeller', 'Remove from best sellers', 'is_best_seller', false, 'heroicon-o-minus-circle'),
                    self::markBulk('showOnStorefront', 'Show on storefront', 'is_active', true, 'heroicon-o-eye'),
                    // Takes them off the shop for customers, so it asks first.
                    self::markBulk('hideFromStorefront', 'Hide from storefront', 'is_active', false, 'heroicon-o-eye-slash')
                        ->requiresConfirmation()
                        ->modalHeading('Hide the selected products from the storefront?')
                        ->modalDescription('Customers will no longer see or be able to buy them. You can show them again at any time.')
                        ->modalSubmitActionLabel('Hide'),
                ])->label('Mark')->icon('heroicon-o-tag'),
                BulkActionGroup::make(RowActions::trashBulk(restore: ProductRestoreActions::restoreBulk())),
            ]);
    }

    /**
     * Sets New or Best seller on the chosen products. Each product is saved normally, so the
     * storefront and the activity log see it; the BulkAction hook (AppServiceProvider) writes
     * one line for the lot. Products already set that way are left alone.
     */
    private static function markBulk(string $name, string $label, string $column, bool $on, string $icon): BulkAction
    {
        return BulkAction::make($name)
            ->label($label)
            ->icon($icon)
            ->visible(fn () => ClientAccess::current('products', 'update'))
            ->authorizeIndividualRecords('update')
            ->deselectRecordsAfterCompletion()
            ->action(function (EloquentCollection $records) use ($column, $on, $label) {
                $changed = $records->filter(fn (Product $product) => (bool) $product->{$column} !== $on);
                $changed->each(fn (Product $product) => $product->update([$column => $on]));

                $count = $changed->count();
                Notification::make()->success()
                    ->title($count === 0 ? 'Nothing to change' : $label.': '.$count.' '.Str::plural('product', $count))
                    ->body($count < $records->count() ? ($records->count() - $count).' already '.($on ? 'had' : 'did not have').' it.' : null)
                    ->send();
            });
    }

    /**
     * One product, and for a product sold by variant one of its variants, as on Stock movements.
     * The product narrows the list to its row; the variant shows that variant's stock and SKU in
     * it (filteredVariant()).
     */
    private static function productFilter(): Filter
    {
        return Filter::make('product')
            ->schema([
                // Found by typing a name or SKU, not every product loaded into the page.
                Select::make('value')->label('Product')->searchable()->native(false)
                    ->getSearchResultsUsing(fn (string $search) => Product::query()
                        ->where(fn (Builder $q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"))
                        ->orderBy('name')->limit(50)->pluck('name', 'id')->all())
                    ->getOptionLabelUsing(fn ($value) => Product::withTrashed()->find($value)?->name)
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('variant', null)),
                Select::make('variant')->label('Variant')->native(false)
                    ->placeholder('All variants')
                    ->options(fn (Get $get) => self::variantOptions($get('value')))
                    // Always shown, so the bar keeps the same cells; usable once a product sold by variant is picked.
                    ->disabled(fn (Get $get) => self::variantOptions($get('value')) === []),
            ])
            ->query(fn (Builder $query, array $data) => $query->when(
                filled($data['value'] ?? null),
                fn (Builder $q) => $q->whereKey($data['value']),
            ))
            ->indicateUsing(function (array $data): array {
                if (blank($data['value'] ?? null)) {
                    return [];
                }
                $name = Product::withTrashed()->find($data['value'])?->name ?? 'Product #'.$data['value'];

                return [Indicator::make('Product: '.$name.(filled($data['variant'] ?? null) ? ' ('.$data['variant'].')' : ''))];
            });
    }

    /** @return array<string, string> a product's variants by label ("M / Red"); empty when it has none */
    private static function variantOptions(mixed $productId): array
    {
        $product = filled($productId) ? Product::withTrashed()->find($productId) : null;
        $labels = $product?->sellsVariants() ? array_column($product->variantList(), 'label') : [];

        return array_combine($labels, $labels) ?: [];
    }

    /**
     * The variant picked in the Product filter, when $record is that product.
     *
     * @return array{label: string, sku: ?string, stock: int}|null
     */
    private static function filteredVariant(Product $record, mixed $livewire): ?array
    {
        $filter = (array) ($livewire->tableFilters['product'] ?? []);
        if (blank($filter['variant'] ?? null) || (int) ($filter['value'] ?? 0) !== (int) $record->getKey()) {
            return null;
        }

        return collect($record->variantList())->firstWhere('label', $filter['variant']);
    }

    /** Products in any of the chosen collections, or in none at all (not merchandised anywhere). Also used by SEO → Search listings. */
    public static function collectionFilter(): SelectFilter
    {
        return SelectFilter::make('collections')
            ->label('Collection')
            ->visible(fn () => StoreFeatures::enabled('collections'))
            ->multiple()
            ->native(false)
            ->options(fn () => [self::NO_COLLECTION => 'Not in any collection']
                + Collection::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all())
            ->query(function (Builder $query, array $data) {
                $values = array_filter((array) ($data['values'] ?? []), 'filled');
                if ($values === []) {
                    return $query;
                }
                $ids = array_values(array_diff($values, [self::NO_COLLECTION]));
                $none = in_array(self::NO_COLLECTION, $values, true);

                return $query->where(fn (Builder $q) => $q
                    ->when($ids, fn (Builder $q) => $q->orWhereHas('collections', fn (Builder $c) => $c->whereKey($ids)))
                    ->when($none, fn (Builder $q) => $q->orWhereDoesntHave('collections')));
            });
    }
}
