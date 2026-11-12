<?php

namespace App\Models\Concerns;

/**
 * Marks a row as changed for the local sync agent.
 *
 * A row is due for sending while sync_version is ahead of sync_acked_version. A counter is used
 * rather than updated_at so an edit made in the same second as an acknowledgement is not lost.
 * Only the fields in SYNC_TRACKED count, so fields owned by the local system do not echo back.
 */
trait TracksSyncVersion
{
    private static bool $syncTrackingPaused = false;

    public static function bootTracksSyncVersion(): void
    {
        static::updated(function ($model) {
            if (static::$syncTrackingPaused || ! array_intersect(array_keys($model->getChanges()), static::SYNC_TRACKED)) {
                return;
            }

            $model->newQueryWithoutScopes()->whereKey($model->getKey())->toBase()->increment('sync_version');
        });
    }

    /** Apply a change that came from the local system, so it is not sent straight back. */
    public static function withoutSyncTracking(callable $callback): mixed
    {
        $previous = static::$syncTrackingPaused;
        static::$syncTrackingPaused = true;

        try {
            return $callback();
        } finally {
            static::$syncTrackingPaused = $previous;
        }
    }
}
