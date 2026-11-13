<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Templates\TemplateManager;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Read-only overview of a product: what it is and how it sells on the left; price, stock,
 * storefront flags and sales on the right. Template-specific details (grocery, nutrition,
 * specifications) appear only when the product has them. Reviews are listed below the page.
 */
class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 2])->schema([
                Section::make('Product')
                    ->icon('heroicon-o-shopping-bag')
                    ->columns(2)
                    ->schema([
                        ImageEntry::make('image_urls')->hiddenLabel()->columnSpanFull()
                            ->imageHeight(140)->stacked(false)->limit(8)
                            ->placeholder('No images'),
                        TextEntry::make('name')->weight('bold')->size('lg')->columnSpanFull(),
                        TextEntry::make('category.name')->label('Category')->badge()->color('gray')
                            ->url(fn (Product $r) => $r->category ? CategoryResource::getUrl('view', ['record' => $r->category]) : null)
                            ->placeholder('—'),
                        TextEntry::make('sku')->label('SKU')->copyable()->placeholder('—'),
                        TextEntry::make('brand')->placeholder('—'),
                        TextEntry::make('material')->label('Primary material')->placeholder('—'),
                        TextEntry::make('description')->label('Short description')->columnSpanFull()->placeholder('—'),
                        TextEntry::make('details')->label('Full description')->html()->columnSpanFull()
                            ->visible(fn (Product $r) => filled(strip_tags((string) $r->details))),
                        TextEntry::make('materials')->label('Materials')->visible(fn (Product $r) => filled($r->materials)),
                        TextEntry::make('care')->label('Care')->visible(fn (Product $r) => filled($r->care)),
                    ]),

                Section::make('Variants')
                    ->icon('heroicon-o-swatch')
                    ->description(fn (Product $r) => $r->sellsVariants()
                        ? 'Price, SKU and stock are set per variant.'
                        : 'Options the shopper picks; price and stock are the product\'s own.')
                    ->visible(fn (Product $r) => $r->variantAttributes() !== [])
                    ->schema([
                        RepeatableEntry::make('options')->hiddenLabel()
                            ->state(fn (Product $r) => array_map(fn ($a) => ['name' => $a['name'], 'values' => $a['values']], $r->variantAttributes()))
                            ->schema([
                                TextEntry::make('name')->hiddenLabel()->weight('bold'),
                                TextEntry::make('values')->hiddenLabel()->badge()->color('gray'),
                            ])
                            ->columns(2)
                            ->contained(false),
                        RepeatableEntry::make('variant_rows')->label('Combinations')
                            ->visible(fn (Product $r) => $r->sellsVariants())
                            ->state(fn (Product $r) => $r->variantList())
                            ->schema([
                                TextEntry::make('label')->hiddenLabel()->weight('medium'),
                                TextEntry::make('sku')->hiddenLabel()->placeholder('—')->color('gray'),
                                TextEntry::make('price')->hiddenLabel()->formatStateUsing(fn ($state) => money((int) $state)),
                                TextEntry::make('stock')->hiddenLabel()->formatStateUsing(fn ($state) => $state.' in stock'),
                                IconEntry::make('is_active')->hiddenLabel()->boolean(),
                            ])
                            ->columns(5),
                    ]),

                Section::make('Grocery details')
                    ->icon('heroicon-o-shopping-cart')
                    ->columns(3)
                    ->visible(fn (Product $r) => self::anyFilled($r, ['is_veg', 'shelf_life', 'country_of_origin', 'ingredients', 'storage_instructions']))
                    ->schema([
                        TextEntry::make('is_veg')->label('Veg mark')
                            ->formatStateUsing(fn ($state) => $state ? 'Vegetarian' : 'Non-vegetarian')
                            ->badge()->color(fn ($state) => $state ? 'success' : 'danger')->placeholder('Not applicable'),
                        TextEntry::make('shelf_life')->placeholder('—'),
                        TextEntry::make('country_of_origin')->placeholder('—'),
                        TextEntry::make('ingredients')->columnSpanFull()->placeholder('—'),
                        TextEntry::make('storage_instructions')->columnSpanFull()->placeholder('—'),
                    ]),

                Section::make('Dietary, benefits & nutrition')
                    ->icon('heroicon-o-heart')
                    ->columns(2)
                    ->visible(fn (Product $r) => self::anyFilled($r, ['dietary_tags', 'benefits', 'usage_instructions', 'nutrition']))
                    ->schema([
                        TextEntry::make('dietary_tags')->label('Dietary tags')->badge()->color('success')->columnSpanFull()->placeholder('—'),
                        TextEntry::make('benefits')->placeholder('—'),
                        TextEntry::make('usage_instructions')->label('How to use')->placeholder('—'),
                        RepeatableEntry::make('nutrition')->label('Nutrition (per 100 g)')->columnSpanFull()
                            ->schema([TextEntry::make('label')->hiddenLabel(), TextEntry::make('value')->hiddenLabel()->alignRight()])
                            ->columns(2),
                    ]),

                Section::make('Specifications')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->columns(2)
                    ->visible(fn (Product $r) => self::anyFilled($r, ['specifications', 'applications', 'installation_notes']))
                    ->schema([
                        RepeatableEntry::make('specifications')->hiddenLabel()->columnSpanFull()
                            ->schema([TextEntry::make('label')->hiddenLabel()->weight('medium'), TextEntry::make('value')->hiddenLabel()])
                            ->columns(2),
                        TextEntry::make('applications')->placeholder('—'),
                        TextEntry::make('installation_notes')->label('Installation notes')->placeholder('—'),
                    ]),
            ]),

            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 1])->schema([
                Section::make('Price & stock')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        TextEntry::make('price')->inlineLabel()->formatStateUsing(fn (int $state) => money($state))->weight('bold'),
                        TextEntry::make('compare_at_price')->label('Compare-at')->inlineLabel()
                            ->formatStateUsing(fn ($state, Product $r) => money((int) $state).($r->discount_percent ? " ({$r->discount_percent}% off)" : ''))
                            ->visible(fn (Product $r) => (int) $r->compare_at_price > 0),
                        TextEntry::make('stock')->inlineLabel()->badge()
                            ->color(fn (Product $r) => $r->stock <= 0 ? 'danger' : ($r->stock <= 5 ? 'warning' : 'success'))
                            ->formatStateUsing(fn ($state) => (int) $state <= 0 ? 'Out of stock' : $state.' in stock'),
                        TextEntry::make('max_qty')->label('Max per order')->inlineLabel()
                            ->visible(fn (Product $r) => filled($r->max_qty)),
                    ]),

                Section::make('Storefront')
                    ->icon('heroicon-o-building-storefront')
                    ->schema([
                        IconEntry::make('is_active')->label('Visible')->inlineLabel()->boolean(),
                        IconEntry::make('is_new')->label('New arrival')->inlineLabel()->boolean(),
                        IconEntry::make('is_best_seller')->label('Best seller')->inlineLabel()->boolean(),
                        TextEntry::make('rating')->inlineLabel()
                            ->state(fn (Product $r) => $r->review_count ? number_format((float) $r->rating, 1).' ★ · '.$r->review_count.' review'.($r->review_count === 1 ? '' : 's') : null)
                            ->placeholder('No reviews yet'),
                        TextEntry::make('collections.name')->label('Collections')->badge()->color('gray')->placeholder('None'),
                        TextEntry::make('template')->label('Visible in')->inlineLabel()
                            ->formatStateUsing(fn ($state) => app(TemplateManager::class)->options()[$state] ?? $state)
                            ->placeholder('All templates'),
                        TextEntry::make('slug')->label('URL')->inlineLabel()->prefix('/product/')->copyable()->color('gray'),
                    ]),

                Section::make('Sales')
                    ->icon('heroicon-o-chart-bar')
                    ->description('Excluding cancelled orders.')
                    ->schema([
                        TextEntry::make('units_sold')->label('Units sold')->inlineLabel()->state(fn (Product $r) => self::sales($r)['units']),
                        TextEntry::make('revenue')->inlineLabel()->state(fn (Product $r) => money(self::sales($r)['revenue'])),
                        TextEntry::make('orders')->label('Orders')->inlineLabel()->state(fn (Product $r) => self::sales($r)['orders']),
                        TextEntry::make('last_sold')->label('Last sold')->inlineLabel()
                            ->state(fn (Product $r) => self::sales($r)['last'])->since()->placeholder('Never'),
                    ]),

                Section::make('Record')
                    ->icon('heroicon-o-clock')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('created_at')->label('Created')->inlineLabel()->dateTime('d M Y, H:i'),
                        TextEntry::make('updated_at')->label('Updated')->inlineLabel()->since(),
                        TextEntry::make('deleted_at')->label('Deleted')->inlineLabel()->dateTime('d M Y, H:i')->color('danger')
                            ->visible(fn (Product $r) => $r->trashed()),
                        TextEntry::make('meta_title')->label('SEO title')->placeholder('Uses the product name'),
                        TextEntry::make('meta_description')->label('SEO description')->placeholder('Uses the short description'),
                    ]),
            ]),
        ]);
    }

    private static function anyFilled(Product $product, array $fields): bool
    {
        foreach ($fields as $field) {
            $value = $product->{$field};
            if ($value !== null && $value !== '' && $value !== []) {
                return true;
            }
        }

        return false;
    }

    /** @return array{units:int, revenue:int, orders:int, last:?string} */
    private static function sales(Product $product): array
    {
        // Several entries read these; once() works them out a single time per product per request.
        return once(function () use ($product) {
            $items = OrderItem::query()
                ->where('product_id', $product->getKey())
                ->whereHas('order', fn ($q) => $q->where('status', '!=', Order::STATUS_CANCELLED));

            return [
                'units' => (int) (clone $items)->sum('qty'),
                'revenue' => (int) (clone $items)->sum('total'),
                'orders' => (int) (clone $items)->distinct()->count('order_id'),
                'last' => (clone $items)->max('created_at'),
            ];
        });
    }
}
