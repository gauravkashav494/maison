<?php

namespace App\Filament\Resources\ServiceAreas;

use App\Filament\Resources\ServiceAreas\Pages\CreateServiceArea;
use App\Filament\Resources\ServiceAreas\Pages\EditServiceArea;
use App\Filament\Resources\ServiceAreas\Pages\ListServiceAreas;
use App\Filament\Resources\ServiceAreas\Schemas\ServiceAreaForm;
use App\Filament\Resources\ServiceAreas\Tables\ServiceAreasTable;
use App\Models\ServiceArea;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ServiceAreaResource extends Resource
{
    protected static ?string $model = ServiceArea::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|\UnitEnum|null $navigationGroup = 'Services';

    protected static ?int $navigationSort = 61;

    protected static ?string $navigationLabel = 'Service areas';

    public static function form(Schema $schema): Schema
    {
        return ServiceAreaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServiceAreasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceAreas::route('/'),
            'create' => CreateServiceArea::route('/create'),
            'edit' => EditServiceArea::route('/{record}/edit'),
        ];
    }
}
