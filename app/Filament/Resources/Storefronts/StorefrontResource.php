<?php

namespace App\Filament\Resources\Storefronts;

use App\Filament\Resources\Storefronts\Pages\CreateStorefront;
use App\Filament\Resources\Storefronts\Pages\EditStorefront;
use App\Filament\Resources\Storefronts\Pages\ListStorefronts;
use App\Filament\Resources\Storefronts\Schemas\StorefrontForm;
use App\Filament\Resources\Storefronts\Tables\StorefrontsTable;
use App\Models\Storefront;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/** Stores = one website per template, with its owners. Super admin only (StorefrontPolicy). */
class StorefrontResource extends Resource
{
    protected static ?string $model = Storefront::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Stores';

    protected static ?string $modelLabel = 'store';

    public static function form(Schema $schema): Schema
    {
        return StorefrontForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StorefrontsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStorefronts::route('/'),
            'create' => CreateStorefront::route('/create'),
            'edit' => EditStorefront::route('/{record}/edit'),
        ];
    }
}
