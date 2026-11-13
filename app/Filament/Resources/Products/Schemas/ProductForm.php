<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Admin\StoreContext;
use App\Filament\Support\Fields;
use App\Clients\ClientQuota;
use App\Models\Product;
use App\Rules\WithinClientImageLimit;
use App\Templates\Template;
use App\Templates\TemplateManager;
use Closure;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Product')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Details')
                            ->icon('heroicon-o-tag')
                            ->schema([
                                Grid::make(2)->schema([
                                    ...Fields::nameAndSlug(),
                                    Select::make('category_id')
                                        ->label('Category')
                                        ->relationship('category', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->required(),
                                    TextInput::make('sku')->label('SKU')->maxLength(64)
                                        ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('client_id', app(\App\Clients\ClientContext::class)->id())),
                                    TextInput::make('brand')->maxLength(80)->datalist(fn () => \App\Models\Product::query()->whereNotNull('brand')->distinct()->pluck('brand')->all())->helperText('Used by the shop Brand filter.'),
                                    TextInput::make('material')->label('Primary material')->maxLength(80)->datalist(fn () => \App\Models\Product::query()->whereNotNull('material')->distinct()->pluck('material')->all())->helperText('Used by the shop Material filter.'),
                                ]),
                                Textarea::make('description')
                                    ->label('Short description')
                                    ->rows(3)
                                    ->maxLength(600)
                                    ->helperText('Shown on product cards, quick view and search results.'),
                                RichEditor::make('details')
                                    ->label('Full description')
                                    ->toolbarButtons(['bold', 'italic', 'underline', 'bulletList', 'orderedList', 'h3', 'link', 'undo', 'redo']),
                                Grid::make(2)->schema([
                                    Textarea::make('materials')->rows(3),
                                    Textarea::make('care')->label('Care instructions')->rows(3),
                                ]),
                                Select::make('collections')
                                    ->relationship('collections', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable(),
                            ]),

                        Tab::make('Media & Variants')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Fields::image('images', 'Product images', 'products', multiple: true)
                                    // The allowance is enforced here as well as in the policy, so the
                                    // limit holds even if the field is posted directly.
                                    ->rules(fn (?Product $record) => [new WithinClientImageLimit(self::imagePaths($record))])
                                    ->helperText(function (?Product $record) {
                                        $quota = ClientQuota::current();
                                        $base = 'Drag to reorder. The first image is the main image; the second is shown on hover.';

                                        return $quota && $quota->imageLimit() !== null
                                            ? $base.' Product images used: '.$quota->imageLabel().'.'
                                            : $base;
                                    })
                                    ->required(),
                                TextInput::make('video_url')->label('Product video (optional)')->url()->placeholder('https://…/video.mp4 or a YouTube link')->helperText('Shown as the last slide of the gallery.'),

                                Section::make('Variants')
                                    ->description('Define what this product varies by — size, colour, material, style or anything else. Switch variants on to give each combination its own price, SKU and stock.')
                                    ->schema([
                                        Toggle::make('has_variants')
                                            ->label('Sell this product by variant')
                                            ->live()
                                            ->default(false)
                                            ->helperText('Off: the values below are shown as plain choices and the product price applies to all of them.'),
                                        Repeater::make('variant_attributes')
                                            ->label('Variant attributes')
                                            ->live()
                                            ->addActionLabel('Add Variant')
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Attribute')
                                                    ->required()
                                                    ->maxLength(40)
                                                    ->live(onBlur: true)
                                                    // A dot would split the name into a nested path.
                                                    ->notRegex('/\./')
                                                    ->validationMessages(['not_regex' => 'Attribute names cannot contain a full stop.'])
                                                    ->datalist(['Size', 'Colour', 'Material', 'Style', 'Finish', 'Pack size', 'Length'])
                                                    ->helperText('What the customer chooses.'),
                                                TagsInput::make('values')
                                                    ->label('Values')
                                                    ->required()
                                                    ->live()
                                                    ->reorderable()
                                                    ->placeholder('Add a value and press Enter'),
                                                // Colour attributes get a colour picker for each value entered,
                                                // written to swatches.<Value>, so the saved shape is still
                                                // {Value: hex}. Values with a full stop are skipped (it would
                                                // split the path).
                                                Section::make('Swatch colours (optional)')
                                                    ->description('Shown as round swatches on the storefront instead of text. Leave a colour empty to show that value as text.')
                                                    ->compact()
                                                    ->columnSpanFull()
                                                    ->visible(fn (Get $get) => self::isColourAttribute($get('name')))
                                                    ->schema(fn (Get $get) => self::swatchValues($get('values')) === []
                                                        ? [Text::make('Add values (e.g. Red, Navy) and a colour picker appears for each one.')]
                                                        : array_map(
                                                            fn (string $value) => ColorPicker::make('swatches.'.$value)->label($value),
                                                            self::swatchValues($get('values')),
                                                        ))
                                                    ->columns(4),
                                            ])
                                            ->columns(2)
                                            ->reorderable()
                                            ->defaultItems(0)
                                            ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null)
                                                ? $state['name'].' ('.count((array) ($state['values'] ?? [])).')'
                                                : null),
                                        Repeater::make('variants')
                                            ->label('Combinations')
                                            ->visible(fn ($get) => (bool) $get('has_variants'))
                                            ->addActionLabel('Add combination')
                                            ->schema([
                                                // One dropdown per attribute defined above, listing its values.
                                                // Each writes to options.<Attribute>, so the saved shape is
                                                // still {Attribute: Value}.
                                                Group::make(fn (Get $get) => array_map(
                                                    fn (array $attribute) => Select::make('options.'.$attribute['name'])
                                                        ->label($attribute['name'])
                                                        ->options(array_combine($attribute['values'], $attribute['values']))
                                                        ->native(false)
                                                        ->required(),
                                                    self::definedAttributes($get),
                                                ))
                                                    ->columns(3)
                                                    ->columnSpanFull(),
                                                Text::make('Add a variant attribute above (e.g. Size with values S, M, L) to choose values here.')
                                                    ->visible(fn (Get $get) => self::definedAttributes($get) === [])
                                                    ->columnSpanFull(),
                                                TextInput::make('sku')->label('SKU')->maxLength(64)->placeholder('Same as the product'),
                                                TextInput::make('price')->label('Price')->numeric()->prefix('₹')->minValue(0)->placeholder('Same as the product'),
                                                TextInput::make('stock')->label('Stock')->numeric()->minValue(0)->placeholder('Same as the product'),
                                                Toggle::make('is_active')->label('Available')->default(true)->inline(false),
                                            ])
                                            ->columns(3)
                                            ->reorderable()
                                            ->defaultItems(0)
                                            ->itemLabel(fn (array $state): ?string => filled($state['options'] ?? null)
                                                ? implode(' / ', array_filter((array) $state['options']))
                                                : null),
                                        Section::make('Colour & size lists')
                                            ->description('The original lists. They are kept so existing products keep working, and they are read as a Colour and a Size attribute when no attributes are defined above.')
                                            ->collapsed()
                                            ->schema([
                                                Repeater::make('colors')
                                                    ->label('Colours')
                                                    ->schema([
                                                        TextInput::make('name')->required()->maxLength(40),
                                                        ColorPicker::make('hex')->label('Swatch')->required(),
                                                    ])
                                                    ->columns(2)
                                                    ->reorderable()
                                                    ->defaultItems(0)
                                                    ->addActionLabel('Add colour')
                                                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
                                                TagsInput::make('sizes')
                                                    ->placeholder('Add a size and press Enter')
                                                    ->helperText('e.g. XS, S, M, L — or 50ml, 100ml — or "One size".')
                                                    ->reorderable(),
                                            ]),
                                    ]),
                            ]),

                        Tab::make('Pricing & Inventory')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('price')->numeric()->prefix('₹')->required()->minValue(0),
                                    TextInput::make('compare_at_price')
                                        ->label('Compare-at price')
                                        ->numeric()
                                        ->prefix('₹')
                                        ->minValue(0)
                                        ->helperText('Set higher than the price to show a sale badge and strikethrough.'),
                                    TextInput::make('stock')->numeric()->minValue(0)->default(100)->required(),
                                    TextInput::make('max_qty')
                                        ->label('Max quantity per order')
                                        ->numeric()->minValue(1)->maxValue(99)
                                        ->placeholder('No limit')
                                        ->visible(self::uses(Template::PRODUCT_FIELDS_ORDER_LIMIT)),
                                ]),
                                Section::make('Merchandising')->columns(3)->schema([
                                    Toggle::make('is_active')->label('Visible on storefront')->default(true),
                                    Fields::templateVisibility()->live()->columnSpan(2),
                                    Toggle::make('is_new')->label('New arrival'),
                                    Toggle::make('is_best_seller')->label('Best seller'),
                                    TextInput::make('rating')->numeric()->minValue(0)->maxValue(5)->step(0.1)->default(0),
                                    TextInput::make('review_count')->numeric()->minValue(0)->default(0),
                                    TextInput::make('sort_order')->numeric()->default(0)->helperText('Lower numbers appear first.'),
                                ]),
                            ]),

                        Tab::make('Grocery & nutrition')
                            ->icon('heroicon-o-shopping-cart')
                            ->visible(self::uses(Template::PRODUCT_FIELDS_GROCERY, Template::PRODUCT_FIELDS_NUTRITION))
                            ->schema([
                                Section::make('Grocery attributes')
                                    ->visible(self::uses(Template::PRODUCT_FIELDS_GROCERY))
                                    ->description('Used by the Indian Grocery template. Pack sizes are the "Sizes" on the Media & Variants tab (e.g. 500 g, 1 kg). Ignored by other templates.')
                                    ->columns(3)
                                    ->schema([
                                        Select::make('is_veg')
                                            ->label('Veg / non-veg mark')
                                            ->options([1 => 'Vegetarian (green)', 0 => 'Non-vegetarian (red)'])
                                            ->placeholder('Not applicable')
                                            ->native(false),
                                        TextInput::make('shelf_life')->label('Shelf life')->placeholder('6 months')->maxLength(80),
                                        TextInput::make('country_of_origin')->label('Country of origin')->placeholder('India')->maxLength(80),
                                        Textarea::make('ingredients')->rows(3)->columnSpan(3),
                                        Textarea::make('storage_instructions')->label('Storage instructions')->rows(2)->columnSpan(3),
                                    ]),
                                Section::make('Dietary, benefits & nutrition')
                                    ->visible(self::uses(Template::PRODUCT_FIELDS_NUTRITION))
                                    ->description('Used by the Heritage Grocery template: dietary filter chips, the Benefits / How to use tabs and the nutrition table.')
                                    ->columns(2)
                                    ->schema([
                                        TagsInput::make('dietary_tags')->label('Dietary tags')->suggestions(['Organic', 'Vegan', 'Gluten-free', 'Sugar-free', 'High protein', 'High fibre', 'No preservatives', 'Cold-pressed', 'Stone-ground'])->columnSpan(2),
                                        Textarea::make('benefits')->label('Benefits')->rows(3)->helperText('One benefit per line.'),
                                        Textarea::make('usage_instructions')->label('How to use / cook')->rows(3),
                                        Repeater::make('nutrition')
                                            ->label('Nutrition (per 100 g)')
                                            ->schema([TextInput::make('label')->required(), TextInput::make('value')->required()])
                                            ->columns(2)->defaultItems(0)->addActionLabel('Add row')->reorderable()->columnSpan(2)
                                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null),
                                    ]),
                            ]),

                        Tab::make('Plumbing specifications')
                            ->icon('heroicon-o-wrench-screwdriver')
                            ->visible(self::uses(Template::PRODUCT_FIELDS_SPECIFICATIONS))
                            ->schema([
                                Section::make('Technical data')
                                    ->description('Used by the Plumbing template: the specification table, Applications and Installation tabs on the product page. Sizes (e.g. 1/2 inch, 1 inch) are the "Sizes" on the Media & Variants tab; brand and material are on the Details tab.')
                                    ->columns(2)
                                    ->schema([
                                        Repeater::make('specifications')
                                            ->label('Specifications')
                                            ->schema([TextInput::make('label')->required()->placeholder('Material'), TextInput::make('value')->required()->placeholder('CPVC')])
                                            ->columns(2)->defaultItems(0)->addActionLabel('Add row')->reorderable()->columnSpan(2)
                                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null),
                                        Textarea::make('applications')->label('Applications')->rows(3)->helperText('One application per line, e.g. "Hot and cold water lines".'),
                                        Textarea::make('installation_notes')->label('Installation / usage notes')->rows(3),
                                    ]),
                            ]),

                        Fields::seoTab(),
                    ]),
            ]);
    }

    /**
     * Visibility for a template-specific field group: shown when the product's template uses any
     * of the groups. A store's own admin always works in its template; a super admin follows the
     * "Visible in" picker, and a product shared by all templates shows every group.
     */
    /**
     * The attributes defined on the form, read from inside a Combinations item: the Variant
     * attributes repeater, or the older colour and size lists when none are defined (the same
     * fallback Product::variantAttributes() uses).
     *
     * @return array<int, array{name:string, values:array<int,string>}>
     */
    private static function definedAttributes(Get $get): array
    {
        $attributes = [];
        foreach ((array) $get('../../variant_attributes') as $attribute) {
            $name = trim((string) ($attribute['name'] ?? ''));
            $values = array_values(array_unique(array_filter(array_map(fn ($v) => trim((string) $v), (array) ($attribute['values'] ?? [])), 'strlen')));
            if ($name !== '' && ! str_contains($name, '.') && $values) {
                $attributes[$name] = ['name' => $name, 'values' => $values];
            }
        }
        if ($attributes) {
            return array_values($attributes);
        }

        $colours = array_values(array_filter(array_map(fn ($c) => trim((string) (is_array($c) ? ($c['name'] ?? '') : $c)), (array) $get('../../colors')), 'strlen'));
        $sizes = array_values(array_filter(array_map(fn ($s) => trim((string) $s), (array) $get('../../sizes')), 'strlen'));

        return array_values(array_filter([
            $colours ? ['name' => 'Colour', 'values' => array_values(array_unique($colours))] : null,
            $sizes ? ['name' => 'Size', 'values' => array_values(array_unique($sizes))] : null,
        ]));
    }

    /** Colour, Color, Colours or Colors, in any case. */
    private static function isColourAttribute(mixed $name): bool
    {
        return (bool) preg_match('/^colou?rs?$/i', trim((string) $name));
    }

    /**
     * The values of a colour attribute that can each have a swatch picker.
     *
     * @return array<int, string>
     */
    private static function swatchValues(mixed $values): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn ($v) => trim((string) $v), (array) $values),
            fn (string $v) => $v !== '' && ! str_contains($v, '.'),
        )));
    }

    private static function uses(string ...$groups): Closure
    {
        return function (Get $get) use ($groups): bool {
            $templates = app(TemplateManager::class);
            $id = (string) (app(StoreContext::class)->template() ?? $get('template'));
            if ($id === '' || ! $templates->has($id)) {
                return true;
            }

            return (bool) array_intersect($groups, $templates->get($id)->productFields());
        };
    }

    /**
     * Every image this product holds today, product-level and per variant. The allowance
     * check subtracts these before counting, so swapping an image never trips the limit.
     *
     * @return array<string>
     */
    private static function imagePaths(?Product $product): array
    {
        if (! $product) {
            return [];
        }

        $paths = (array) $product->images;
        foreach ((array) $product->variants as $variant) {
            $paths = [...$paths, ...(array) ($variant['images'] ?? [])];
        }

        return array_values(array_unique(array_filter($paths, 'is_string')));
    }
}
