<?php

namespace App\Filament\Pages;

use App\Admin\StoreContext;
use App\Clients\ClientAccess;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Filament\Support\CategoryFilter;
use App\Filament\Support\RowActions;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Inventory;
use App\Support\Media;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Inventory → Stock levels: what is left of every product, lowest first, with Out of stock and
 * Low stock picked out. Staff with Inventory "Edit" record stock received, returns, damage and
 * stock counts here (App\Services\Inventory), and set the low-stock level.
 */
class StockLevels extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.stock-levels';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Stock levels';

    protected static ?string $title = 'Stock levels';

    protected static ?string $slug = 'inventory';

    public static function canAccess(): bool
    {
        return app(StoreContext::class)->allowsModel(StockMovement::class) && ClientAccess::current('inventory', 'view');
    }

    /** Low and out-of-stock items need someone to act, so the menu shows how many there are. */
    public static function getNavigationBadge(): ?string
    {
        $count = Inventory::whereStatus(Product::query(), Inventory::STATUS_OUT)->count()
            + Inventory::whereStatus(Product::query(), Inventory::STATUS_LOW)->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Out of stock or low on stock';
    }

    public function getSubheading(): ?string
    {
        $out = Inventory::whereStatus(Product::query(), Inventory::STATUS_OUT)->count();
        $low = Inventory::whereStatus(Product::query(), Inventory::STATUS_LOW)->count();

        return "{$out} out of stock · {$low} low on stock (".Inventory::lowStockLevel().' or fewer left)';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('lowStockLevel')
                ->label('Low-stock level')
                ->icon(Heroicon::OutlinedBellAlert)
                ->color('gray')
                ->visible(fn () => ClientAccess::current('inventory', 'update'))
                ->modalWidth('md')
                ->fillForm(fn () => ['level' => Inventory::lowStockLevel()])
                ->schema([
                    TextInput::make('level')->label('Low stock at or below')->integer()->minValue(0)->maxValue(100000)->required()->suffix('units')
                        ->helperText('Items with this many or fewer left are marked Low stock. For a product sold by variant, any one variant counts.'),
                ])
                ->action(function (array $data) {
                    app(Inventory::class)->setLowStockLevel((int) $data['level']);
                    Notification::make()->success()->title('Low-stock level saved')->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Product::query())
            ->defaultSort('lowest_stock')
            ->columns([
                ImageColumn::make('image')->label('')
                    ->state(fn (Product $record) => Media::url($record->images[0] ?? null))
                    ->square()->imageSize(40),
                TextColumn::make('name')->label('Product')->searchable(['name', 'sku'])->sortable()->wrap()
                    ->description(fn (Product $record) => $record->sku),
                TextColumn::make('variants_summary')->label('Variants')->placeholder('—')->wrap()
                    ->state(fn (Product $record) => self::variantSummary($record)),
                TextColumn::make('stock')->label('In stock')->numeric()->sortable()->alignRight()->weight('medium'),
                TextColumn::make('stock_status')->label('Status')->badge()
                    ->state(fn (Product $record) => Inventory::status($record))
                    ->formatStateUsing(fn (string $state) => Inventory::STATUSES[$state])
                    ->color(fn (string $state) => match ($state) {
                        Inventory::STATUS_OUT => 'danger',
                        Inventory::STATUS_LOW => 'warning',
                        default => 'success',
                    }),
                IconColumn::make('is_active')->label('On sale')->boolean()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('stock_status')->label('Status')->options(Inventory::STATUSES)
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) ? Inventory::whereStatus($query, $data['value']) : $query),
                // Also finds the products in the categories below the one picked.
                CategoryFilter::make(),
            ])
            ->recordActions([RowActions::group([
                $this->adjustAction(),
                Action::make('history')->label('Stock history')->icon(Heroicon::OutlinedClock)
                    ->url(fn (Product $record) => StockMovementResource::getUrl('index', ['filters' => ['product_id' => ['value' => $record->getKey()]]])),
            ])])
            ->striped()
            ->emptyStateHeading('No products yet')
            ->emptyStateDescription('Products you add under Catalogue show up here with their stock.')
            ->emptyStateIcon('heroicon-o-archive-box');
    }

    private function adjustAction(): Action
    {
        return Action::make('adjust')
            ->label('Adjust stock')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->visible(fn () => ClientAccess::current('inventory', 'update'))
            ->modalHeading(fn (Product $record) => 'Adjust stock: '.$record->name)
            ->modalSubmitActionLabel('Save')
            ->modalWidth('md')
            ->fillForm(['type' => StockMovement::RECEIVED])
            ->schema(fn (Product $record) => [
                Select::make('variant')->label('Variant')->native(false)->required()
                    ->options(Inventory::variantOptions($record))
                    ->visible($record->sellsVariants()),
                Select::make('type')->label('What happened')->native(false)->required()->live()
                    ->options(Inventory::ADJUSTMENTS),
                TextInput::make('quantity')->required()->integer()->maxValue(1000000)
                    ->label(fn (Get $get) => $get('type') === StockMovement::COUNT ? 'Counted on the shelf' : 'How many')
                    ->minValue(fn (Get $get) => $get('type') === StockMovement::COUNT ? 0 : 1)
                    ->helperText($record->sellsVariants() ? null : 'Now '.number_format((int) $record->stock).' in stock.'),
                TextInput::make('note')->label('Note')->maxLength(255)->placeholder('Supplier invoice, reason…'),
            ])
            ->action(function (Product $record, array $data, Action $action) {
                try {
                    app(Inventory::class)->adjust($record, $data['variant'] ?? null, $data['type'], (int) $data['quantity'], $data['note'] ?? null);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->danger()->title('Stock not changed')->body($e->getMessage())->send();
                    $action->halt();
                }

                Notification::make()->success()->title('Stock updated')->send();
            });
    }

    /** "6 variants · 2 sold out · 1 low", for a product sold by variant. */
    private static function variantSummary(Product $product): ?string
    {
        if (! $product->sellsVariants()) {
            return null;
        }

        $variants = array_filter($product->variantList(), fn (array $v) => $v['is_active']);
        $level = Inventory::lowStockLevel();
        $out = count(array_filter($variants, fn (array $v) => $v['stock'] <= 0));
        $low = count(array_filter($variants, fn (array $v) => $v['stock'] > 0 && $v['stock'] <= $level));

        return implode(' · ', array_filter([
            count($variants).' '.str('variant')->plural(count($variants)),
            $out ? $out.' sold out' : null,
            $low ? $low.' low' : null,
        ]));
    }
}
