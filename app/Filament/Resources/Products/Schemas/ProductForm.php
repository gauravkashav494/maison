<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Admin\StoreContext;
use App\Clients\ClientAccess;
use App\Clients\ClientContext;
use App\Clients\ClientQuota;
use App\Clients\StoreFeatures;
use App\Filament\Forms\Components\ImagePicker;
use App\Filament\Pages\StockLevels;
use App\Filament\Resources\Brands\Schemas\BrandForm;
use App\Filament\Resources\Taxes\Schemas\TaxForm;
use App\Filament\Support\Fields;
use App\Models\Category;
use App\Models\Collection;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\SyncToken;
use App\Models\Tax;
use App\Rules\WithinClientImageLimit;
use App\Rules\WithinProductImageCap;
use App\Support\ColourNames;
use App\Support\TaxPrices;
use App\Templates\Template;
use App\Templates\TemplateManager;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconPosition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ProductForm
{
    /**
     * A shop with an active sync token exchanges products with its billing software. Items are
     * matched by the billing software's own id, but each variant by its SKU, so variants need one.
     */
    public static function syncsWithBillingSoftware(): bool
    {
        $clientId = app(ClientContext::class)->id();

        return $clientId !== null
            && SyncToken::active()->where('client_id', $clientId)->exists();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Product')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Details')->badge(self::errorBadge('Details'))->badgeColor('danger')
                            ->icon('heroicon-o-tag')
                            ->schema([
                                Grid::make(2)->schema([
                                    // First: the product's own name, then its brand, then where it is listed.
                                    TextInput::make('name')->required()->maxLength(255)
                                        // The URL slug is made from it on save (GeneratesSlug) and kept when renamed.
                                        ->helperText(fn (?Model $record) => $record?->slug ? 'Page address: /product/'.$record->slug : 'The page address is made from the name when saved.'),
                                    // Picked from the brand master (Catalogue → Brands); a new one can be added here.
                                    Select::make('brand_id')
                                        ->label('Brand')
                                        ->visible(fn () => StoreFeatures::enabled('brands'))
                                        ->relationship('brandRecord', 'name', fn (Builder $query) => $query->ordered())
                                        ->searchable()
                                        ->preload()
                                        ->createOptionForm(fn () => ClientAccess::current('brands', 'create') || app(StoreContext::class)->isSuperAdmin() ? BrandForm::fields() : null)
                                        ->helperText('Used by the shop Brand filter.'),
                                    // Without the Brands module the name is typed in (it still joins the brand master).
                                    TextInput::make('brand')->maxLength(80)
                                        ->visible(fn () => ! StoreFeatures::enabled('brands'))
                                        ->datalist(fn () => Product::query()->whereNotNull('brand')->distinct()->pluck('brand')->all())
                                        ->helperText('Used by the shop Brand filter.'),
                                ]),
                                // One row: Category, Child category (and a Type box when the child category has
                                // types under it), then two extra places. Grid::make() alone means 2 columns on
                                // large screens, which would win over these.
                                Grid::make(null)
                                    ->columns(fn (Get $get) => ['default' => 1, 'md' => $count = self::shownCategoryLevels($get) + count(self::EXTRA_CATEGORIES), 'lg' => $count])
                                    ->schema(self::categoryFields()),
                                Textarea::make('description')
                                    ->label('Short description')
                                    ->rows(3)
                                    ->maxLength(600)
                                    ->helperText('Shown on product cards, quick view and search results.'),
                                RichEditor::make('details')
                                    ->label('Full description')
                                    ->toolbarButtons(['bold', 'italic', 'underline', 'bulletList', 'orderedList', 'h3', 'link', 'undo', 'redo']),
                                // The material for the shop filter sits with the fuller materials text.
                                Grid::make(2)->schema([
                                    Group::make([
                                        TextInput::make('material')->label('Primary material')->maxLength(80)->datalist(fn () => Product::query()->whereNotNull('material')->distinct()->pluck('material')->all())->helperText('Used by the shop Material filter.'),
                                        Textarea::make('materials')->rows(3),
                                    ]),
                                    Textarea::make('care')->label('Care instructions')->rows(3),
                                ]),
                                // One collection per product. Stored in collection_product (which also keeps the
                                // product's place inside the collection), so it is read and written by hand.
                                Select::make('collection')
                                    ->label('Collection')
                                    ->visible(fn () => StoreFeatures::enabled('collections'))
                                    ->options(fn () => Collection::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('None')
                                    ->loadStateFromRelationshipsUsing(fn (Select $component, ?Product $record) => $component->state(
                                        $record?->collections()->orderBy('collection_product.sort_order')->value('collections.id'),
                                    ))
                                    ->saveRelationshipsUsing(fn (Product $record, $state) => $record->collections()->sync(filled($state) ? [(int) $state] : []))
                                    ->dehydrated(false),
                                Section::make('Merchandising')->columns(3)->schema([
                                    Toggle::make('is_active')->label('Visible on storefront')->default(true),
                                    Fields::templateVisibility()->live()->columnSpan(2),
                                    Toggle::make('is_new')->label('New arrival'),
                                    Toggle::make('is_best_seller')->label('Best seller'),
                                    // The baseline (reviews collected elsewhere); approved storefront reviews are added on top.
                                    TextInput::make('base_rating')->label('Rating')->numeric()->minValue(0)->maxValue(5)->step(0.1)->default(0)
                                        ->helperText(fn (?Product $record) => $record && $record->review_count > $record->base_review_count
                                            ? 'Shown on the storefront with customer reviews: '.number_format((float) $record->rating, 1).' ★'
                                            : null),
                                    TextInput::make('base_review_count')->label('Review count')->numeric()->minValue(0)->default(0)
                                        ->helperText(fn (?Product $record) => $record && $record->review_count > $record->base_review_count
                                            ? 'Plus '.($record->review_count - $record->base_review_count).' approved customer review(s) = '.$record->review_count.' shown'
                                            : 'Approved customer reviews are added to this number.'),
                                    TextInput::make('sort_order')->numeric()->default(0)->helperText('Lower numbers appear first.'),
                                ]),
                                self::tabButtons(create: false),
                            ]),

                        Tab::make('Media')->badge(self::errorBadge('Media'))->badgeColor('danger')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Fields::image('images', 'Product images', 'products', multiple: true)
                                    // The account allowance and the per-product cap are checked on the
                                    // whole form (ProductForm::imageRules), not here: Filament runs an
                                    // upload field's rules once per file, so they would miss photos
                                    // added under a colour, or not run at all on an empty gallery.
                                    ->helperText(function (?Product $record) {
                                        $quota = ClientQuota::current();
                                        $base = 'Drag to reorder. The first image is the main image; the second is shown on hover. '
                                            .'Tip: name a photo after its colour (e.g. container-red-1.jpg) and it is picked for that colour when saved.';

                                        return $quota && $quota->imageLimit() !== null
                                            ? $base.' Product images used: '.$quota->imageLabel().'.'
                                            : $base;
                                    }),
                                // Optional: a product without photos shows a neutral placeholder on the storefront.
                                TextInput::make('video_url')->label('Product video (optional)')->url()->placeholder('https://…/video.mp4 or a YouTube link')->helperText('Shown as the last slide of the gallery.'),
                                self::tabButtons(create: false),
                            ]),

                        // Price, stock and variants in one place, as on most stores: the product's price and
                        // stock, the options (with photos per colour), then each variant's own price and stock.
                        Tab::make('Pricing & Variants')->badge(self::errorBadge('Pricing & Variants'))->badgeColor('danger')
                            ->icon('heroicon-o-swatch')
                            ->schema([
                                // The product's price and stock; a variant without its own price uses it.
                                Section::make('Price & stock')
                                    ->icon('heroicon-o-banknotes')
                                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 6])
                                    ->schema([
                                        // MRP, tax, price including tax, price excluding tax. The tax comes before
                                        // the prices because either price fills in the other from its rate. Only
                                        // one is saved, as `price`: the one the store's prices are entered as (Site
                                        // settings → Payments & tax), so the cart and checkout read it as before.
                                        TextInput::make('compare_at_price')
                                            ->label('MRP')
                                            ->numeric()
                                            ->prefix('₹')
                                            ->minValue(0)
                                            // An MRP below the selling price would read as a price rise, so it is refused.
                                            ->rules([fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get) {
                                                $incl = $get('price_incl_tax');
                                                if (filled($value) && filled($incl) && (float) $value < (float) $incl) {
                                                    $fail('Set the MRP at or above the price including tax ('.money((float) $incl).'), or leave it blank.');
                                                }
                                            }])
                                            ->helperText(fn (Get $get) => $get('has_variants')
                                                ? 'For variants without their own.'
                                                : 'Struck through when above the price.'),
                                        // From the tax master (Catalogue → Taxes): its rate works out the two
                                        // prices, and is copied to tax_rate on save. Variants follow it.
                                        Select::make('tax_id')
                                            ->label('Tax')
                                            ->relationship('tax', 'name', fn (Builder $query) => $query->ordered())
                                            ->getOptionLabelFromRecordUsing(fn (Tax $record) => $record->option_label)
                                            ->native(false)
                                            // Required once the store has taxes; until then products carry no tax.
                                            ->required(fn () => Tax::query()->exists())
                                            ->placeholder('No tax (0%)')
                                            ->helperText(fn () => Tax::query()->exists() ? null : 'Add taxes under Catalogue → Taxes.')
                                            ->createOptionForm(fn () => ClientAccess::current('taxes', 'create') || app(StoreContext::class)->isSuperAdmin() ? TaxForm::fields() : null)
                                            ->live()
                                            // The saved price stays as it is; the other one is worked out again.
                                            ->afterStateUpdated(function (Get $get, Set $set) {
                                                self::syncPrices($get, $set, prices_include_tax());
                                                $set('variants', self::variantRowsWithPrices((array) $get('variants'), self::selectedTaxRate($get('tax_id'))));
                                            }),
                                        // GST classification of the goods: 4, 6 or 8 digits.
                                        self::hsnCodeField()->placeholder('e.g. 6109')
                                            ->helperText(fn (Get $get) => $get('has_variants')
                                                ? 'Optional. The GST classification of the goods. For variants without their own.'
                                                : 'Optional. The GST classification of the goods.'),
                                        self::priceField(inclusive: true),
                                        self::priceField(inclusive: false),
                                        // The saved price: whichever of the two above the store's prices are entered as.
                                        // Variants without their own price use it (their placeholder reads it).
                                        Hidden::make('price'),
                                        // Beside the stock, as on most stores. Optional everywhere and always starts
                                        // blank: staff type their own code. A product without one is still sold from
                                        // the catalogue, just not typed into the pad. Variants without their own use it.
                                        // Optional with sync too: the billing software matches items by its own id.
                                        TextInput::make('sku')->label('SKU / Barcode')->maxLength(64)
                                            ->helperText(fn (Get $get) => implode(' ', array_filter([
                                                self::requiresSku($get) ? 'Optional. Buyers can order by this code on the quick-order pad.' : null,
                                                $get('has_variants') && ! self::syncsWithBillingSoftware() ? 'For variants without their own.' : null,
                                            ])) ?: null)
                                            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('client_id', app(ClientContext::class)->id()))
                                            ->columnSpan(['lg' => 2]),
                                        // Selling as variant: each variant has its own stock (step 2 below),
                                        // so this is not asked twice; it is saved as their total, which the shop
                                        // uses for "in stock" and the cart.
                                        TextInput::make('stock')->numeric()->minValue(0)->default(100)->required()
                                            // What the product starts with when added; once saved, what is on the shelf now
                                            // (the opening figure stays in the note under it).
                                            ->label(fn (?Product $record) => $record?->exists ? 'Current stock' : 'Initial stock')
                                            // Once ordered, stock changes in Inventory → Stock levels, with a reason
                                            // (EditProduct saves it as it is now). Still sent, for the variants total.
                                            ->disabled(fn (?Product $record) => self::stockLocked($record))
                                            ->dehydrated()
                                            ->hintIcon(fn (?Product $record) => self::stockLocked($record) ? 'heroicon-m-lock-closed' : null, tooltip: 'Locked: this product has been ordered')
                                            ->hintColor('warning')
                                            ->hintAction(fn (?Product $record) => self::adjustStockLink($record))
                                            ->helperText(fn (?Product $record) => self::stockNote($record, self::stockLocked($record) ? self::STOCK_LOCKED_HELP : null))
                                            ->hidden(fn (Get $get) => (bool) $get('has_variants'))
                                            ->dehydratedWhenHidden()
                                            ->dehydrateStateUsing(fn ($state, Get $get) => $get('has_variants') ? self::combinationStock($get('variants')) : $state)
                                            ->columnSpan(['lg' => 2]),
                                        // Read-only while selling as variant: the total of the variants' stock,
                                        // shown greyed in the field's place. Not saved itself (`stock` above is).
                                        TextInput::make('variant_stock_total')
                                            ->label('Stock')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->placeholder(fn (Get $get) => number_format(self::combinationStock($get('variants'))))
                                            ->helperText(fn (?Product $record) => self::stockNote($record, 'Total of the variants (step 2 below).'))
                                            ->visible(fn (Get $get) => (bool) $get('has_variants'))
                                            ->columnSpan(['lg' => 2]),
                                        TextInput::make('max_qty')
                                            ->label('Max quantity per order')
                                            ->numeric()->minValue(1)->maxValue(99)
                                            ->placeholder('No limit')
                                            ->visible(self::uses(Template::PRODUCT_FIELDS_ORDER_LIMIT))
                                            ->columnSpan(['lg' => 2]),
                                    ]),

                                // Step 1: the options (what the customer chooses).
                                Section::make('Step 1 · Options')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->description(fn () => self::optionsExample())
                                    ->secondary()
                                    ->compact()
                                    ->schema([
                                        Repeater::make('variant_attributes')
                                            ->hiddenLabel()
                                            ->live()
                                            ->addActionLabel('Add option')
                                            ->table([
                                                // Wide enough for the longest label, "Pack size (weight / volume)".
                                                TableColumn::make('Option')->markAsRequired()->width('17rem'),
                                                TableColumn::make('Values')->markAsRequired(),
                                            ])
                                            ->schema([
                                                Group::make([
                                                    // Pick a common option, or "Other…" to name your own. Only `name`
                                                    // is saved; the choice is worked out from it when the form opens.
                                                    Select::make('preset')
                                                        ->label('Option')
                                                        ->hiddenLabel()
                                                        ->options(fn () => self::optionPresets() + [self::OTHER_OPTION => 'Other…'])
                                                        ->placeholder('Choose')
                                                        // Filament's dropdown: a plain <select> lost its shown choice when the table redrew.
                                                        ->native(false)
                                                        ->required()
                                                        ->live()
                                                        // An option already added in another row cannot be chosen again;
                                                        // "Other…" stays open, and its typed name is checked below.
                                                        ->disableOptionWhen(fn (Select $component, string $value) => $value !== self::OTHER_OPTION && self::optionTakenInSiblingRow($component, $value))
                                                        ->rule(fn (Select $component, Get $get) => self::uniqueOptionRule($component, $get('preset') === self::OTHER_OPTION ? null : $get('name')))
                                                        ->dehydrated(false)
                                                        ->afterStateHydrated(fn (Select $component, Get $get) => $component->state(self::presetFor($get('name'))))
                                                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                                            $old = $get('name');
                                                            // "Other…" keeps the current name in the box to edit, so the
                                                            // combinations' values follow when it is renamed.
                                                            $new = $state === self::OTHER_OPTION ? $old : $state;
                                                            $set('name', $new);
                                                            // Switching option carries the combinations' chosen values over.
                                                            self::renameOptionInCombinations($get, $set, $old, $new);
                                                            // A row with no values yet starts with the option's common ones, to
                                                            // trim with × or add to; values already entered are never replaced.
                                                            if ($state !== self::OTHER_OPTION && self::swatchValues($get('values')) === []) {
                                                                $values = self::optionValues($state)['default'];
                                                                $set('values', $values);
                                                                self::fillNamedSwatches($get, $set, $state, $values);
                                                            }
                                                        }),
                                                    TextInput::make('name')
                                                        ->label('Option name')
                                                        ->hiddenLabel()
                                                        ->placeholder('e.g. Fragrance')
                                                        ->visible(fn (Get $get) => $get('preset') === self::OTHER_OPTION)
                                                        ->dehydratedWhenHidden()
                                                        ->required()
                                                        ->maxLength(40)
                                                        ->live(onBlur: true)
                                                        // A dot would split the name into a nested path.
                                                        ->notRegex('/\./')
                                                        ->validationMessages(['not_regex' => 'Option names cannot contain a full stop.'])
                                                        ->rule(fn (TextInput $component, ?string $state) => self::uniqueOptionRule($component, $state))
                                                        // Renaming an option carries the combinations' chosen values over to the new name.
                                                        ->afterStateUpdated(fn (Get $get, Set $set, ?string $old, ?string $state) => self::renameOptionInCombinations($get, $set, $old, $state)),
                                                ]),
                                                Group::make([
                                                    TagsInput::make('values')
                                                        ->label('Values')
                                                        ->hiddenLabel()
                                                        ->required()
                                                        ->live()
                                                        // Enter or a comma adds a value (as does leaving the box); pasting "S, M, L" adds three.
                                                        // Not Tab: Filament swallows it even when the box is empty, trapping keyboard users.
                                                        ->splitKeys([','])
                                                        // Click a chip to rename it (editable-tags script); the class turns that on.
                                                        // The counts let × warn before removing a value variants use.
                                                        ->extraAttributes(fn (Get $get) => [
                                                            'class' => 'fi-editable-tags',
                                                            // URL-encoded: Filament escapes quotes in attributes as \", which would cut the JSON short.
                                                            'data-value-uses' => rawurlencode(json_encode((object) self::valueUses($get('../../variants'), $get('name')))),
                                                        ])
                                                        ->afterStateUpdated(function (Get $get, Set $set, $old, $state) {
                                                            // A chip renamed in place keeps its swatch, photos and combinations.
                                                            if ($renamed = self::renamedValue($old, $state)) {
                                                                self::renameValueInOption($get, $set, $get('name'), ...$renamed);
                                                            } else {
                                                                // A removed value takes the combinations that chose it with it
                                                                // (the × asked first); otherwise they block the save.
                                                                self::dropCombinationsWithout($get, $set, $get('name'), $old, $state);
                                                            }
                                                            // A new colour value named like a colour ("Red", "Navy") starts with that swatch.
                                                            self::fillNamedSwatches($get, $set, $get('name'), $state);
                                                        })
                                                        // Common values for the chosen option, offered while typing; any
                                                        // other value can still be typed in.
                                                        ->suggestions(fn (Get $get) => self::optionValues(self::presetFor($get('name')))['all'])
                                                        ->reorderable()
                                                        ->placeholder('Type a value, press Enter. Click a value to rename it.'),
                                                    // A colour option gets one compact card per value: its swatch (written
                                                    // to swatches.<Value>, so the saved shape is still {Value: hex}; clear
                                                    // it to show the name instead) and its photos, picked from the Media
                                                    // tab or uploaded here, shown whatever else is chosen. A variant's own
                                                    // photos (step 2) override these. Values with a full stop are skipped
                                                    // (it would split the path).
                                                    // One short row per colour: [swatch] [photos to pick] [upload], two
                                                    // colours side by side on wide screens.
                                                    Grid::make(['default' => 1, 'xl' => 2])
                                                        ->extraAttributes(['class' => 'fi-option-photos fi-swatch-grid'])
                                                        ->visible(fn (Get $get) => Product::isColourAttribute($get('name')) && self::swatchValues($get('values')) !== [])
                                                        ->schema(fn (Get $get) => array_map(
                                                            fn (string $value) => Group::make([
                                                                ColorPicker::make('swatches.'.$value)->label($value.' swatch')->hiddenLabel()->prefix($value)->placeholder('No swatch')
                                                                    ->extraAttributes(['class' => 'fi-swatch-picker'])
                                                                    ->columnSpan(['lg' => 5])
                                                                    // A value named like a colour (or written as a colour code) shows
                                                                    // that colour, as the storefront does.
                                                                    ->default(ColourNames::hex($value))
                                                                    ->afterStateHydrated(fn (ColorPicker $component, $state) => blank($state) && ($hex = ColourNames::hex($value)) ? $component->state($hex) : null),
                                                                ImagePicker::make('images.'.$value)
                                                                    ->label($value.' photos')
                                                                    ->hiddenLabel()
                                                                    ->withoutInlineLabel()
                                                                    ->columnSpan(['lg' => 3])
                                                                    ->images(fn (Get $get) => (array) $get('../../images')),
                                                                // Uploaded here: on save they join the product's images and are
                                                                // picked for this colour (Product::assignColourPhotos).
                                                                Fields::image('uploads.'.$value, 'Upload '.$value.' photos', 'products', multiple: true)
                                                                    ->hiddenLabel()
                                                                    ->panelLayout('compact')
                                                                    ->imagePreviewHeight('40')
                                                                    // No custom placeholder: each new upload box resets FilePond's language
                                                                    // for every box on the page, so a custom one survives only on the last
                                                                    // box. theme.css shows only FilePond's own "Browse" link (.fi-variant-photos).
                                                                    ->extraAttributes(['class' => 'fi-variant-photos'])
                                                                    ->columnSpan(['lg' => 4]),
                                                            ])->columns(['default' => 1, 'lg' => 12])->extraAttributes(['class' => 'fi-colour-card']),
                                                            self::swatchValues($get('values')),
                                                        )),
                                                ]),
                                            ])
                                            ->reorderable()
                                            ->defaultItems(0),
                                    ]),

                                // Step 2: price, SKU and stock for each combination.
                                Section::make('Step 2 · Variants (price & stock for each)')
                                    ->icon('heroicon-o-squares-2x2')
                                    ->description(fn (Get $get) => $get('has_variants')
                                        ? 'On: only the variants listed below can be bought. A variant left out shows as unavailable.'
                                        : 'Off: every choice costs the product price and shares its stock (Price & stock above). Turn on when sizes or colours differ in price or stock.')
                                    ->secondary()
                                    ->compact()
                                    ->afterHeader([
                                        Toggle::make('has_variants')
                                            ->label('Sell as variant')
                                            ->live()
                                            ->default(false),
                                    ])
                                    ->schema([
                                        Text::make('Add at least one option in step 1 to list its variants.')
                                            ->color('gray')
                                            ->visible(fn (Get $get) => (bool) $get('has_variants') && self::definedAttributes($get, '') === []),
                                        // Ordered: the stock of the variants it already has is locked (a new one can still take its stock).
                                        Text::make(self::STOCK_LOCKED_HELP.' A variant you add here takes the stock you enter for it.')
                                            ->color('warning')
                                            ->icon('heroicon-m-lock-closed')
                                            ->visible(fn (Get $get, ?Product $record) => (bool) $get('has_variants') && self::stockLocked($record)),
                                        // One compact row per combination: a column for each option, then its
                                        // own SKU, price, stock, availability and photos.
                                        Repeater::make('variants')
                                            ->key('combinations')
                                            ->label('Variants')
                                            ->hint(fn (Get $get) => self::combinationSummary($get('variants'), $get('price')))
                                            ->hintAction(
                                                Action::make('generateCombinations')
                                                    ->label('Generate all variants')
                                                    ->icon('heroicon-o-squares-plus')
                                                    ->visible(fn (Get $get) => self::definedAttributes($get, '') !== [])
                                                    ->action(fn (Get $get, Set $set) => self::generateCombinations($get, $set)),
                                            )
                                            ->visible(fn (Get $get) => (bool) $get('has_variants') && self::definedAttributes($get, '') !== [])
                                            ->addActionLabel('Add one variant')
                                            ->table(fn (Get $get, ?Product $record) => [
                                                ...array_map(fn (array $attribute) => TableColumn::make($attribute['name'])->markAsRequired()->width('9rem'), self::definedAttributes($get, '')),
                                                TableColumn::make('SKU / Barcode')->width('11rem'),
                                                TableColumn::make('HSN code')->width('8rem'),
                                                TableColumn::make('MRP (₹)')->width('8rem'),
                                                TableColumn::make('Tax')->width('9rem'),
                                                TableColumn::make('Price incl. tax (₹)')->width('9rem'),
                                                TableColumn::make('Price excl. tax (₹)')->width('9rem'),
                                                TableColumn::make($record?->exists ? 'Current stock' : 'Initial stock')->markAsRequired()->width('7rem'),
                                                TableColumn::make('Available')->alignment('center')->width('6rem'),
                                            ])
                                            ->schema(fn (Get $get) => [
                                                // One dropdown per option defined above, listing its values.
                                                // Each writes to options.<Option>, so the saved shape is
                                                // still {Option: Value}.
                                                ...array_map(
                                                    fn (array $attribute) => Select::make('options.'.$attribute['name'])
                                                        ->label($attribute['name'])
                                                        ->options(array_combine($attribute['values'], $attribute['values']))
                                                        // Filament's dropdown, not a plain <select>: a plain one lost its shown
                                                        // choice when a live field redrew the table (as on the Details tab, which works).
                                                        ->native(false)
                                                        ->placeholder('Choose')
                                                        ->required()
                                                        ->validationMessages(['required' => 'Choose a '.$attribute['name'].' (a value may have been removed from the option above).']),
                                                    self::definedAttributes($get, ''),
                                                ),
                                                // Required when the shop syncs with its billing software: each variant
                                                // needs its own SKU there, so the product's is not offered as a stand-in.
                                                TextInput::make('sku')->label('SKU / Barcode')->maxLength(64)
                                                    ->required(fn () => self::syncsWithBillingSoftware())
                                                    ->placeholder(fn (Get $get) => self::syncsWithBillingSoftware() ? null : ($get('../../sku') ?: null)),
                                                // Only for a variant that is different goods (another material, say).
                                                // Blank uses the product's HSN code, shown greyed as the placeholder.
                                                self::hsnCodeField()
                                                    ->placeholder(fn (Get $get) => $get('../../hsn_code') ?: null),
                                                // The MRP, shown struck through when above the price. Blank uses the
                                                // product's MRP, shown greyed as the placeholder.
                                                TextInput::make('compare_at_price')->label('MRP')->numeric()->minValue(0)
                                                    ->placeholder(fn (Get $get) => filled($get('../../compare_at_price')) ? number_format((float) $get('../../compare_at_price')) : null)
                                                    ->rules([fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get) {
                                                        $incl = filled($get('price_incl_tax')) ? (float) $get('price_incl_tax') : (float) $get('../../price_incl_tax');
                                                        if (filled($value) && (float) $value < $incl) {
                                                            $fail('Set the MRP at or above this variant\'s price including tax ('.money($incl).'), or leave it blank.');
                                                        }
                                                    }]),
                                                // Read only: every variant takes the product's tax (Price & stock above).
                                                // Shown as the placeholder so it follows a change of tax; never saved.
                                                TextInput::make('tax_display')->label('Tax')
                                                    ->disabled()->dehydrated(false)
                                                    ->placeholder(fn (Get $get) => self::taxLabel($get('../../tax_id'))),
                                                // Both prices use the product's tax (Price & stock above). Blank
                                                // uses the product's price, shown greyed as the placeholder.
                                                self::variantPriceField(inclusive: true),
                                                self::variantPriceField(inclusive: false),
                                                // Required: a blank stock used to copy the product's whole stock to
                                                // every combination, so the store could sell many times what it had.
                                                TextInput::make('stock')->label(fn (?Product $record) => $record?->exists ? 'Current stock' : 'Initial stock')->numeric()->integer()->minValue(0)->required()
                                                    // Locked on a variant the product already had once it has been ordered;
                                                    // a variant added now takes its stock here. Still sent: EditProduct
                                                    // saves the stock as it is now in its place.
                                                    ->disabled(fn (Get $get, ?Product $record) => self::stockLocked($record) && self::isSavedVariant($record, (array) $get('options')))
                                                    ->dehydrated()
                                                    // Updates the read-only total in Price & stock.
                                                    ->live(onBlur: true)
                                                    ->validationMessages(['required' => 'Enter how many of this variant you have (0 if none).']),
                                                Toggle::make('is_active')->label('Available')->default(true),
                                                // Photos are chosen per colour (step 1), so the table has no photo column.
                                                // Photos a combination already has are kept as they are (hidden, saved
                                                // unchanged), and still shown when that combination is chosen.
                                                ImagePicker::make('images')
                                                    ->hidden()
                                                    ->dehydratedWhenHidden(),
                                                // The saved price, as for the product: the one the store's prices are
                                                // entered as. A Hidden field, not a hidden TextInput: the table counts
                                                // a hidden TextInput as a column, pushing Stock and Available out of line.
                                                Hidden::make('price'),
                                            ])
                                            ->reorderable()
                                            ->defaultItems(0),
                                    ]),

                                // Only for a product that still has an older list no option replaces
                                // (e.g. one display colour beside a Size option): the storefront shows
                                // it, so it stays editable here.
                                Section::make('Older colour & size lists')
                                    ->description('Shown on the storefront, but not a choice the variants use. To make it a choice, add it as an option in step 1 instead, then empty this list.')
                                    ->collapsed()
                                    ->visible(fn (?Product $record, Get $get) => self::hasUncoveredLegacyList($record, $get, 'colors') || self::hasUncoveredLegacyList($record, $get, 'sizes'))
                                    ->schema([
                                        Repeater::make('colors')
                                            ->label('Colours')
                                            ->visible(fn (?Product $record, Get $get) => self::hasUncoveredLegacyList($record, $get, 'colors'))
                                            ->schema([
                                                TextInput::make('name')->required()->maxLength(40),
                                                ColorPicker::make('hex')->label('Swatch'),
                                            ])
                                            ->columns(2)->reorderable()->defaultItems(0)->addActionLabel('Add colour')
                                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
                                        TagsInput::make('sizes')
                                            ->visible(fn (?Product $record, Get $get) => self::hasUncoveredLegacyList($record, $get, 'sizes'))
                                            ->reorderable(),
                                    ]),
                                self::tabButtons(create: true),
                            ]),

                        Tab::make('Grocery & nutrition')->badge(self::errorBadge('Grocery & nutrition'))->badgeColor('danger')
                            ->icon('heroicon-o-shopping-cart')
                            ->visible(self::uses(Template::PRODUCT_FIELDS_GROCERY, Template::PRODUCT_FIELDS_NUTRITION))
                            ->schema([
                                Section::make('Grocery details')
                                    ->visible(self::uses(Template::PRODUCT_FIELDS_GROCERY))
                                    ->description('Used by the Indian Grocery template. Pack sizes are a "Size" option on the Pricing & Variants tab (e.g. 500 g, 1 kg). Ignored by other templates.')
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
                                self::tabButtons(create: true),
                            ]),

                        Tab::make('Plumbing specifications')->badge(self::errorBadge('Plumbing specifications'))->badgeColor('danger')
                            ->icon('heroicon-o-wrench-screwdriver')
                            ->visible(self::uses(Template::PRODUCT_FIELDS_SPECIFICATIONS))
                            ->schema([
                                Section::make('Technical data')
                                    ->description('Used by the Plumbing template: the specification table, Applications and Installation tabs on the product page. Sizes (e.g. 1/2 inch, 1 inch) are a "Size" option on the Pricing & Variants tab; brand and material are on the Details tab.')
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
                                self::tabButtons(create: true),
                            ]),
                    ]),
            ]);
    }

    /**
     * The buttons at the foot of a tab while adding a product: "Next" on the tabs before Pricing &
     * Variants (it only moves on: nothing is checked or saved), "Create" from Pricing & Variants
     * on, which saves the whole form (CreateProduct has no Create button of its own). The tabs stay
     * free to click. Editing keeps its usual Save button and none of these.
     */
    private static function tabButtons(bool $create): Actions
    {
        $actions = $create
            ? [
                Action::make('createFromTab')->label('Create')->icon('heroicon-m-check')
                    ->alpineClickHandler('$wire.create()')->keyBindings(['mod+s']),
                Action::make('createAnotherFromTab')->label('Create & add another')->color('gray')
                    ->alpineClickHandler('$wire.createAnother()'),
            ]
            : [
                Action::make('nextTab')->label('Next')->icon('heroicon-m-arrow-right')->iconPosition(IconPosition::After)->color('primary')
                    // The tabs' own state: move to the tab after this one, and back to the top of the form.
                    ->alpineClickHandler("tab = getTabs()[getTabs().indexOf(tab) + 1] ?? tab; \$el.closest('.fi-sc-tabs')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"),
            ];

        return Actions::make($actions)->visibleOn('create')->alignEnd();
    }

    /** A tab's red badge: how many of the fields on it a refused save complained about (ExplainsMissingFields). */
    private static function errorBadge(string $tab): Closure
    {
        return fn ($livewire) => method_exists($livewire, 'errorCountOnTab') ? $livewire->errorCountOnTab($tab) : null;
    }

    /**
     * Visibility for a template-specific field group: shown when the product's template uses any
     * of the groups. A store's own admin always works in its template; a super admin follows the
     * "Visible in" picker, and a product shared by all templates shows every group.
     */
    /**
     * True when the product has an older colour or size list that no option in step 1
     * replaces, so it is still shown on the storefront as it is.
     */
    /**
     * The product's own place, one box per level: a category (Men), then one of its
     * sub-categories (Shirts), then a type under that if there is one. The deepest pick is the
     * main category (breadcrumb and card label).
     */
    public const CATEGORY_LEVELS = ['Category', 'Child category', 'Type', 'Sub-type'];

    /** The extra places, each any category or sub-category ("Winter Wear", "Women › Shirts"). */
    public const EXTRA_CATEGORIES = ['extra_category_1' => 'Category tag 1', 'extra_category_2' => 'Category tag 2'];

    /**
     * The whole category row: the level boxes, then the two extra places.
     *
     * @return list<Component>
     */
    private static function categoryFields(): array
    {
        $levels = count(self::CATEGORY_LEVELS);
        $fields = [];

        foreach (self::CATEGORY_LEVELS as $level => $label) {
            $field = Select::make('category_level_'.$level)
                ->label($label)
                ->searchable()
                ->live()
                ->dehydrated(false)
                ->afterStateHydrated(fn (Select $component, ?Product $record) => $component->state(
                    self::categoryPath($record?->category_id)[$level] ?? null,
                ))
                // A new pick clears the levels below it: they belonged to the old one.
                ->afterStateUpdated(function (Set $set) use ($level, $levels) {
                    for ($below = $level + 1; $below < $levels; $below++) {
                        $set('category_level_'.$below, null);
                    }
                });

            $fields[] = $level === 0
                ? $field->options(fn () => self::treeOptions())->required()->validationAttribute('category')->placeholder('Pick a category')
                : $field->options(fn (Get $get) => self::childOptions(self::pickedPath($get)[$level - 1] ?? 0))
                    // Child category always shows, so the row keeps its four sections; a Type box only
                    // when the child category picked has categories under it.
                    ->visible(fn (Get $get) => $level === 1 || self::shownCategoryLevels($get) > $level)
                    ->disabled(fn (Get $get) => self::childOptions(self::pickedPath($get)[$level - 1] ?? 0) === [])
                    ->placeholder(fn (Get $get) => match (true) {
                        ! isset(self::pickedPath($get)[$level - 1]) => 'Pick a category first',
                        self::childOptions(self::pickedPath($get)[$level - 1]) === [] => 'No child categories',
                        default => 'Optional',
                    });
        }

        $fields[] = Hidden::make('category_id')->dehydrateStateUsing(fn (Get $get) => self::pickedCategoryId($get));

        foreach (self::EXTRA_CATEGORIES as $name => $label) {
            $other = $name === 'extra_category_1' ? 'extra_category_2' : 'extra_category_1';
            $fields[] = Select::make($name)
                ->label($label)
                ->searchable()
                ->live()
                ->dehydrated(false)
                ->placeholder('Optional')
                // Each option shows the categories above it on the right ("New Shirts   Men › Shirts"),
                // so two of the same name are told apart.
                ->allowHtml()
                // Any category or sub-category, but not one already picked in the boxes before it.
                ->options(fn (Get $get) => self::allCategoryOptions([...self::pickedPath($get), (int) $get($other)]))
                ->afterStateHydrated(fn (Select $component, ?Product $record) => $component->state(
                    self::otherCategories($record)[$name === 'extra_category_1' ? 0 : 1] ?? null,
                ));
        }

        // A product listed in more extra places than there are boxes keeps the rest.
        // It also saves the whole row: the level and extra boxes are not columns of the product.
        $fields[] = Hidden::make('extra_categories_more')
            ->dehydrated(false)
            ->afterStateHydrated(fn (Hidden $component, ?Product $record) => $component->state(array_slice(self::otherCategories($record), 2)))
            ->saveRelationshipsUsing(fn (Product $record, Get $get) => self::saveCategories($record, $get));

        return $fields;
    }

    /** Saves every category the product is listed under: its main one and the extra places. */
    private static function saveCategories(Product $record, Get $get): void
    {
        $record->listUnder(
            [
                ...array_map('intval', array_filter([$get('extra_category_1'), $get('extra_category_2')], 'filled')),
                ...array_map('intval', (array) $get('extra_categories_more')),
            ],
            self::pickedCategoryId($get),
        );
    }

    /** The level boxes on show: the first, then one more for each pick that has categories under it. */
    private static function shownCategoryLevels(Get $get): int
    {
        $path = self::pickedPath($get);
        // The child category box is always on show, with or without a pick before it.
        $shown = 2;
        while ($shown < count(self::CATEGORY_LEVELS) && isset($path[$shown - 1]) && self::childOptions($path[$shown - 1]) !== []) {
            $shown++;
        }

        return $shown;
    }

    /**
     * The levels picked, from the top, stopping at the first gap or at a pick that is not under
     * the one before it (left over from an earlier pick).
     *
     * @return list<int>
     */
    private static function pickedPath(Get $get): array
    {
        $path = [];
        foreach (array_keys(self::CATEGORY_LEVELS) as $level) {
            $id = (int) $get('category_level_'.$level);
            if (! $id) {
                break;
            }
            $parentOk = $level === 0
                // Any category can be the first pick, a sub-category included ("shirts").
                ? Category::query()->whereKey($id)->exists()
                : Category::query()->whereKey($id)->where('parent_id', $path[$level - 1])->exists();
            if (! $parentOk) {
                break;
            }
            $path[] = $id;
        }

        return $path;
    }

    /** The main category: the deepest level picked. */
    private static function pickedCategoryId(Get $get): ?int
    {
        $path = self::pickedPath($get);

        return $path === [] ? null : end($path);
    }

    /**
     * Every category by its own name, in tree order: each one straight after its parent
     * (Men, Shirts, T-shirts & Polos, shirts, Women, …), so two "Shirts" are told apart by
     * where they sit.
     *
     * @return array<int, string>
     */
    private static function treeOptions(): array
    {
        $byParent = Category::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'parent_id'])->groupBy(fn (Category $c) => (int) $c->parent_id);
        $options = [];
        $add = function (int $parentId) use (&$add, &$options, $byParent) {
            foreach ($byParent[$parentId] ?? [] as $category) {
                // Seen already: the data holds a loop, so stop there.
                if (! isset($options[$category->getKey()])) {
                    $options[$category->getKey()] = $category->name;
                    $add((int) $category->getKey());
                }
            }
        };
        $add(0);

        return $options;
    }

    /** @return array<int, string> the categories directly under this one (top level for null) */
    private static function childOptions(?int $parentId): array
    {
        return Category::query()
            ->when($parentId === null, fn (Builder $q) => $q->whereNull('parent_id'), fn (Builder $q) => $q->where('parent_id', $parentId))
            ->orderBy('sort_order')->orderBy('name')
            ->pluck('name', 'id')->all();
    }

    /**
     * Every category by its own name ("Shirts"), in alphabetical order, with the categories above
     * it shown muted on the right ("Men › T-shirts & Polos"). The labels are HTML (the select
     * allows it), so names are escaped.
     *
     * @param  list<int>  $except
     * @return array<int, string>
     */
    private static function allCategoryOptions(array $except = []): array
    {
        $all = Category::query()->get(['id', 'name', 'parent_id'])->keyBy('id')->toBase(); // a base collection: an Eloquent one's except() drops the id keys

        return $all->except(array_filter($except))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(function (Category $category) use ($all) {
                $above = [];
                $seen = [$category->id];
                for ($at = $all->get($category->parent_id); $at && ! in_array($at->id, $seen, true); $at = $all->get($at->parent_id)) {
                    array_unshift($above, $at->name);
                    $seen[] = $at->id;
                }

                return Fields::categoryOption($category->name, $above);
            })
            ->all();
    }

    /**
     * The categories a saved product is listed under besides its main one and the categories
     * above that: what the extra boxes hold.
     *
     * @return list<int>
     */
    private static function otherCategories(?Product $record): array
    {
        if (! $record?->exists) {
            return [];
        }
        $path = self::categoryPath($record->category_id);

        return $record->categories()->orderBy('category_product.category_id')->pluck('categories.id')
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => in_array($id, $path, true))
            ->values()->all();
    }

    /** @return list<int> the ids from the top-level category down to this one */
    private static function categoryPath(mixed $categoryId): array
    {
        $path = [];
        $category = filled($categoryId) ? Category::query()->find($categoryId) : null;
        while ($category && ! in_array($category->getKey(), $path, true)) {
            array_unshift($path, (int) $category->getKey());
            $category = $category->parent;
        }

        return $path;
    }

    /** Does the store this product is for order by code (Template::requiresSku())? */
    private static function requiresSku(Get $get): bool
    {
        $id = app(StoreContext::class)->template() ?? $get('template');
        $templates = app(TemplateManager::class);

        return filled($id) && $templates->has($id) && $templates->get($id)->requiresSku();
    }

    private static function hasUncoveredLegacyList(?Product $record, Get $get, string $list): bool
    {
        if (! $record || empty($record->{$list})) {
            return false;
        }
        $isOption = $list === 'colors' ? Product::isColourAttribute(...) : Product::isSizeAttribute(...);
        foreach ((array) $get('variant_attributes') as $attribute) {
            if ($isOption($attribute['name'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * "Price incl. tax" or "Price excl. tax". Typing one fills in the other from the tax rate and
     * sets `price` (the price the cart charges) to the one the store's prices are entered as.
     * Both are saved (products.price_incl_tax / price_excl_tax).
     */
    private static function priceField(bool $inclusive): TextInput
    {
        $name = $inclusive ? 'price_incl_tax' : 'price_excl_tax';

        return TextInput::make($name)
            ->label(fn (Get $get) => ($get('has_variants') ? 'Default price ' : 'Price ').($inclusive ? 'incl. tax' : 'excl. tax'))
            ->numeric()->prefix('₹')->minValue(0)->required()
            ->live(onBlur: true)
            // Shows the saved price; a product saved before it had one gets it worked out from `price`.
            ->afterStateHydrated(function (TextInput $component, Get $get) use ($name) {
                if (filled($component->getState())) {
                    return;
                }
                $component->state(TaxPrices::for($get('price'), self::selectedTaxRate($get('tax_id')), prices_include_tax())[$name]);
            })
            ->afterStateUpdated(fn (Get $get, Set $set) => self::syncPrices($get, $set, $inclusive))
            ->helperText(fn (Get $get) => $get('has_variants') && $inclusive === prices_include_tax()
                ? 'For variants without their own.'
                : null);
    }

    /**
     * Fills in the other price from the one in `$fromInclusive` (true: incl. tax) and sets the
     * saved `price` to the one the store's prices are entered as.
     */
    private static function syncPrices(Get $get, Set $set, bool $fromInclusive): void
    {
        $source = $get($fromInclusive ? 'price_incl_tax' : 'price_excl_tax');
        $target = $fromInclusive ? 'price_excl_tax' : 'price_incl_tax';
        if (blank($source) || ! is_numeric($source)) {
            $set($target, null);
            $set('price', null);

            return;
        }

        $source = round((float) $source, 2);
        $other = TaxPrices::convert($source, self::selectedTaxRate($get('tax_id')), toInclusive: ! $fromInclusive);
        $set($target, $other);
        $set('price', $fromInclusive === prices_include_tax() ? $source : $other);
    }

    /**
     * A variant's "Price incl. tax" or "Price excl. tax", worked like the product's (priceField)
     * at the variant's own tax rate, else the product's. Both are saved in the variant's row.
     * Both blank: the variant uses the product's price.
     */
    private static function variantPriceField(bool $inclusive): TextInput
    {
        $name = $inclusive ? 'price_incl_tax' : 'price_excl_tax';

        return TextInput::make($name)
            ->label($inclusive ? 'Price incl. tax' : 'Price excl. tax')
            ->numeric()->minValue(0)
            ->live(onBlur: true)
            ->placeholder(fn (Get $get) => filled($get('../../'.$name)) ? number_format((float) $get('../../'.$name), 2) : null)
            ->afterStateHydrated(function (TextInput $component, Get $get) use ($name) {
                if (filled($component->getState())) {
                    return;
                }
                $row = self::variantRowsWithPrices([['price' => $get('price')]], self::selectedTaxRate($get('../../tax_id')))[0];
                $component->state($row[$name]);
            })
            ->afterStateUpdated(function (Get $get, Set $set) use ($inclusive) {
                $source = $get($inclusive ? 'price_incl_tax' : 'price_excl_tax');
                $target = $inclusive ? 'price_excl_tax' : 'price_incl_tax';
                if (blank($source) || ! is_numeric($source)) {
                    $set($target, null);
                    $set('price', null);

                    return;
                }
                $source = round((float) $source, 2);
                $other = TaxPrices::convert($source, self::selectedTaxRate($get('../../tax_id')), toInclusive: ! $inclusive);
                $set($target, $other);
                $set('price', $inclusive === prices_include_tax() ? $source : $other);
            });
    }

    /**
     * Variant rows with both their shown prices worked out from the saved `price` (blank when
     * the variant uses the product's price), at the product's tax rate.
     *
     * @param  array<array-key, mixed>  $rows
     * @return array<array-key, mixed>
     */
    private static function variantRowsWithPrices(array $rows, float $rate): array
    {
        return array_map(
            fn ($row) => is_array($row) ? array_merge($row, TaxPrices::for($row['price'] ?? null, $rate, prices_include_tax())) : $row,
            $rows,
        );
    }

    /** "GST 18%" for the chosen tax, as listed in the Tax dropdown; "No tax (0%)" when none is chosen. */
    private static function taxLabel(mixed $taxId): string
    {
        $tax = blank($taxId) ? null : Tax::query()->withTrashed()->find($taxId);

        return $tax?->option_label ?? 'No tax (0%)';
    }

    /** The rate of the chosen tax; none chosen is 0%. */
    private static function selectedTaxRate(mixed $taxId): float
    {
        if (blank($taxId)) {
            return 0.0;
        }

        return (float) (Tax::query()->withTrashed()->whereKey($taxId)->value('rate') ?? 0);
    }

    /** An HSN code: 4, 6 or 8 digits, spaces dropped, blank saved as null. */
    private static function hsnCodeField(): TextInput
    {
        return TextInput::make('hsn_code')->label('HSN code')
            ->maxLength(8)
            ->regex('/^\d{4}(\d{2}){0,2}$/')
            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? preg_replace('/\s+/', '', $state) : null)
            ->validationMessages(['regex' => 'Enter 4, 6 or 8 digits, e.g. 6109 or 61091000.']);
    }

    /** "6 variants · 24 in stock · ₹1,299–₹1,499" for the Variants header. */
    private static function combinationSummary(mixed $variants, mixed $productPrice): ?string
    {
        $variants = array_values((array) $variants);
        if ($variants === []) {
            return null;
        }
        $stock = array_sum(array_map(fn ($v) => (int) ($v['stock'] ?? 0), $variants));
        $prices = array_map(fn ($v) => (float) (filled($v['price'] ?? null) ? $v['price'] : $productPrice), $variants);
        $min = min($prices);
        $max = max($prices);
        $price = $min === $max ? '₹'.number_format($min) : '₹'.number_format($min).'–₹'.number_format($max);

        return count($variants).' '.Str::plural('variant', count($variants)).' · '.$stock.' in stock · '.$price;
    }

    /**
     * Options offered in the Option dropdown for each store's template (stored name => label).
     *
     * "Size" keeps its name wherever it means pack size or pipe size, because every template's
     * own size picker reads it; only the label changes. Material is not offered: a product's
     * material is the "Primary material" on the Details tab (it drives the shop's Material
     * filter), and the rare product sold in several materials can still use "Other…".
     */
    private const OPTION_PRESETS_BY_TEMPLATE = [
        'fashion' => ['Size' => 'Size', 'Colour' => 'Colour', 'Style' => 'Style / fit', 'Pack size' => 'Pack size'],
        'grocery' => ['Size' => 'Pack size (weight / volume)', 'Flavour' => 'Flavour', 'Pack size' => 'Pack of (count)'],
        'heritage' => ['Size' => 'Pack size (weight / volume)', 'Flavour' => 'Flavour', 'Pack size' => 'Pack of (count)'],
        'plumbing' => ['Size' => 'Size (inch / mm)', 'Finish' => 'Finish', 'Colour' => 'Colour', 'Length' => 'Length', 'Pack size' => 'Pack of (count)'],
        'bulk' => ['Size' => 'Size / pack', 'Colour' => 'Colour', 'Finish' => 'Finish', 'Pack size' => 'Pack of (count)'],
    ];

    /** Every option, for a super admin working outside any one store. */
    private const ALL_OPTION_PRESETS = [
        'Size' => 'Size', 'Colour' => 'Colour', 'Style' => 'Style / fit', 'Pack size' => 'Pack size',
        'Weight' => 'Weight', 'Flavour' => 'Flavour', 'Finish' => 'Finish', 'Length' => 'Length',
    ];

    /** The example under "Step 1 · Options", in the store's own terms. */
    private const OPTIONS_EXAMPLE_BY_TEMPLATE = [
        'fashion' => 'What the customer picks, e.g. Size: S, M, L · Colour: Red, Navy',
        'grocery' => 'What the customer picks, e.g. Pack size: 250 g, 500 g, 1 kg · Flavour: Masala',
        'heritage' => 'What the customer picks, e.g. Pack size: 250 g, 500 g, 1 kg · Flavour: Kesar',
        'plumbing' => 'What the customer picks, e.g. Size: 1/2 inch, 1 inch · Finish: Chrome, Matte',
        'bulk' => 'What the customer picks, e.g. Size / pack: 500 ml, 1 L, 5 L · Colour: Blue',
    ];

    private static function optionsExample(): string
    {
        return self::OPTIONS_EXAMPLE_BY_TEMPLATE[(string) app(StoreContext::class)->template()]
            ?? 'What the customer picks, e.g. Size: S, M, L · Colour: Red, Navy';
    }

    /** The Option dropdown's choices for the store being edited (stored name => label). */
    public static function optionPresets(): array
    {
        return self::OPTION_PRESETS_BY_TEMPLATE[(string) app(StoreContext::class)->template()] ?? self::ALL_OPTION_PRESETS;
    }

    /**
     * Common values per option: `default` fills a new row when the option is chosen, `more` is
     * only suggested while typing. Size, Weight and Pack size depend on what the store sells.
     */
    private const OPTION_VALUES = [
        'Size' => ['default' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'], 'more' => ['XXXL', 'Free size', '28', '30', '32', '34', '36', '38', '40', '42']],
        'Colour' => ['default' => ['Black', 'White', 'Red', 'Blue'], 'more' => ['Navy', 'Grey', 'Green', 'Yellow', 'Pink', 'Maroon', 'Beige', 'Brown', 'Orange', 'Purple', 'Gold', 'Silver', 'Multicolour']],
        'Style' => ['default' => ['Classic', 'Modern'], 'more' => ['Regular fit', 'Slim fit', 'Relaxed fit', 'Traditional', 'Casual', 'Formal']],
        'Pack size' => ['default' => ['Single', 'Pack of 2', 'Pack of 5'], 'more' => ['Pack of 3', 'Pack of 4', 'Pack of 6', 'Pack of 10', 'Pack of 12', 'Pack of 24', 'Box of 50', 'Box of 100']],
        'Weight' => ['default' => ['250 g', '500 g', '1 kg'], 'more' => ['50 g', '100 g', '200 g', '2 kg', '5 kg', '10 kg', '25 kg']],
        'Flavour' => ['default' => ['Original'], 'more' => ['Masala', 'Classic salted', 'Chocolate', 'Vanilla', 'Strawberry', 'Mango', 'Elaichi', 'Kesar', 'Mint', 'Lemon']],
        'Finish' => ['default' => ['Matte', 'Glossy'], 'more' => ['Satin', 'Chrome', 'Brushed', 'Polished', 'Antique', 'Natural']],
        'Length' => ['default' => ['1 m', '2 m', '3 m'], 'more' => ['0.5 m', '5 m', '6 m', '10 m', '15 m', '30 m', '50 m']],
    ];

    /** Size values by template, where the store's sizes are not clothing sizes. */
    private const SIZE_VALUES_BY_TEMPLATE = [
        'grocery' => ['default' => ['250 g', '500 g', '1 kg'], 'more' => ['100 g', '200 g', '2 kg', '5 kg', '200 ml', '500 ml', '1 L', '2 L', '5 L']],
        'heritage' => ['default' => ['250 g', '500 g', '1 kg'], 'more' => ['100 g', '200 g', '2 kg', '5 kg', '250 ml', '500 ml', '1 L']],
        'plumbing' => ['default' => ['1/2 inch', '3/4 inch', '1 inch'], 'more' => ['1/4 inch', '1.25 inch', '1.5 inch', '2 inch', '3 inch', '4 inch', '15 mm', '20 mm', '25 mm', '32 mm', '40 mm', '50 mm']],
        'bulk' => ['default' => ['500 ml', '1 L', '5 L'], 'more' => ['250 ml', '2 L', '20 L', '50 L', '1 kg', '5 kg', '25 kg', 'Pack of 10', 'Box of 100']],
    ];

    /**
     * Default and suggested values for an option preset, for the store's template.
     *
     * @return array{default: array<int,string>, all: array<int,string>}
     */
    private static function optionValues(?string $preset): array
    {
        $set = self::OPTION_VALUES[$preset] ?? null;
        if ($preset === 'Size' && ($byTemplate = self::SIZE_VALUES_BY_TEMPLATE[(string) app(StoreContext::class)->template()] ?? null)) {
            $set = $byTemplate;
        }
        if (! $set) {
            return ['default' => [], 'all' => []];
        }

        return ['default' => $set['default'], 'all' => array_values(array_unique([...$set['default'], ...$set['more']]))];
    }

    /** Gives colour values named like a colour ("Red", "Navy") that swatch, unless one is set. */
    private static function fillNamedSwatches(Get $get, Set $set, mixed $optionName, mixed $values): void
    {
        if (! Product::isColourAttribute($optionName)) {
            return;
        }
        foreach (self::swatchValues($values) as $value) {
            if (blank($get('swatches.'.$value)) && ($hex = ColourNames::hex($value))) {
                $set('swatches.'.$value, $hex);
            }
        }
    }

    /** The dropdown choice that shows a free-text box for the option's name. */
    public const OTHER_OPTION = '__other';

    /**
     * The dropdown choice for a saved option name: its preset (matched without regard to case,
     * and Color as Colour), "Other…" for any other name, or nothing for a new row.
     */
    /** Option names entered in the other rows of the options repeater (not the component's own row). */
    private static function siblingOptionNames(Component $component): array
    {
        $repeater = $component->getParentRepeater();
        if (! $repeater) {
            return [];
        }
        $ownKey = (string) str($component->getStatePath())->after($repeater->getStatePath().'.')->before('.');

        return collect($repeater->getRawState() ?? [])
            ->reject(fn ($row, $key) => (string) $key === $ownKey)
            ->map(fn ($row) => trim((string) ($row['name'] ?? '')))
            ->filter()
            ->values()
            ->all();
    }

    /** Whether another row already uses this option (Colour and Color count as the same). */
    private static function optionTakenInSiblingRow(Component $component, ?string $name): bool
    {
        $name = trim((string) $name);

        return $name !== '' && collect(self::siblingOptionNames($component))->contains(fn (string $other) => Product::sameAttribute($other, $name));
    }

    /** Fails when the same option is added twice, so each option appears once on the product. */
    private static function uniqueOptionRule(Component $component, ?string $name): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($component, $name) {
            if (self::optionTakenInSiblingRow($component, $name)) {
                $fail('"'.trim((string) $name).'" is already added as an option. Add its values to that row instead.');
            }
        };
    }

    private static function presetFor(mixed $name): ?string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }
        foreach (array_keys(self::optionPresets()) as $preset) {
            if (Product::sameAttribute($name, $preset)) {
                return $preset;
            }
        }

        return self::OTHER_OPTION;
    }

    /** Most variants "Generate all variants" will create at once. */
    public const MAX_GENERATED_COMBINATIONS = 200;

    /**
     * The options defined on the form, cleaned: [{name, values}]. $prefix is the path from the
     * calling component back to the form root ("../../" from inside a Combinations item).
     *
     * @return array<int, array{name:string, values:array<int,string>}>
     */
    private static function definedAttributes(Get $get, string $prefix = '../../'): array
    {
        $attributes = [];
        foreach ((array) $get($prefix.'variant_attributes') as $attribute) {
            $name = trim((string) ($attribute['name'] ?? ''));
            $values = array_values(array_unique(array_filter(array_map(fn ($v) => trim((string) $v), (array) ($attribute['values'] ?? [])), 'strlen')));
            if ($name !== '' && ! str_contains($name, '.') && $values) {
                $attributes[$name] = ['name' => $name, 'values' => $values];
            }
        }

        return array_values($attributes);
    }

    /**
     * Lists every combination of the option values, in option order (all of the first value,
     * then the next). Combinations already listed keep their price, SKU, stock and photos.
     *
     * A row listed before an option was added (e.g. "750 ml" before Colour and Finish) is split
     * into its combinations: each keeps the row's price, availability and photos, and gets its
     * SKU with the new values added. Stock cannot be split, so the new rows start at 0. Rows
     * that match no combination (a value was removed above) are kept at the end, untouched.
     */
    private static function generateCombinations(Get $get, Set $set): void
    {
        $attributes = self::definedAttributes($get, '');
        $combinations = [[]];
        foreach ($attributes as $attribute) {
            $next = [];
            foreach ($combinations as $options) {
                foreach ($attribute['values'] as $value) {
                    $next[] = $options + [$attribute['name'] => $value];
                }
            }
            $combinations = $next;
            if (count($combinations) > self::MAX_GENERATED_COMBINATIONS) {
                Notification::make()->warning()->title('Too many variants')
                    ->body('These options make more than '.self::MAX_GENERATED_COMBINATIONS.' variants. Add the ones you sell one at a time instead.')
                    ->send();

                return;
            }
        }

        $existing = (array) $get('variants');
        $byKey = [];
        foreach ($existing as $id => $variant) {
            $byKey[self::combinationKey((array) ($variant['options'] ?? []))] ??= $id;
        }

        // Rows missing a value for some option, whose chosen values all still exist: these are
        // the ones listed before an option was added, and are split into their combinations.
        $partial = [];
        foreach ($existing as $id => $variant) {
            $chosen = self::chosenOptions((array) ($variant['options'] ?? []));
            $complete = count($chosen) === count($attributes);
            $valid = collect($chosen)->every(fn ($value, $name) => in_array($value, collect($attributes)->firstWhere('name', $name)['values'] ?? [], true));
            if (! $complete && $valid) {
                $partial[$id] = $chosen;
            }
        }

        $rows = [];
        $added = 0;
        $split = [];
        foreach ($combinations as $options) {
            $key = self::combinationKey($options);
            // The most specific earlier row this combination belongs to, if any.
            $source = collect($partial)
                ->filter(fn (array $chosen) => array_intersect_assoc($chosen, $options) === $chosen)
                ->sortByDesc(fn (array $chosen) => count($chosen))
                ->keys()->first();
            $from = $source !== null ? $existing[$source] : [];
            $source !== null && $split[$source] = true;
            $sku = filled($from['sku'] ?? null) ? self::extendSku($from['sku'], array_diff_key($options, $partial[$source])) : null;

            if (isset($byKey[$key]) && ! isset($partial[$byKey[$key]])) {
                // Already listed: kept as it is, with only its blanks filled from the earlier row
                // (when generate ran once before that row was split).
                $row = $existing[$byKey[$key]];
                blank($row['sku'] ?? null) && $row['sku'] = $sku;
                blank($row['price'] ?? null) && $row['price'] = $from['price'] ?? null;
                blank($row['compare_at_price'] ?? null) && $row['compare_at_price'] = $from['compare_at_price'] ?? null;
                empty($row['images']) && $row['images'] = $from['images'] ?? [];
                $rows[$byKey[$key]] = $row;

                continue;
            }

            $rows[(string) Str::uuid()] = [
                'options' => $options,
                'sku' => $sku,
                'price' => $from['price'] ?? null,
                'compare_at_price' => $from['compare_at_price'] ?? null,
                'stock' => 0,
                'is_active' => $from['is_active'] ?? true,
                'images' => $from['images'] ?? [],
            ];
            $added++;
        }

        // Anything else (a value removed above, or a row split here only partly) stays at the end.
        foreach ($existing as $id => $variant) {
            if (! isset($rows[$id]) && ! isset($split[$id])) {
                $rows[$id] = $variant;
            }
        }
        $set('variants', self::variantRowsWithPrices($rows, self::selectedTaxRate($get('tax_id'))));

        $splitNames = collect(array_keys($split))->map(fn ($id) => implode(' / ', $partial[$id]))->filter()->all();
        $body = $added ? 'New variants start with 0 in stock. Set the stock (and a price or SKU if it differs) for each one.' : null;
        if ($splitNames) {
            $body = Arr::join($splitNames, ', ', ' and ').' '.(count($splitNames) === 1 ? 'was' : 'were').' split into one row per variant, keeping '
                .(count($splitNames) === 1 ? 'its' : 'their').' prices, SKU and photos. Set the stock for each new row.';
        }

        Notification::make()->success()
            ->title($added ? "Added {$added} ".Str::plural('variant', $added) : 'Every variant is already listed')
            ->body($body)
            ->send();
    }

    /** A combination's chosen values, blanks left out: [Option => Value]. */
    private static function chosenOptions(array $options): array
    {
        return array_filter(array_map(fn ($v) => trim((string) $v), $options), 'strlen');
    }

    /** "PK-CT-750" with Black and Matte added: "PK-CT-750-BLACK-MATTE". */
    private static function extendSku(string $sku, array $values): string
    {
        $parts = array_map(fn ($v) => strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', (string) $v)), $values);

        return mb_substr(implode('-', array_filter([trim($sku), ...$parts], 'strlen')), 0, 64);
    }

    /** Order-independent identity of a combination's chosen values. */
    private static function combinationKey(array $options): string
    {
        $options = array_filter(array_map(fn ($v) => trim((string) $v), $options), 'strlen');
        ksort($options);

        return (string) json_encode($options);
    }

    /** Renaming an option moves each combination's value for it to the new name. */
    private static function renameOptionInCombinations(Get $get, Set $set, ?string $old, ?string $new): void
    {
        $old = trim((string) $old);
        $new = trim((string) $new);
        if ($old === '' || $new === '' || $old === $new || str_contains($new, '.')) {
            return;
        }

        $variants = (array) $get('../../variants');
        foreach ($variants as $key => $variant) {
            $options = (array) ($variant['options'] ?? []);
            if (array_key_exists($old, $options) && ! array_key_exists($new, $options)) {
                $renamed = [];
                foreach ($options as $name => $value) {
                    $renamed[$name === $old ? $new : $name] = $value;
                }
                $variants[$key]['options'] = $renamed;
            }
        }
        $set('../../variants', $variants);
    }

    /**
     * The value renamed by an edit of the values list, as [old, new]: the list kept its length and
     * order and exactly one entry changed text (a chip edited in place). Null for any other edit.
     *
     * @return array{0: string, 1: string}|null
     */
    private static function renamedValue(mixed $old, mixed $new): ?array
    {
        $old = array_values(array_map(fn ($v) => trim((string) $v), (array) $old));
        $new = array_values(array_map(fn ($v) => trim((string) $v), (array) $new));
        if ($old === [] || count($old) !== count($new)) {
            return null;
        }
        $changed = array_keys(array_diff_assoc($old, $new));
        if (count($changed) !== 1) {
            return null;
        }
        [$from, $to] = [$old[$changed[0]], $new[$changed[0]]];
        if ($from === '' || $to === '' || in_array($from, $new, true)) {
            return null;
        }

        return [$from, $to];
    }

    /**
     * Renaming a value keeps what hangs off it: its swatch, the photos picked or uploaded for it,
     * and the combinations that chose it, all move to the new name.
     */
    private static function renameValueInOption(Get $get, Set $set, mixed $optionName, string $from, string $to): void
    {
        $optionName = trim((string) $optionName);
        // A full stop would split the swatch/photo path; such values have no swatch or photos.
        if (! str_contains($from, '.') && ! str_contains($to, '.')) {
            foreach (['swatches', 'images', 'uploads'] as $key) {
                $map = (array) $get($key);
                if (! array_key_exists($from, $map)) {
                    continue;
                }
                $renamed = [];
                foreach ($map as $value => $entry) {
                    $renamed[$value === $from ? $to : $value] = $entry;
                }
                $set($key, $renamed);
            }
        }

        if ($optionName === '') {
            return;
        }
        $variants = (array) $get('../../variants');
        foreach ($variants as $key => $variant) {
            if (trim((string) ($variant['options'][$optionName] ?? '')) === $from) {
                $variants[$key]['options'][$optionName] = $to;
            }
        }
        $set('../../variants', $variants);
    }

    /**
     * How many combinations chose each value of an option, for the × warning.
     *
     * @return array<string, int>
     */
    private static function valueUses(mixed $variants, mixed $optionName): array
    {
        $optionName = trim((string) $optionName);
        $uses = [];
        if ($optionName === '') {
            return $uses;
        }
        foreach ((array) $variants as $variant) {
            $value = trim((string) ($variant['options'][$optionName] ?? ''));
            if ($value !== '') {
                $uses[$value] = ($uses[$value] ?? 0) + 1;
            }
        }

        return $uses;
    }

    /** Removes the combinations that chose a value no longer in the option's list. */
    private static function dropCombinationsWithout(Get $get, Set $set, mixed $optionName, mixed $old, mixed $new): void
    {
        $optionName = trim((string) $optionName);
        $removed = array_diff(
            array_map(fn ($v) => trim((string) $v), (array) $old),
            array_map(fn ($v) => trim((string) $v), (array) $new),
        );
        if ($optionName === '' || $removed === []) {
            return;
        }
        $variants = (array) $get('../../variants');
        $kept = array_filter(
            $variants,
            fn ($variant) => ! in_array(trim((string) ($variant['options'][$optionName] ?? '')), $removed, true),
        );
        if (count($kept) !== count($variants)) {
            $set('../../variants', $kept);
        }
    }

    /**
     * Rules for the gallery, given the whole form state. The product pages add them to their own
     * validation (ValidatesProductImages), so they see every image at once: the gallery, photos
     * uploaded under a colour (which join it on save) and the combinations' photos.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, object>
     */
    public static function imageRules(?Product $record, array $data): array
    {
        $colourUploads = self::formColourUploads($data['variant_attributes'] ?? []);

        return [
            new WithinClientImageLimit(self::imagePaths($record), [...self::formVariantImages($data['variants'] ?? []), ...$colourUploads]),
            new WithinProductImageCap($colourUploads),
        ];
    }

    /** Photos being uploaded under a colour (Variants → Colour): new images, counted with the rest. */
    private static function formColourUploads(mixed $attributes): array
    {
        $files = [];
        foreach ((array) $attributes as $attribute) {
            foreach ((array) ($attribute['uploads'] ?? []) as $paths) {
                foreach ((array) $paths as $file) {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    /** Total stock of the combinations that can be bought; what the product's own stock is saved as. */
    public const STOCK_LOCKED_HELP = 'This product has been ordered, so its stock can no longer be changed here. Use Inventory → Stock levels → Adjust stock: it keeps the reason for every change (stock received, return, damage, stock count).';

    /** Per record object, like $ordered. */
    private static ?\WeakMap $opening = null;

    /**
     * What the product started with: its "Opening stock" lines in the stock history (one per
     * variant, added up). Null for a new product, or one added before the stock history began.
     */
    public static function openingStock(?Product $record): ?int
    {
        if (! $record?->exists) {
            return null;
        }
        self::$opening ??= new \WeakMap;

        return self::$opening[$record] ??= (function () use ($record) {
            $lines = StockMovement::query()->where('product_id', $record->getKey())->where('reason', StockMovement::OPENING);

            return $lines->exists() ? (int) $lines->sum('quantity') : null;
        })();
    }

    /** "Started with 100." in front of $more, on a saved product whose opening stock is known. */
    private static function stockNote(?Product $record, ?string $more): ?string
    {
        $opening = self::openingStock($record);
        $started = $opening === null ? null : 'Started with '.number_format($opening).'.';

        return trim(implode(' ', array_filter([$started, $more]))) ?: null;
    }

    /** "Adjust stock" next to a locked stock field, for staff who may adjust stock. */
    private static function adjustStockLink(?Product $record): ?Action
    {
        if (! self::stockLocked($record) || ! ClientAccess::current('inventory', 'update')) {
            return null;
        }

        return Action::make('adjustStockLink')->label('Adjust stock')->icon('heroicon-m-arrow-top-right-on-square')
            ->url(StockLevels::getUrl(['tableSearch' => $record->sku ?: $record->name]), shouldOpenInNewTab: true);
    }

    /** Per record object, so a cached answer never outlives the request that loaded it. */
    private static ?\WeakMap $ordered = null;

    /**
     * Has this product been ordered? Then its stock is locked on the product form: it moves with
     * orders, deliveries and adjustments, and is changed in Inventory → Stock levels, where each
     * change keeps its reason.
     */
    public static function stockLocked(?Product $record): bool
    {
        if (! $record?->exists) {
            return false;
        }

        self::$ordered ??= new \WeakMap;

        return self::$ordered[$record] ??= OrderItem::withTrashed()->where('product_id', $record->getKey())->exists();
    }

    /** Is this row a variant the product already has (not one being added)? */
    private static function isSavedVariant(?Product $record, array $options): bool
    {
        if (! $record) {
            return false;
        }
        $clean = fn (array $o) => collect($o)->map(fn ($v) => trim((string) $v))->filter(fn ($v) => $v !== '')->sortKeys()->all();
        $wanted = $clean($options);

        return $wanted !== [] && collect((array) $record->variants)->contains(fn ($v) => is_array($v) && $clean((array) ($v['options'] ?? [])) === $wanted);
    }

    private static function combinationStock(mixed $variants): int
    {
        return (int) collect((array) $variants)
            ->filter(fn ($v) => is_array($v) && ($v['is_active'] ?? true))
            ->sum(fn (array $v) => max(0, (int) ($v['stock'] ?? 0)));
    }

    /**
     * Every image held on the combinations in the form: stored paths and files being uploaded.
     *
     * @return array<int, mixed>
     */
    private static function formVariantImages(mixed $variants): array
    {
        $images = [];
        foreach ((array) $variants as $variant) {
            foreach ((array) ($variant['images'] ?? []) as $image) {
                $images[] = $image;
            }
        }

        return $images;
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
