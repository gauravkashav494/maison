<?php

namespace App\Sync;

/** The outcome of one record in a batch; one bad row never blocks the rest. */
final class SyncResult
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const UNCHANGED = 'unchanged';

    public const REJECTED = 'rejected';

    public static function of(mixed $key, string $result, array $extra = []): array
    {
        return ['key' => $key, 'result' => $result] + $extra;
    }

    public static function rejected(mixed $key, string $error): array
    {
        return self::of($key, self::REJECTED, ['error' => $error]);
    }
}
