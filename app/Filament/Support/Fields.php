<?php

namespace App\Filament\Support;

use Filament\Forms\Components\BaseFileUpload;
use App\Templates\TemplateManager;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

/** Reusable admin form building blocks shared across resources and settings pages. */
class Fields
{
    /**
     * Image upload stored on the public disk. Existing values may also be absolute URLs
     * (e.g. seeded Unsplash imagery), which are previewed as-is instead of being dropped.
     */
    public static function image(string $name, ?string $label = null, string $directory = 'uploads', bool $multiple = false): FileUpload
    {
        $field = FileUpload::make($name)
            ->label($label ?? Str::headline($name))
            ->disk('public')
            ->directory($directory)
            ->visibility('public')
            ->image()
            ->imageEditor()
            ->maxSize(8192)
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

                return $component->getUploadedFile($file, $storedFileNames);
            });

        if ($multiple) {
            $field->multiple()->reorderable()->appendFiles()->panelLayout('grid')->maxFiles(12);
        }

        return $field;
    }

    /** Name + auto-generated slug pair. */
    /** @param  string|null  $scopeColumn  make the slug unique per value of this column (e.g. per storefront template) */
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
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $scopeColumn ? $rule->where($scopeColumn, $get($scopeColumn)) : $rule)
                ->helperText('Used in the page URL. Lowercase letters, numbers and dashes only.'),
        ];
    }

    /** "Visible in" template selector for catalogue records (null = every template). */
    public static function templateVisibility(string $column = 'template'): Select
    {
        return Select::make($column)
            ->default(fn () => app(TemplateManager::class)->has((string) request()->query('template')) ? request()->query('template') : null)
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
