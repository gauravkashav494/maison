<?php

namespace App\Filament\Resources\Menus\RelationManagers;

use App\Filament\Support\Fields;
use App\Filament\Support\RowActions;
use App\Filament\Support\ToggleToast;
use App\Models\MenuItem;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'allItems';

    protected static ?string $title = 'Menu items';

    /**
     * Hierarchy-aware drag-and-drop: items are listed in tree order (parent, then its children).
     * Dropping a child under a different top-level item moves it into that item's menu.
     */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        MenuItem::renumber((int) $this->getOwnerRecord()->getKey(), $order);
        $this->itemsChanged();
    }

    /**
     * The item editor: a panel that slides in from the right (see the actions in table()), one
     * column of three sections. Every field is offered for every menu; the hints say where the store
     * draws them (only the Fashion header opens mega menus with columns, tiles and badges).
     */
    public function form(Schema $schema): Schema
    {
        $menuId = $this->getOwnerRecord()->getKey();
        $megaMenu = $this->getOwnerRecord()->location === 'header';

        return $schema->columns(1)->components([
            Section::make('Link')
                ->icon('heroicon-o-link')
                ->schema([
                    TextInput::make('label')->label('Label')->required()->maxLength(80)
                        ->placeholder('Privacy policy')
                        ->helperText('What shoppers see.'),
                    TextInput::make('url')->label('Link')->maxLength(255)
                        ->placeholder('/privacy or https://…')
                        ->helperText('A page of this store starts with / (e.g. /shop). Another website: the full address.'),
                ]),

            Section::make('Options')
                ->icon('heroicon-o-adjustments-horizontal')
                ->schema([
                    Toggle::make('is_active')->label('Visible on storefront')->default(true)
                        ->helperText('Off: hidden on the store, kept here.'),
                    Toggle::make('opens_in_new_tab')->label('Open in a new tab')
                        ->helperText('For links to other websites.'),
                    Toggle::make('is_accent')->label('Highlight')
                        ->helperText('In the accent colour, e.g. for Offers. Shown in header menus.'),
                ]),

            Section::make('Sub-items & mega menu')
                ->icon('heroicon-o-squares-2x2')
                ->description($megaMenu
                    ? 'Optional. Sub-items open in a large drop-down under a top item of the header.'
                    : 'Optional. Used by the header’s drop-down (mega) menu; most footer and policy menus show only their top items.')
                ->schema([
                    Select::make('parent_id')
                        ->label('Show under')
                        ->options(fn (?MenuItem $record) => MenuItem::where('menu_id', $menuId)
                            ->whereNull('parent_id')
                            ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                            ->orderBy('sort_order')
                            ->pluck('label', 'id'))
                        ->placeholder('Nothing (a top item)')
                        ->helperText('Pick a top item to list this in its drop-down.'),
                    Grid::make(2)->schema([
                        TextInput::make('group')
                            ->label('Column')
                            ->maxLength(40)
                            ->placeholder('Categories, Featured, Tiles…')
                            ->helperText('Sub-items with the same column name are listed together. "Tiles" shows image tiles.'),
                        TextInput::make('badge')->maxLength(20)
                            ->placeholder('New')
                            ->helperText('Small text beside the item, e.g. "New" or a count.'),
                    ]),
                    TextInput::make('eyebrow')->label('Tile small heading')->maxLength(40)
                        ->placeholder('Just in')
                        ->helperText('Above the tile\'s name, for "Tiles" only.'),
                    Fields::image('image', 'Tile image (portrait 3:4), for "Tiles" only', 'menus'),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->defaultSort('created_at', 'desc')
            ->reorderable('sort_order')
            ->paginated(false)
            ->reorderRecordsTriggerAction(fn (Action $action, bool $isReordering) => $action
                ->button()
                ->color($isReordering ? 'gray' : 'primary')
                ->icon($isReordering ? 'heroicon-o-check' : 'heroicon-o-arrows-up-down')
                ->label($isReordering ? 'Done — save order' : 'Drag & drop reorder'))
            ->description('Newest items are listed first. Click “Drag & drop reorder” to see the menu order and drag rows. Dragging a sub-item under another top-level item moves it there.')
            ->columns([
                TextColumn::make('label')
                    ->formatStateUsing(fn (string $state, MenuItem $r) => ($r->parent_id ? '  ↳ ' : '').$state)
                    ->weight(fn (MenuItem $r) => $r->parent_id ? null : 'bold')
                    ->searchable(),
                TextColumn::make('parent.label')->label('Parent')->badge()->color('gray')->placeholder('Top level'),
                TextColumn::make('group')->label('Column')->badge()->color('info')->placeholder('—'),
                TextColumn::make('url')->label('Link')->limit(40)->color('gray'),
                IconColumn::make('image')->label('Tile')->boolean()->getStateUsing(fn (MenuItem $r) => filled($r->image)),
                ToggleColumn::make('is_active')->label('Visible on storefront')
                    // Replaces the global after-switch callback, so it sends the toast itself.
                    ->afterStateUpdated(function (ToggleColumn $column, $state): void {
                        ToggleToast::send($column, $state);
                        $this->itemsChanged();
                    }),
            ])
            ->filters([
                SelectFilter::make('parent_id')
                    ->label('Parent')
                    ->options(fn () => MenuItem::where('menu_id', $this->getOwnerRecord()->getKey())->whereNull('parent_id')->pluck('label', 'id')),
            ])
            ->headerActions([
                CreateAction::make()->slideOver()->modalWidth('lg')->after(fn () => $this->itemsChanged()),
            ])
            ->recordAction(null) // no click-to-edit on the row; Edit is in the ⋮ menu
            ->recordActions([RowActions::group([
                EditAction::make()->slideOver()->modalWidth('lg')->after(fn () => $this->itemsChanged()),
                DeleteAction::make()->after(fn () => $this->itemsChanged()),
            ])])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->after(fn () => $this->itemsChanged()),
                ]),
            ]);
    }

    /** Tells "Where it shows" on the page to reload, so the store section shows the new links. */
    private function itemsChanged(): void
    {
        $this->dispatch('menu-items-changed');
    }
}
