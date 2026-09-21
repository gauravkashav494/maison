<?php

namespace App\Filament\Resources\ServiceRequests;

use App\Filament\Resources\ServiceRequests\Pages\CreateServiceRequest;
use App\Filament\Resources\ServiceRequests\Pages\EditServiceRequest;
use App\Filament\Resources\ServiceRequests\Pages\ListServiceRequests;
use App\Filament\Resources\ServiceRequests\Schemas\ServiceRequestForm;
use App\Filament\Resources\ServiceRequests\Tables\ServiceRequestsTable;
use App\Models\ServiceRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ServiceRequestResource extends Resource
{
    protected static ?string $model = ServiceRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Services';

    protected static ?int $navigationSort = 64;

    protected static ?string $navigationLabel = 'Bookings & quotes';

    public static function getNavigationBadge(): ?string
    {
        $n = ServiceRequest::where('status', 'new')->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'New requests';
    }

    public static function getModelLabel(): string
    {
        return 'request';
    }

    public static function getPluralModelLabel(): string
    {
        return 'bookings & quotes';
    }

    public static function form(Schema $schema): Schema
    {
        return ServiceRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServiceRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceRequests::route('/'),
            'create' => CreateServiceRequest::route('/create'),
            'edit' => EditServiceRequest::route('/{record}/edit'),
        ];
    }
}
