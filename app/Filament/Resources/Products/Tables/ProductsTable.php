<?php

namespace App\Filament\Resources\Products\Tables;

use App\Filament\Support\RowActions;
use App\Models\Collection;
use App\Models\Product;
use App\Support\Media;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    /** Filter option for products that belong to no collection. */
    private const NO_COLLECTION = 'none';

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->state(fn (Product $record) => Media::url($record->images[0] ?? null))
                    ->square()
                    ->imageSize(48),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Product $record) => $record->sku),
                TextColumn::make('category.name')->sortable()->badge()->color('gray'),
                // A product can sit in several collections; off by default, switch it on from the column menu.
                TextColumn::make('collections.name')->label('Collections')->badge()->color('gray')
                    ->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('price')
                    ->formatStateUsing(fn (int $state) => money($state))
                    ->sortable()
                    ->description(fn (Product $record) => $record->compare_at_price ? 'was '.money($record->compare_at_price) : null),
                TextColumn::make('stock')->sortable()->alignRight(),
                IconColumn::make('is_new')->label('New')->boolean()->toggleable(),
                IconColumn::make('is_best_seller')->label('Best seller')->boolean()->toggleable(),
                ToggleColumn::make('is_active')->label('Visible'),
                TextColumn::make('updated_at')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('category')->relationship('category', 'name')->preload(),
                // Products in any of the chosen collections, or in none at all (not merchandised anywhere).
                SelectFilter::make('collections')
                    ->label('Collection')
                    ->multiple()
                    ->native(false)
                    ->options(fn () => [self::NO_COLLECTION => 'Not in any collection']
                        + Collection::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data) {
                        $values = array_filter((array) ($data['values'] ?? []), 'filled');
                        if ($values === []) {
                            return $query;
                        }
                        $ids = array_values(array_diff($values, [self::NO_COLLECTION]));
                        $none = in_array(self::NO_COLLECTION, $values, true);

                        return $query->where(fn (Builder $q) => $q
                            ->when($ids, fn (Builder $q) => $q->orWhereHas('collections', fn (Builder $c) => $c->whereKey($ids)))
                            ->when($none, fn (Builder $q) => $q->orWhereDoesntHave('collections')));
                    }),
                TernaryFilter::make('is_active')->label('Visible'),
                TernaryFilter::make('is_new')->label('New arrival'),
                TernaryFilter::make('is_best_seller')->label('Best seller'),
            ])
            ->recordActions([RowActions::group([ViewAction::make(), EditAction::make(), ...RowActions::trash()])])
            ->toolbarActions([
                BulkActionGroup::make(RowActions::trashBulk()),
            ]);
    }
}
