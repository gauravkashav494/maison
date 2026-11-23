<?php

namespace App\Filament\Resources\StockMovements;

use App\Clients\ClientAccess;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Support\CategoryFilter;
use App\Filament\Support\DateRangeFilter;
use App\Models\Product;
use App\Models\StockMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\Filter;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Inventory → Stock movements: every move in or out of stock, newest first: sales, cancelled
 * orders, stock received, counts… with who did it and the stock left after. Read-only.
 */
class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $slug = 'stock-movements';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Stock movements';

    protected static ?string $modelLabel = 'stock movement';

    protected static bool $isGloballySearchable = false;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('product'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('d M Y, H:i')->sortable()->width('10rem'),
                TextColumn::make('product_name')->label('Item')->searchable()->wrap()
                    ->description(fn (StockMovement $r) => $r->variant)
                    ->url(fn (StockMovement $r) => $r->product && ! $r->product->trashed() && ClientAccess::current('products', 'view')
                        ? ProductResource::getUrl('view', ['record' => $r->product_id]) : null),
                TextColumn::make('reason')->label('Why')->badge()
                    ->formatStateUsing(fn (StockMovement $r) => $r->reasonLabel())
                    ->color(fn (StockMovement $r) => match ($r->reason) {
                        StockMovement::SALE => 'info',
                        StockMovement::DAMAGED => 'danger',
                        StockMovement::RECEIVED, StockMovement::RETURNED, StockMovement::CANCELLED => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('quantity')->label('Change')->alignRight()->weight('medium')
                    ->formatStateUsing(fn (int $state) => $state > 0 ? '+'.number_format($state) : ($state < 0 ? '−'.number_format(abs($state)) : '0'))
                    ->color(fn (int $state) => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray')),
                TextColumn::make('stock_after')->label('Stock after')->alignRight()->numeric(),
                TextColumn::make('order_number')->label('Order')->placeholder('—')
                    ->url(fn (StockMovement $r) => $r->order_id && ClientAccess::current('orders', 'view')
                        ? OrderResource::getUrl('view', ['record' => $r->order_id]) : null),
                TextColumn::make('by_name')->label('By')
                    ->placeholder(fn (StockMovement $r) => match ($r->reason) {
                        StockMovement::SALE => 'Customer',
                        StockMovement::SYNCED => 'Sync agent',
                        default => 'System',
                    }),
                TextColumn::make('note')->placeholder('—')->wrap()->toggleable(),
            ])
            ->filters([
                self::productFilter(),
                // Movements of products in this category or any below it.
                CategoryFilter::through('product'),
                SelectFilter::make('reason')->label('Why')->options(StockMovement::REASONS)->multiple(),
                DateRangeFilter::range('when', 'When'),
            ])
            ->striped()
            ->emptyStateHeading('No stock movements yet')
            ->emptyStateDescription('Sales, cancelled orders and stock adjustments show up here.')
            ->emptyStateIcon('heroicon-o-arrows-right-left');
    }

    /**
     * Product, and for a product sold by variant one of its variants. Named "product_id" with its
     * product in "value", as a plain select filter would be, so links from Stock levels
     * (filters[product_id][value]=12) keep working.
     */
    private static function productFilter(): Filter
    {
        return Filter::make('product_id')
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
                    // Always shown, so the bar keeps the same cells; usable once a product sold by variant is picked.
                    ->placeholder('All variants')
                    ->options(fn (Get $get) => self::variantOptions($get('value')))
                    ->disabled(fn (Get $get) => self::variantOptions($get('value')) === []),
            ])
            ->query(fn (Builder $query, array $data) => $query
                ->when(filled($data['value'] ?? null), fn (Builder $q) => $q->where('product_id', $data['value']))
                ->when(filled($data['value'] ?? null) && filled($data['variant'] ?? null), fn (Builder $q) => $q->where('variant', $data['variant'])))
            ->indicateUsing(function (array $data): array {
                if (blank($data['value'] ?? null)) {
                    return [];
                }
                $name = Product::withTrashed()->find($data['value'])?->name ?? 'Product #'.$data['value'];

                return [Indicator::make('Product: '.$name.(filled($data['variant'] ?? null) ? ' ('.$data['variant'].')' : ''))];
            });
    }

    /**
     * A product's variants as the history names them ("M / Red"): the ones it has now and any
     * that only remain in its history (renamed or removed since). Empty for a product not sold by variant.
     *
     * @return array<string, string>
     */
    private static function variantOptions(mixed $productId): array
    {
        $product = filled($productId) ? Product::withTrashed()->find($productId) : null;
        if (! $product) {
            return [];
        }

        $labels = $product->sellsVariants() ? array_column($product->variantList(), 'label') : [];
        $labels = array_values(array_unique([
            ...$labels,
            ...StockMovement::query()->where('product_id', $product->getKey())->whereNotNull('variant')->distinct()->pluck('variant')->all(),
        ]));

        return array_combine($labels, $labels) ?: [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockMovements::route('/'),
        ];
    }
}
