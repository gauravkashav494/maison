<?php

namespace App\Filament\Resources\Menus;

use App\Admin\StoreContext;
use App\Filament\Concerns\OpensTrashedRecords;
use App\Filament\Resources\Menus\Pages\CreateMenu;
use App\Filament\Resources\Menus\Pages\EditMenu;
use App\Filament\Resources\Menus\Pages\ListMenus;
use App\Filament\Resources\Menus\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\Menus\Schemas\MenuForm;
use App\Filament\Resources\Menus\Tables\MenusTable;
use App\Models\Menu;
use App\Templates\TemplateManager;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MenuResource extends Resource
{
    use OpensTrashedRecords;

    protected static ?string $model = Menu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Navigation';

    /**
     * Menu locations a new menu can still use, key => label: the store template's locations that
     * have no menu yet. Each location holds one menu per store, so a taken one is never offered.
     * Editing a menu keeps its own location in the list. A super admin working across all stores
     * gets every template's locations (which ones are free depends on the store), and the unique
     * rule on the form still applies.
     *
     * @return array<string, string>
     */
    public static function availableLocations(?Menu $editing = null): array
    {
        $template = app(StoreContext::class)->template();
        $locations = app(TemplateManager::class)->menuLocationsFor($template);
        if ($template === null) {
            return $locations;
        }

        // Scoped to this store by the model's global scopes. A deleted menu still holds its location
        // (the unique key counts it) until it is restored or deleted permanently.
        $taken = Menu::withTrashed()
            ->when($editing, fn ($q) => $q->whereKeyNot($editing->getKey()))
            ->pluck('location')
            ->all();

        return array_diff_key($locations, array_flip($taken));
    }

    public static function form(Schema $schema): Schema
    {
        return MenuForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MenusTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMenus::route('/'),
            'create' => CreateMenu::route('/create'),
            'edit' => EditMenu::route('/{record}/edit'),
        ];
    }
}
