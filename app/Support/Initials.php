<?php

namespace App\Support;

/** Initials for an avatar: first and last word ("Indian Grocery Owner" → "IO"). */
class Initials
{
    public static function for(?string $name): string
    {
        $words = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
        // Skip leading punctuation ("[SYSTEM] Admin") so it does not become a letter.
        $letters = collect(count($words) > 1 ? [reset($words), end($words)] : $words)
            ->map(fn (string $w): string => mb_strtoupper(mb_substr(preg_replace('/^[^\p{L}\p{N}]+/u', '', $w) ?: $w, 0, 1)))
            ->implode('');

        return $letters !== '' ? $letters : '?';
    }
}
