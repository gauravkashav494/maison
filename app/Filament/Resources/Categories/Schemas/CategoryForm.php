<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Admin\StoreContext;
use App\Filament\Support\Fields;
use App\Models\Category;
use App\Rules\NotOwnDescendantCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Details')
                    ->icon('heroicon-o-squares-2x2')
                    ->schema([
                        // Name, Parent category and Tagline on one row; stacked on phones.
                        Grid::make(['default' => 1, 'md' => 3])->schema([
                            TextInput::make('name')->required()->maxLength(255)
                                // The URL slug is made from it on save (GeneratesSlug) and kept when renamed.
                                ->helperText(fn (?Model $record) => $record?->slug ? 'Page address: /shop/'.$record->slug : 'The page address is made from the name when saved.'),
                            Select::make('parent_id')
                                ->label('Parent category')
                                // Hide the category itself and everything below it: picking one would make a loop.
                                ->relationship('parent', 'name', fn ($query, ?Category $record) => ($record ? $query->whereKeyNot([$record->getKey(), ...$record->descendantIds()]) : $query)->with(Category::PATH))
                                // "Shirts" with "Men" on the right, as in the product form's category tags:
                                // two categories may share a name under different parents.
                                ->getOptionLabelFromRecordUsing(fn (Category $category) => Fields::categoryOption($category->name, $category->namesAbove()))
                                ->allowHtml()
                                ->rule(fn (?Category $record) => new NotOwnDescendantCategory($record))
                                ->searchable()
                                ->preload()
                                ->placeholder('None (top level)'),
                            TextInput::make('tagline')->maxLength(120)->helperText('Short line shown on category tiles.'),
                        ]),
                        // The categories directly under this one (Shirts, Pants, Suits under Men), as rows:
                        // rename, reorder, remove (to the trash) or add. Each is a category of its own,
                        // saved as a normal model, so every change is in the activity log.
                        Repeater::make('children')
                            ->label('Child categories')
                            ->relationship('children')
                            ->simple(
                                TextInput::make('name')
                                    ->placeholder('e.g. Shirts')
                                    ->required()
                                    ->maxLength(255)
                                    // The same name twice under one category, whatever the capitals or spaces.
                                    ->rule(fn (TextInput $component) => function (string $attribute, mixed $value, \Closure $fail) use ($component) {
                                        $key = fn ($name) => mb_strtolower(trim((string) $name));
                                        $rows = (array) $component->getContainer()->getParentComponent()?->getState();
                                        $same = array_filter($rows, fn ($row) => $key(is_array($row) ? ($row['name'] ?? '') : $row) === $key($value));
                                        if (count($same) > 1) {
                                            $fail('"'.trim((string) $value).'" is listed twice.');
                                        }
                                    })
                                    ->dehydrateStateUsing(fn (?string $state) => trim((string) $state)),
                            )
                            ->orderColumn('sort_order')
                            ->grid(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->defaultItems(0)
                            ->addActionLabel('Add child category')
                            ->helperText('Optional. Each one is saved as its own category under this one (e.g. Shirts, Pants under Men), with its own page. Removing one moves it to the trash.')
                            // A new one copies this category's store and visibility, with a slug led by this
                            // one's ("men-shirts"), so Men and Women can both have "Shirts".
                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data, Category $record) => $record->newChildAttributes($data['name']) + $data),
                        Textarea::make('description')->rows(3),
                        RichEditor::make('content')->label('Category page content')->helperText('Optional long-form copy shown below the products (used by templates with rich category pages).')->toolbarButtons(['bold', 'italic', 'bulletList', 'h3', 'link']),
                        Fields::image('image', 'Category image (portrait, 4:5)', 'categories'),
                        // The order is set by dragging rows in the categories list (child categories: in the list above).
                        Grid::make(2)->schema([
                            Toggle::make('is_active')->label('Visible on storefront')->default(true),
                            Toggle::make('show_in_menu')->label('Feature in menu & homepage')->default(true)
                                ->helperText("Off = the page still works by link, but isn't listed in the header menus or homepage.")
                                // Bulk Order lists every visible category, so the switch would do nothing there.
                                ->hidden(fn (Get $get) => ! Category::featurableIn($get('template') ?: app(StoreContext::class)->template())),
                            Fields::templateVisibility()->live()->columnSpan(2),
                        ]),
                    ]),
            ]);
    }
}
