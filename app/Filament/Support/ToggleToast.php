<?php

namespace App\Filament\Support;

use App\Support\Activity\ActivityDescriber;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * The toast after a table toggle is switched, as a sentence naming the record:
 * 'Product "Linen Shirt" is now visible on the storefront.'
 *
 * Set for every ToggleColumn in AppServiceProvider. A column with its own afterStateUpdated()
 * replaces that callback, so it calls this itself.
 */
class ToggleToast
{
    /**
     * What switching on / off means, by the column's label. A label not listed here reads
     * "Visible turned on" etc.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const PHRASES = [
        'Visible on storefront' => ['is now visible on the storefront', 'is now hidden from the storefront'],
        'Active' => ['is now active', 'is now inactive'],
        'Published' => ['is now published', 'is now unpublished'],
        'Approved' => ['is now approved and shown on the storefront', 'is no longer approved and is hidden from the storefront'],
        'Featured' => ['is now featured', 'is no longer featured'],
        'Popular' => ['is now marked as popular', 'is no longer marked as popular'],
        'Lead story' => ['is now the lead story', 'is no longer the lead story'],
        'Menu & homepage' => ['now shows in the navigation and homepage grid', 'no longer shows in the navigation and homepage grid'],
    ];

    public static function send(ToggleColumn $column, mixed $state): void
    {
        Notification::make()
            ->success()
            ->title(static::message($column->getLabel(), (bool) $state, $column->getRecord()))
            ->send();
    }

    public static function message(string $label, bool $on, ?Model $record): string
    {
        $subject = $record ? ucfirst(app(ActivityDescriber::class)->reference($record)) : null;
        $phrase = self::PHRASES[$label][$on ? 0 : 1] ?? null;

        if ($subject === null) {
            return $label.' turned '.($on ? 'on' : 'off');
        }

        return $phrase
            ? $subject.' '.$phrase.'.'
            : $subject.': '.$label.' turned '.($on ? 'on' : 'off').'.';
    }
}
