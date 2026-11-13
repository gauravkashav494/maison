<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Concerns\OpensTrashedRecords;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Resources\Pages\Schemas\PageForm;
use App\Filament\Resources\Pages\Tables\PagesTable;
use App\Admin\StoreContext;
use App\Models\Page;
use App\Templates\TemplateManager;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PageResource extends Resource
{
    use OpensTrashedRecords;

    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 11;

    /**
     * Page layouts to offer, key => label: the ones the store's design has a view for (see
     * Template::pageLayouts()). A store's team always works in its own template; a super admin
     * follows the page's "Visible in" choice, and a page shared by every template gets all of
     * them. A page that already uses a layout its design lacks keeps it in the list, marked, so
     * opening the page does not silently change it.
     *
     * @return array<string, string>
     */
    public static function layoutOptions(?string $storefrontTemplate = null, ?string $current = null): array
    {
        $templates = app(TemplateManager::class);
        $id = app(StoreContext::class)->template() ?? $storefrontTemplate;
        if (blank($id) || ! $templates->has($id)) {
            return Page::TEMPLATES;
        }

        $options = array_intersect_key(Page::TEMPLATES, array_flip($templates->get($id)->pageLayouts()));
        if (filled($current) && ! isset($options[$current]) && isset(Page::TEMPLATES[$current])) {
            $options[$current] = Page::TEMPLATES[$current].' (not in this store\'s design)';
        }

        return $options;
    }

    public static function form(Schema $schema): Schema
    {
        return PageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
