<?php

namespace App\Filament\Support;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Clients\ClientQuota;
use App\Rules\WithinClientStorage;
use App\Templates\TemplateManager;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** Reusable admin form building blocks shared across resources and settings pages. */
class Fields
{
    /** Most images one upload box holds, and one product's gallery (WithinProductImageCap). */
    public const MAX_IMAGES = 12;

    /**
     * Image upload stored on the public disk. Existing values may also be absolute URLs
     * (e.g. seeded Unsplash imagery), which are previewed as-is instead of being dropped.
     */
    /** The "nothing entered" value for a field: [] for list-like fields, false for switches, otherwise null. */
    public static function emptyStateFor(Field $field): mixed
    {
        return match (true) {
            $field instanceof Toggle, $field instanceof Checkbox => false,
            $field instanceof TagsInput, $field instanceof Repeater, $field instanceof CheckboxList,
            $field instanceof KeyValue, $field instanceof Builder, $field instanceof BaseFileUpload => [],
            $field instanceof Select && $field->isMultiple() => [],
            default => null,
        };
    }

    public static function image(string $name, ?string $label = null, string $directory = 'uploads', bool $multiple = false): FileUpload
    {
        // A client's media lives under clients/<client id>/… so its storage can be measured and capped.
        $client = app(ClientContext::class)->client();

        $field = FileUpload::make($name)
            ->label($label ?? Str::headline($name))
            ->disk('public')
            ->directory($client ? $client->mediaDirectory().'/'.$directory : $directory)
            ->rules([new WithinClientStorage])
            ->helperText(fn () => ($q = ClientQuota::current())?->storageLimit() === null ? null : 'Media storage: '.$q->storageLabel().' used.')
            ->visibility('public')
            ->image()
            ->imageEditor()
            ->maxSize(8192)
            // Adds the open icon on each saved image; a click on the image shows it large
            // (resources/views/filament/partials/image-lightbox.blade.php).
            ->openable()
            // "Container Red 1.JPG" is stored as "container-red-1-01jx…jpg": unique, safe (letters,
            // digits and dashes only), and still readable, so a photo named after a colour can be
            // sorted to that colour (Product::assignColourPhotos).
            ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => self::storedFileName($file))
            ->fetchFileInformation(false)
            ->getUploadedFileUsing(function (BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array {
                if (Str::startsWith($file, ['http://', 'https://', '/'])) {
                    return [
                        'name' => basename(parse_url($file, PHP_URL_PATH) ?: $file),
                        'size' => 0,
                        'type' => 'image/jpeg',
                        'url' => $file,
                    ];
                }

                $uploaded = $component->getUploadedFile($file, $storedFileNames);

                // The disk URL is built from APP_URL (the main domain), but a client edits on its
                // own subdomain. The upload box fetches each image with JavaScript, and the browser
                // blocks that cross-domain fetch (CORS), leaving the box on "Loading". The same
                // file is served on every host under /storage, so hand the box a same-host path.
                if (is_array($uploaded) && filled($uploaded['url'] ?? null) && ($path = parse_url($uploaded['url'], PHP_URL_PATH))) {
                    $query = parse_url($uploaded['url'], PHP_URL_QUERY);
                    $uploaded['url'] = $path.($query ? '?'.$query : '');
                }

                return $uploaded;
            });

        // A saved path whose file is gone (deleted, or lost in a move) would leave the box
        // spinning on "Loading" for ever, as there is nothing to fetch: drop it when the form opens.
        // Full URLs (sample images) are kept as they are.
        $field->afterStateHydrated(function (BaseFileUpload $component, $state): void {
            if (blank($state)) {
                return;
            }
            $disk = $component->getDisk();
            $kept = array_filter((array) $state, fn ($file) => ! is_string($file)
                || Str::startsWith($file, ['http://', 'https://', '/'])
                || $disk->exists($file));
            if (count($kept) !== count((array) $state)) {
                $component->state(is_array($state) ? $kept : (array_values($kept)[0] ?? null));
            }
        });

        if ($multiple) {
            // Small tiles (see .filepond grid rules in the admin theme), not a third of the form each.
            $field->multiple()->reorderable()->appendFiles()->panelLayout('grid')->maxFiles(self::MAX_IMAGES)->imagePreviewHeight('150');
        }

        return $field;
    }

    /** "Container Red 1.JPG" → "container-red-1-01jx….jpg". */
    public static function storedFileName(TemporaryUploadedFile $file): string
    {
        $base = Str::limit(Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)), 60, '');
        $extension = strtolower(preg_replace('/[^a-z0-9]/i', '', $file->getClientOriginalExtension()) ?: ($file->guessExtension() ?: 'jpg'));

        return ($base !== '' ? $base.'-' : '').strtolower((string) Str::ulid()).'.'.$extension;
    }

    /**
     * Name + auto-generated slug pair.
     *
     * @param  string|null  $scopeColumn  outside a store (a super admin working across all of
     *                                    them), the slug only has to be unique per value of this
     *                                    column, e.g. per storefront template. Inside a store it
     *                                    is unique across all of the store's records: a shared
     *                                    record and the store's own one with the same slug would
     *                                    compete for one URL, and only one of them is ever shown.
     */
    public static function nameAndSlug(string $nameField = 'name', string $label = 'Name', ?string $scopeColumn = null): array
    {
        return [
            TextInput::make($nameField)
                ->label($label)
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Set $set, Get $get, ?string $state, ?string $old) {
                    // Only auto-fill the slug while it still mirrors the previous name.
                    if (blank($get('slug')) || $get('slug') === Str::slug((string) $old)) {
                        $set('slug', Str::slug((string) $state));
                    }
                }),
            TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->alphaDash()
                // Slugs are unique inside a client, not across the platform: two clients may
                // each have a "shirts" category.
                ->unique(ignoreRecord: true, modifyRuleUsing: function (Unique $rule, Get $get) use ($scopeColumn) {
                    $clientId = app(ClientContext::class)->id();
                    $rule = $rule->where('client_id', $clientId);

                    return $scopeColumn && ! $clientId ? $rule->where($scopeColumn, $get($scopeColumn)) : $rule;
                })
                ->helperText('Used in the page URL. Lowercase letters, numbers and dashes only.'),
        ];
    }

    /** "Visible in" template selector for catalogue records (null = every template). */
    public static function templateVisibility(string $column = 'template'): Select
    {
        $context = app(StoreContext::class);

        return Select::make($column)
            ->default(fn () => $context->template() ?? (app(TemplateManager::class)->has((string) request()->query('template')) ? request()->query('template') : null))
            // Only a super admin sees it. A store's staff work in their one template, and the
            // model stamps it on every save (BelongsToTemplate), so the field would only be noise.
            ->hidden(fn () => $context->isLocked())
            ->dehydrated()
            ->label('Visible in')
            ->options(fn () => app(TemplateManager::class)->options())
            ->placeholder('All templates')
            ->native(false)
            ->helperText('Limit this record to one storefront template, or leave blank to show it in every template.');
    }

    /** Table filter matching templateVisibility(). */
    public static function templateFilter(): SelectFilter
    {
        return SelectFilter::make('template')
            ->label('Visible in')
            ->options(fn () => ['all' => 'All templates only'] + app(TemplateManager::class)->options())
            ->query(function ($query, array $data) {
                $value = $data['value'] ?? null;
                if ($value === 'all') {
                    $query->whereNull('template');
                } elseif ($value) {
                    $query->where(fn ($q) => $q->whereNull('template')->orWhere('template', $value));
                }
            });
    }

    /** SEO tab shared by every public content type. */
    public static function seoTab(): Tab
    {
        return Tab::make('SEO')
            ->icon('heroicon-o-magnifying-glass')
            ->schema([
                Section::make('Search & social')
                    ->description('Leave blank to fall back to the name/description and main image.')
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('Meta title')
                            ->maxLength(70)
                            ->helperText('Recommended 50–60 characters. The site suffix is appended automatically.'),
                        Textarea::make('meta_description')
                            ->label('Meta description')
                            ->rows(3)
                            ->maxLength(320)
                            ->helperText('Recommended 140–160 characters.'),
                        self::image('og_image', 'Social share image (1200×630)', 'seo'),
                        TextInput::make('canonical_url')
                            ->label('Canonical URL')
                            ->url()
                            ->helperText('Only set when this content duplicates another URL.'),
                        Toggle::make('noindex')
                            ->label('Hide from search engines (noindex)')
                            ->default(false),
                    ]),
            ]);
    }
}
