<?php

namespace App\Filament\Pages;

use App\Admin\StoreContext;
use App\Clients\ClientAccess;
use App\Clients\ClientContext;
use App\Clients\ClientModules;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Filament\Support\CategoryFilter;
use App\Filament\Support\Fields;
use App\Filament\Support\RowActions;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Page as ContentPage;
use App\Models\Post;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceArea;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

/**
 * SEO → Search listings: how every public page of the store shows up in Google and when shared,
 * one content type at a time (products, categories, pages, journal…). The search & social fields
 * (meta title, description, share image, canonical URL, noindex) are edited only here, not on
 * the content forms. Needs the "SEO" module; a type shows only when its own module is on for
 * the client and fits the store (no products on a service business).
 */
class SearchListings extends Page implements HasTable
{
    use InteractsWithTable;

    /**
     * Keyed by the content type's client module. 'path' is the storefront address before the slug;
     * 'one' names a single record (the label of the filter that picks records by name).
     *
     * @var array<string, array{model: class-string<Model>, name: string, path: string, one: string}>
     */
    public const TYPES = [
        'products' => ['model' => Product::class, 'name' => 'name', 'path' => '/product/', 'one' => 'Product'],
        'categories' => ['model' => Category::class, 'name' => 'name', 'path' => '/shop/', 'one' => 'Category'],
        'collections' => ['model' => Collection::class, 'name' => 'name', 'path' => '/collections/', 'one' => 'Collection'],
        'brands' => ['model' => Brand::class, 'name' => 'name', 'path' => '/brands/', 'one' => 'Brand'],
        'services' => ['model' => Service::class, 'name' => 'name', 'path' => '/services/', 'one' => 'Service'],
        'service_areas' => ['model' => ServiceArea::class, 'name' => 'name', 'path' => '/service-areas/', 'one' => 'Service area'],
        'pages' => ['model' => ContentPage::class, 'name' => 'title', 'path' => '/', 'one' => 'Page'],
        'posts' => ['model' => Post::class, 'name' => 'title', 'path' => '/journal/', 'one' => 'Story'],
    ];

    public const STATUSES = [
        'custom' => 'Custom',
        'partial' => 'Partly custom',
        'default' => 'Default',
        'hidden' => 'Hidden from search',
    ];

    protected string $view = 'filament.pages.search-listings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|\UnitEnum|null $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Search listings';

    protected static ?string $title = 'Search listings';

    protected static ?string $slug = 'seo';

    /** The content type on screen, a key of TYPES. */
    #[Url]
    public string $type = '';

    public static function canAccess(): bool
    {
        return ClientAccess::current('seo', 'view') && self::types() !== [];
    }

    /** @return array<string, string> module key => menu label, for the types this store has */
    public static function types(): array
    {
        $store = app(StoreContext::class);
        $client = app(ClientContext::class)->client();

        $types = [];
        foreach (self::TYPES as $key => $type) {
            if ($client?->hasModule($key) && $store->allowsModel($type['model'])) {
                $types[$key] = ClientModules::get($key)['label'] ?? str($key)->headline()->toString();
            }
        }

        return $types;
    }

    public function mount(): void
    {
        $this->type = $this->validType($this->type);
    }

    public function updatedType(): void
    {
        $this->type = $this->validType($this->type);
        $this->tableSort = null;
        $this->tableSearch = '';
        $this->resetTable();
    }

    private function validType(string $type): string
    {
        $types = self::types();

        return array_key_exists($type, $types) ? $type : (string) array_key_first($types);
    }

    /** @return array{model: class-string<Model>, name: string, path: string, one: string} */
    private function current(): array
    {
        return self::TYPES[$this->validType($this->type)] ?? self::TYPES['products'];
    }

    public function table(Table $table): Table
    {
        ['model' => $model, 'name' => $name, 'path' => $path] = $this->current();

        return $table
            ->query(fn () => $model::query())
            ->defaultSort($name)
            ->columns([
                ImageColumn::make('share_image')->label('')
                    ->state(fn (Model $record) => $record->seoImage())
                    ->square()->imageSize(40),
                // One key for every type (products have a name, pages a title), so a sort survives switching.
                TextColumn::make('listing_name')->label('Name')->wrap()
                    ->state(fn (Model $record) => $record->getAttribute($name))
                    ->searchable([$name])->sortable([$name])
                    ->description(fn (Model $record) => $path.$record->slug),
                TextColumn::make('meta_title')->label('Title in search')->wrap()
                    ->state(fn (Model $record) => $record->seoTitle())
                    ->color(fn (Model $record) => filled($record->meta_title) ? null : 'gray')
                    ->tooltip(fn (Model $record) => filled($record->meta_title) ? null : 'Default: made from the name')
                    ->searchable(),
                TextColumn::make('meta_description')->label('Description in search')->wrap()->limit(110)
                    ->state(fn (Model $record) => $record->seoDescription())
                    ->color(fn (Model $record) => filled($record->meta_description) ? null : 'gray')
                    ->tooltip(fn (Model $record) => filled($record->meta_description) ? null : 'Default: made from the description')
                    ->placeholder('None: the site default is used')
                    ->searchable(),
                TextColumn::make('seo_status')->label('SEO')->badge()
                    ->state(fn (Model $record) => self::status($record))
                    ->formatStateUsing(fn (string $state) => self::STATUSES[$state])
                    ->color(fn (string $state) => match ($state) {
                        'custom' => 'success',
                        'partial' => 'info',
                        'hidden' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('seo_status')->label('SEO')->options(self::STATUSES)
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) ? self::whereStatus($query, $data['value']) : $query),
                ...$this->typeFilters(),
            ])
            ->recordActions([RowActions::group([
                $this->editAction(),
            ])])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('hideFromSearch')->label('Hide from search engines')->icon(Heroicon::OutlinedEyeSlash)
                        ->authorize(fn () => ClientAccess::current('seo', 'update'))
                        ->requiresConfirmation()
                        ->modalDescription('Search engines are asked not to list these pages (noindex). They stay open on the store.')
                        ->action(fn (EloquentCollection $records) => $records->each->update(['noindex' => true]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('showInSearch')->label('Show in search engines')->icon(Heroicon::OutlinedEye)
                        ->authorize(fn () => ClientAccess::current('seo', 'update'))
                        ->action(fn (EloquentCollection $records) => $records->each->update(['noindex' => false]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->striped()
            ->emptyStateHeading('Nothing here yet')
            ->emptyStateDescription('Add some first; each one then gets its search listing here.')
            ->emptyStateIcon('heroicon-o-magnifying-glass');
    }

    /** The filters of the content type's own list (its master), so a listing is found the same way. */
    private function typeFilters(): array
    {
        $visible = TernaryFilter::make('is_active')->label('Visible on storefront');

        return [$this->recordFilter(), ...match ($this->validType($this->type)) {
            'products' => [
                CategoryFilter::make(),
                ProductsTable::collectionFilter(),
                SelectFilter::make('brand_id')->label('Brand')->native(false)->searchable()->preload()
                    ->relationship('brandRecord', 'name')
                    ->visible(fn () => (bool) app(ClientContext::class)->client()?->hasModule('brands')),
                $visible,
                TernaryFilter::make('is_new')->label('New arrival'),
                TernaryFilter::make('is_best_seller')->label('Best seller'),
            ],
            'categories' => [
                TernaryFilter::make('parent_id')->label('Level')->nullable()->trueLabel('Child categories')->falseLabel('Top level')->queries(
                    true: fn (Builder $q) => $q->whereNotNull('parent_id'),
                    false: fn (Builder $q) => $q->whereNull('parent_id'),
                ),
                $visible,
            ],
            'collections' => [$visible],
            'pages' => [
                SelectFilter::make('template')->label('Layout')->options(fn () => PageResource::layoutOptions())->multiple()->native(false),
                $visible,
            ],
            'posts' => [
                TernaryFilter::make('is_featured')->label('Lead story'),
                TernaryFilter::make('published')->label('Published')->trueLabel('Published')->falseLabel('Scheduled')->queries(
                    true: fn (Builder $q) => $q->where('published_at', '<=', now()),
                    false: fn (Builder $q) => $q->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '>', now())),
                ),
            ],
            'services' => [$visible, TernaryFilter::make('is_popular')->label('Popular'), TernaryFilter::make('is_emergency')->label('Emergency')],
            'service_areas' => [$visible],
            default => [],
        }];
    }

    /**
     * Pick records of the open tab by name: products on Products, categories on Categories, and so
     * on. A category also brings the categories below it ("Men" lists Men › Shirts too).
     */
    private function recordFilter(): SelectFilter
    {
        ['model' => $model, 'name' => $name, 'one' => $one] = $this->current();
        $categories = $model === Category::class;

        return SelectFilter::make('record')
            ->label($one)
            ->multiple()
            ->searchable()
            ->native(false)
            // Categories in tree order, each name with the categories above it on the right, as
            // in the Category filter; the chip above the table stays plain ("Men › Shirts").
            ->options(fn () => $categories
                ? CategoryFilter::treeOptionsHtml()
                : $model::query()->orderBy($name)->pluck($name, (new $model)->getKeyName())->all())
            ->modifyFormFieldUsing(fn (Select $select) => $select->allowHtml($categories))
            ->when($categories, fn (SelectFilter $filter) => $filter->indicateUsing(function (array $state) use ($one): array {
                $names = array_values(array_intersect_key(CategoryFilter::treeOptions(), array_flip(array_map('intval', array_filter((array) ($state['values'] ?? []), 'filled')))));

                return $names === [] ? [] : [Indicator::make($one.': '.collect($names)->join(', ', ' & '))];
            }))
            ->query(function (Builder $query, array $data) use ($categories): Builder {
                $ids = array_values(array_filter((array) ($data['values'] ?? []), 'filled'));
                if ($ids === []) {
                    return $query;
                }
                if ($categories) {
                    $ids = Category::query()->whereKey($ids)->get()
                        ->flatMap(fn (Category $category) => [$category->getKey(), ...$category->descendantIds()])
                        ->unique()->values()->all();
                }

                return $query->whereKey($ids);
            });
    }

    private function editAction(): Action
    {
        return Action::make('editSeo')
            ->label('Edit SEO')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn () => ClientAccess::current('seo', 'update'))
            ->modalHeading(fn (Model $record) => 'SEO: '.$record->getAttribute($this->current()['name']))
            ->modalDescription('Leave a field blank to use the default shown in grey.')
            ->modalSubmitActionLabel('Save')
            ->modalWidth('2xl')
            ->fillForm(fn (Model $record) => $record->only(['meta_title', 'meta_description', 'og_image', 'canonical_url', 'noindex']))
            ->schema(fn (Model $record) => Fields::seoFields($record))
            ->action(function (Model $record, array $data) {
                // A normal model save, so the activity log names what changed.
                $record->update($data);
                Notification::make()->success()->title('SEO updated')->send();
            });
    }

    /** custom (title and description both set), partial, default (neither set) or hidden (noindex). */
    public static function status(Model $record): string
    {
        if ($record->noindex) {
            return 'hidden';
        }
        $set = (int) filled($record->meta_title) + (int) filled($record->meta_description);

        return match ($set) {
            2 => 'custom',
            1 => 'partial',
            default => 'default',
        };
    }

    public static function whereStatus(Builder $query, string $status): Builder
    {
        $filled = fn (string $column) => fn (Builder $q) => $q->whereNotNull($column)->where($column, '!=', '');
        $blank = fn (string $column) => fn (Builder $q) => $q->whereNull($column)->orWhere($column, '');

        if ($status === 'hidden') {
            return $query->where('noindex', true);
        }
        $query->where('noindex', false);

        return match ($status) {
            'custom' => $query->where($filled('meta_title'))->where($filled('meta_description')),
            'default' => $query->where($blank('meta_title'))->where($blank('meta_description')),
            'partial' => $query
                ->where(fn (Builder $q) => $q->where($filled('meta_title'))->orWhere($filled('meta_description')))
                ->where(fn (Builder $q) => $q->where($blank('meta_title'))->orWhere($blank('meta_description'))),
            default => $query,
        };
    }
}
