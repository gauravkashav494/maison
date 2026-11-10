<?php

namespace App\Filament\Resources\Menus\RelationManagers;

use App\Filament\Support\Fields;
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
    }

    public function form(Schema $schema): Schema
    {
        $menuId = $this->getOwnerRecord()->getKey();

        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('label')->required()->maxLength(80),
                TextInput::make('url')
                    ->label('Link')
                    ->maxLength(255)
                    ->placeholder('/shop/clothing or https://…')
                    ->helperText('Relative paths keep visitors on this site.'),
                Select::make('parent_id')
                    ->label('Parent item')
                    ->options(fn (?MenuItem $record) => MenuItem::where('menu_id', $menuId)
                        ->whereNull('parent_id')
                        ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                        ->orderBy('sort_order')
                        ->pluck('label', 'id'))
                    ->placeholder('None (top level)')
                    ->helperText('Children of a header item appear in its mega menu.'),
                TextInput::make('group')
                    ->label('Mega-menu column')
                    ->maxLength(40)
                    ->placeholder('Categories, Featured, Tiles…')
                    ->helperText('Child items sharing a column name are listed together. Use "Tiles" for image tiles.'),
            ]),
            Section::make('Image tile (mega menu only)')
                ->collapsed()
                ->schema([
                    Fields::image('image', 'Tile image (portrait 3:4)', 'menus'),
                    TextInput::make('eyebrow')->maxLength(40)->placeholder('Just in'),
                ]),
            Grid::make(4)->schema([
                TextInput::make('badge')->maxLength(20)->helperText('Small text beside the label, e.g. a count.'),
                Toggle::make('is_accent')->label('Highlight (rouge)')->inline(false),
                Toggle::make('opens_in_new_tab')->label('New tab')->inline(false),
                Toggle::make('is_active')->label('Visible')->default(true)->inline(false),
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
                ToggleColumn::make('is_active')->label('Visible'),
            ])
            ->filters([
                SelectFilter::make('parent_id')
                    ->label('Parent')
                    ->options(fn () => MenuItem::where('menu_id', $this->getOwnerRecord()->getKey())->whereNull('parent_id')->pluck('label', 'id')),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
