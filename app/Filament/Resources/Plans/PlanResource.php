<?php

namespace App\Filament\Resources\Plans;

use App\Models\Plan;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Plans carry the allowances a client inherits. Pricing is not charged anywhere yet: the
 * price is a label, so a paid tier can be introduced later without touching the limits.
 */
class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Plan')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(60),
                TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true),
                TextInput::make('price_label')->label('Price')->default('₹X')->maxLength(40)
                    ->helperText('Shown as-is. Billing is not implemented, so this is a label only.'),
                TextInput::make('position')->label('Order')->numeric()->default(0),
            ]),
            Section::make('Allowances')->description('Enter 0 for unlimited. Blank falls back to the platform default. A client may override any of these.')->columns(3)->schema([
                TextInput::make('image_limit')->label('Product images')->numeric()->minValue(0)->default(50)
                    ->helperText('The platform default is 50; 0 means unlimited.'),
                TextInput::make('product_limit')->label('Maximum products')->numeric()->minValue(0)->placeholder('Unlimited')->helperText('0 or blank = unlimited.'),
                TextInput::make('storage_limit_mb')->label('Storage (MB)')->numeric()->minValue(0)->placeholder('Unlimited'),
            ]),
            Section::make('Default')->schema([
                Toggle::make('is_default')->label('Use for new clients')->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('bold')->description(fn (Plan $r) => $r->price_label),
                TextColumn::make('image_limit')->label('Images')->alignRight()->placeholder('Unlimited')->numeric(),
                TextColumn::make('product_limit')->label('Products')->alignRight()->placeholder('Unlimited')->numeric(),
                TextColumn::make('storage_limit_mb')->label('Storage (MB)')->alignRight()->placeholder('Unlimited')->numeric(),
                TextColumn::make('clients_count')->counts('clients')->label('Clients')->alignRight(),
                IconColumn::make('is_default')->label('Default')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
