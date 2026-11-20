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
                    ->placeholder(fn (StockMovement $r) => $r->reason === StockMovement::SALE ? 'Customer' : 'System'),
                TextColumn::make('note')->placeholder('—')->wrap()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('product_id')->label('Product')->searchable()
                    ->options(fn () => Product::query()->orderBy('name')->pluck('name', 'id')->all()),
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

    public static function getPages(): array
    {
        return [
            'index' => ListStockMovements::route('/'),
        ];
    }
}
