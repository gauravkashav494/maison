<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class Media
{
    /** Resolve a stored path or absolute URL to a public URL. */
    public static function url(?string $path, ?string $fallback = null): ?string
    {
        if (blank($path)) {
            return $fallback;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    /** Unsplash helper used by seeders. */
    public static function unsplash(string $id, int $w = 1200, string $extra = ''): string
    {
        return "https://images.unsplash.com/photo-{$id}?w={$w}&q=80&auto=format&fit=crop{$extra}";
    }
}
