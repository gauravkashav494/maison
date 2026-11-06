<?php

namespace App\Filament\Resources\PlatformActivities;

use App\Models\PlatformActivity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Who changed which account or platform record. Read-only, and it never holds a value. */
class PlatformActivityResource extends Resource
{
    protected static ?string $model = PlatformActivity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'Activity log';

    protected static ?string $modelLabel = 'activity';

    protected static ?string $pluralModelLabel = 'activity log';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('d M Y, H:i')->sortable(),
                TextColumn::make('actor')->label('Who')->searchable()->description(fn (PlatformActivity $r) => $r->actor_type),
                TextColumn::make('action')->badge()->color(fn (string $state) => match ($state) {
                    'created' => 'success',
                    'deleted' => 'danger',
                    default => 'gray',
                }),
                TextColumn::make('subject_type')->label('Type'),
                TextColumn::make('subject')->label('Record')->searchable(),
                TextColumn::make('client.name')->label('Client')->placeholder('—'),
                TextColumn::make('changed')->label('Changed')->placeholder('—')
                    ->state(fn (PlatformActivity $r) => implode(', ', $r->fields ?? [])),
            ])
            ->filters([
                SelectFilter::make('client_id')->label('Client')->relationship('client', 'name'),
                SelectFilter::make('action')->options(['created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted']),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPlatformActivities::route('/')];
    }
}
