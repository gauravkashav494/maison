<?php

namespace App\Filament\Support;

use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Throwable;

use function Livewire\on;

/**
 * "Created at" and "Updated at" in the column menu of every table, hidden until switched on.
 *
 * Set once here (AppServiceProvider) rather than in each table. A table builds its columns as
 * its Livewire component boots, so this runs right after (Livewire's own lifecycle listeners are
 * registered first, by its package provider) and adds what the table lacks:
 *  - only for a table of a model that keeps timestamps;
 *  - a table that already shows the column under its own name ("Placed", "Joined") keeps it;
 *  - dashboard widgets are left alone: they have no column menu.
 * The column menu is then set up again, so it lists the new columns.
 */
class TimestampColumns
{
    public static function register(): void
    {
        foreach (['mount', 'hydrate'] as $event) {
            on($event, function ($component) {
                if ($component instanceof HasTable && ! $component instanceof Widget) {
                    self::addTo($component);
                }
            });
        }
    }

    private static function addTo(HasTable $component): void
    {
        try {
            $table = $component->getTable();
        } catch (Throwable) {
            return; // not built on this request
        }

        $model = $table->getModel();
        $instance = is_string($model) && is_subclass_of($model, Model::class) ? new $model : null;
        if (! $instance?->usesTimestamps() || $table->hasColumnsLayout()) {
            return;
        }

        $created = $instance->getCreatedAtColumn();
        $updated = $instance->getUpdatedAtColumn();
        $make = fn (string $name, string $label) => TextColumn::make($name)->label($label)
            ->dateTime('d M Y, H:i')->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
        $newCreated = $created && ! $table->getColumn($created) ? $make($created, 'Created at') : null;
        $newUpdated = $updated && ! $table->getColumn($updated) ? $make($updated, 'Updated at') : null;

        if (! $newCreated && ! $newUpdated) {
            return;
        }

        // Created always comes first: just before the table's own updated column, or just after
        // its own created column; with neither, both go at the end.
        $layout = [];
        $placed = ['created' => $newCreated === null, 'updated' => $newUpdated === null];
        foreach ($table->getColumnsLayout() as $item) {
            $name = $item instanceof Column ? $item->getName() : null;
            if ($newCreated && ! $placed['created'] && $name === $updated) {
                $layout[] = $newCreated;
                $placed['created'] = true;
            }
            $layout[] = $item;
            if ($newUpdated && ! $placed['updated'] && $name === $created) {
                $layout[] = $newUpdated;
                $placed['updated'] = true;
            }
        }
        if (! $placed['created']) {
            $layout[] = $newCreated;
        }
        if (! $placed['updated']) {
            $layout[] = $newUpdated;
        }

        $table->columns($layout);

        // The column menu was set up from the columns as they were: set it up again with these.
        (function () {
            $this->cachedDefaultTableColumnState = null;
            $this->initTableColumnManager();
        })->call($component);
    }
}
