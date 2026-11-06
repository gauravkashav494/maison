<?php

namespace App\Models\Concerns;

use App\Contracts\AdminIdentity;
use App\Models\PlatformActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * Records who changed an account or platform record. Only the names of the changed fields are
 * kept, never their values, so a password cannot reach the log. Changes with no signed-in admin
 * (seeders, console commands, sign-in bookkeeping) are not recorded.
 */
trait LogsPlatformActivity
{
    protected static function bootLogsPlatformActivity(): void
    {
        static::created(fn (Model $model) => PlatformActivity::record($model, 'created', static::actor()));
        static::deleted(fn (Model $model) => PlatformActivity::record($model, 'deleted', static::actor()));
        static::updated(function (Model $model) {
            $fields = array_values(array_diff(array_keys($model->getChanges()), ['updated_at', 'remember_token', 'last_login_at']));

            if ($fields !== []) {
                PlatformActivity::record($model, 'updated', static::actor(), $fields);
            }
        });
    }

    private static function actor(): ?AdminIdentity
    {
        $user = auth('admin')->user() ?? auth('client')->user();

        return $user instanceof AdminIdentity ? $user : null;
    }
}
