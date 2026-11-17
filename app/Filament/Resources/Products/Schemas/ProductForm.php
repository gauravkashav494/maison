<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Clients\ClientQuota;
use App\Filament\Forms\Components\ImagePicker;
use App\Filament\Support\Fields;
use App\Models\Product;
use App\Rules\WithinClientImageLimit;
use App\Support\ColourNames;
use App\Templates\Template;
use App\Templates\TemplateManager;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
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
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

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
                                        ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('client_id', app(ClientContext::class)->id())),
                                    TextInput::make('brand')->maxLength(80)->datalist(fn () => Product::query()->whereNotNull('brand')->distinct()->pluck('brand')->all())->helperText('Used by the shop Brand filter.'),
                                    TextInput::make('material')->label('Primary material')->maxLength(80)->datalist(fn () => Product::query()->whereNotNull('material')->distinct()->pluck('material')->all())->helperText('Used by the shop Material filter.'),
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
                            ]),

                        Tab::make('Media')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Fields::image('images', 'Product images', 'products', multiple: true)
                                    // The allowance is enforced here as well as in the policy, so the
                                    // limit holds even if the field is posted directly. Images on the
                                    // combinations count too, so the check sees all of them at once.
                                    ->rules(fn (?Product $record, Get $get) => [new WithinClientImageLimit(self::imagePaths($record), [...self::formVariantImages($get('variants')), ...self::formColourUploads($get('variant_attributes'))])])
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
                            ]),

                        // Price, stock and variants in one place, as on most stores: the product's price and
                        // stock, the options (with photos per colour), then each variant's own price and stock.
                        Tab::make('Pricing & Variants')
                            ->icon('heroicon-o-swatch')
                            ->schema([
                                // The product's price and stock; a variant without its own price uses it.
                                Section::make('Price & stock')
                                    ->icon('heroicon-o-banknotes')
                                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                                    ->schema([
                                        TextInput::make('price')
                                            ->label(fn (Get $get) => $get('has_variants') ? 'Default price' : 'Price')
                                            ->numeric()->prefix('₹')->required()->minValue(0)
                                            ->helperText(fn (Get $get) => $get('has_variants') ? 'Used for any variant without its own price (step 2 below).' : null),
                                        TextInput::make('compare_at_price')
                                            ->label('Compare-at price')
                                            ->numeric()
                                            ->prefix('₹')
                                            ->minValue(0)
                                            // A "was" price at or below the price shows no discount, so it is refused.
                                            ->rules([fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get) {
                                                if (filled($value) && filled($get('price')) && (float) $value <= (float) $get('price')) {
                                                    $fail('Set the compare-at price above the '.($get('has_variants') ? 'default price' : 'price').' ('.money((float) $get('price')).'), or leave it blank.');
                                                }
                                            }])
                                            ->helperText(fn (Get $get) => $get('has_variants')
                                                ? 'Must be above the default price. Used for any variant without its own compare-at price (step 2 below).'
                                                : 'Must be above the price. Shows a sale badge and strikethrough.'),
                                        // Selling as variant: each variant has its own stock (step 2 below),
                                        // so this is not asked twice; it is saved as their total, which the shop
                                        // uses for "in stock" and the cart.
                                        TextInput::make('stock')->numeric()->minValue(0)->default(100)->required()
                                            ->hidden(fn (Get $get) => (bool) $get('has_variants'))
                                            ->dehydratedWhenHidden()
                                            ->dehydrateStateUsing(fn ($state, Get $get) => $get('has_variants') ? self::combinationStock($get('variants')) : $state),
                                        // Read-only while selling as variant: the total of the variants' stock,
                                        // shown greyed in the field's place. Not saved itself (`stock` above is).
                                        TextInput::make('variant_stock_total')
                                            ->label('Stock')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->placeholder(fn (Get $get) => number_format(self::combinationStock($get('variants'))))
                                            ->helperText('Total of the variants (step 2 below).')
                                            ->visible(fn (Get $get) => (bool) $get('has_variants')),
                                        TextInput::make('max_qty')
                                            ->label('Max quantity per order')
                                            ->numeric()->minValue(1)->maxValue(99)
                                            ->placeholder('No limit')
                                            ->visible(self::uses(Template::PRODUCT_FIELDS_ORDER_LIMIT)),
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
                                                        ->native()
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
                                                        // A new colour value named like a colour ("Red", "Navy") starts with that swatch.
                                                        ->afterStateUpdated(fn (Get $get, Set $set, $state) => self::fillNamedSwatches($get, $set, $get('name'), $state))
                                                        // Common values for the chosen option, offered while typing; any
                                                        // other value can still be typed in.
                                                        ->suggestions(fn (Get $get) => self::optionValues(self::presetFor($get('name')))['all'])
                                                        ->reorderable()
                                                        ->placeholder('Add a value'),
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
                                            ->table(fn (Get $get) => [
                                                ...array_map(fn (array $attribute) => TableColumn::make($attribute['name'])->markAsRequired()->width('9rem'), self::definedAttributes($get, '')),
                                                TableColumn::make('SKU')->width('11rem'),
                                                TableColumn::make('Price (₹)')->width('8rem'),
                                                TableColumn::make('Compare-at (₹)')->width('8rem'),
                                                TableColumn::make('Stock')->markAsRequired()->width('6rem'),
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
                                                        // A plain select fits the table cell; the searchable one squeezed the value.
                                                        ->native()
                                                        ->placeholder('Choose')
                                                        ->required()
                                                        ->validationMessages(['required' => 'Choose a '.$attribute['name'].' (a value may have been removed from the option above).']),
                                                    self::definedAttributes($get, ''),
                                                ),
                                                TextInput::make('sku')->label('SKU')->maxLength(64)->placeholder(fn (Get $get) => $get('../../sku') ?: null),
                                                // Blank uses the product's price, shown greyed as the placeholder.
                                                TextInput::make('price')->label('Price')->numeric()->minValue(0)->placeholder(fn (Get $get) => filled($get('../../price')) ? number_format((float) $get('../../price')) : null),
                                                // The "was" price shown struck through. Blank uses the product's
                                                // compare-at price; either shows only when above this variant's price.
                                                TextInput::make('compare_at_price')->label('Compare-at')->numeric()->minValue(0)
                                                    ->placeholder(fn (Get $get) => filled($get('../../compare_at_price')) ? number_format((float) $get('../../compare_at_price')) : null)
                                                    ->rules([fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get) {
                                                        $price = filled($get('price')) ? (float) $get('price') : (float) $get('../../price');
                                                        if (filled($value) && (float) $value <= $price) {
                                                            $fail('Set the compare-at price above this variant\'s price ('.money($price).'), or leave it blank.');
                                                        }
                                                    }]),
                                                // Required: a blank stock used to copy the product's whole stock to
                                                // every combination, so the store could sell many times what it had.
                                                TextInput::make('stock')->label('Stock')->numeric()->integer()->minValue(0)->required()
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
                            ]),

                        Tab::make('Grocery & nutrition')
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
                            ]),

                        Tab::make('Plumbing specifications')
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
     * True when the product has an older colour or size list that no option in step 1
     * replaces, so it is still shown on the storefront as it is.
     */
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
        $set('variants', $rows);

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
