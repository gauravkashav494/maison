<?php

namespace App\Filament\Resources\Menus;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
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
     * Where a menu location shows on the storefront, in words: "In the footer of every page".
     * Every template names its locations by kind (header, footer_…, legal); the exceptions are
     * listed by location. An unknown location gets null.
     */
    public static function placement(?string $location): ?string
    {
        $exceptions = [
            // Only the first few header links fit in these headers on a computer screen.
            'grocery_header' => 'At the top of every page (the first 4 links; all of them in the phone menu)',
            'plumbing_header' => 'At the top of every page (the first 4 links; all of them in the phone menu)',
            'heritage_header' => 'At the top of every page, after the category menu (the first 3 links; all of them in the phone menu)',
            'plumbing_legal' => 'In the footer of every page, as the Policies column',
            'heritage_legal' => 'In the footer of every page, at the end of the Help & policies column',
        ];
        if ($location && array_key_exists($location, $exceptions)) {
            return $exceptions[$location];
        }

        $alias = self::alias($location);

        return match (true) {
            $alias === null => null,
            $alias === 'header' => 'At the top of every page',
            $alias === 'legal' => 'On the bottom line of every page, under the footer',
            str_starts_with($alias, 'footer') => 'In the footer of every page, as its own column',
            default => null,
        };
    }

    /** The name a template's views use for a menu location ("bulk_footer_shop" → "footer_shop"). */
    public static function alias(?string $location): ?string
    {
        $template = $location ? app(TemplateManager::class)->templateForLocation($location) : null;
        $alias = $template ? array_search($location, $template->locationKeys(), true) : false;

        return $alias === false ? null : (string) $alias;
    }

    /**
     * The storefront with this menu's area outlined (null when there is no store to show). By
     * default only the header or footer holding it is shown (the "Where it shows" panel); with
     * $fullPage the whole page is shown, scrolled to the menu ("View on storefront").
     */
    public static function previewUrl(?string $location, bool $fullPage = false): ?string
    {
        $store = self::storefrontUrl();
        $alias = self::alias($location);
        if (! $store || ! $alias) {
            return null;
        }

        return rtrim($store, '/').'/?menu_preview='.$alias.($fullPage ? '&menu_preview_page=1' : '');
    }

    /** The client's storefront, to see a menu where it shows (null for a super admin across stores). */
    public static function storefrontUrl(): ?string
    {
        return app(ClientContext::class)->client()?->publicUrl();
    }

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
