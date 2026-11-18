<?php

namespace App\Models\Concerns;

use App\Models\PlatformActivity;
use App\Support\Activity\ActivityLog;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Writes an activity-log line whenever a signed-in panel account adds, changes, trashes,
 * restores or permanently deletes the record (App\Support\Activity\ActivityLog).
 *
 * Every client content model gets it through BelongsToClient, so a new screen is logged without
 * opting in; account and platform records use it directly. Passwords and other secrets are
 * named, never stored. Changes with no signed-in panel account (storefront shoppers, seeders,
 * console commands) are not recorded.
 */
trait LogsActivity
{
    protected static function bootLogsActivity(): void
    {
        $log = fn () => app(ActivityLog::class);

        static::created(fn ($model) => $log()->created($model));
        static::updated(fn ($model) => $log()->updated($model));
        static::deleted(fn ($model) => $log()->deleted($model));
        if (method_exists(static::class, 'restored')) {
            // A restore also saves deleted_at; that update has nothing left to report.
            static::restored(fn ($model) => $log()->restored($model));
        }
    }

    /** This record's own history, newest first. */
    public function activities(): HasMany
    {
        return $this->hasMany(PlatformActivity::class, 'subject_id')
            ->where('subject_type', class_basename($this))
            ->latest('id');
    }
}
