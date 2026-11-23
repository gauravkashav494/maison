<?php

namespace App\Filament\Support;

use App\Models\ClientUser;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;

/**
 * The `password_hint` column, shown only to a SuperAdmin (on /admin) or a Client Admin
 * (on /client). Admin Staff never see it.
 */
class PasswordHintColumn
{
    public static function make(): TextColumn
    {
        return TextColumn::make('password_hint')
            ->label('Password hint')
            ->placeholder('—')
            ->copyable()
            ->fontFamily('mono')
            ->toggleable()
            ->visible(fn () => static::canView());
    }

    /** The same value on a view page. */
    public static function entry(): TextEntry
    {
        return TextEntry::make('password_hint')
            ->label('Password hint')
            ->placeholder('Not recorded yet — shown after the next password change')
            ->copyable()
            ->fontFamily('mono')
            ->icon('heroicon-m-key')
            ->visible(fn () => static::canView());
    }

    public static function canView(): bool
    {
        $user = filament()->auth()->user();

        return $user?->isSuperAdmin() === true
            || ($user instanceof ClientUser && $user->isClientAdmin());
    }
}
