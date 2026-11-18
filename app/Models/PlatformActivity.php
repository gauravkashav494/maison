<?php

namespace App\Models;

use App\Contracts\AdminIdentity;
use App\Models\Concerns\UsesPlatformConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of the platform activity log, written by the LogsPlatformActivity trait. */
class PlatformActivity extends Model
{
    use UsesPlatformConnection;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['fields' => 'array'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /** Lines written by this account (a platform account and a client account never share one). */
    public function scopeByActor(Builder $query, Model $actor): Builder
    {
        return $query->where('actor_model', class_basename($actor))->where('actor_id', $actor->getKey());
    }

    public static function record(Model $subject, string $action, ?AdminIdentity $actor, array $fields = []): void
    {
        if (! $actor) {
            return;
        }

        static::create([
            'actor' => $actor->name ?? $actor->email ?? 'Unknown',
            'actor_type' => $actor->isSuperAdmin() ? 'Super admin' : ($actor->isPlatformAccount() ? 'Platform staff' : 'Client staff'),
            'actor_model' => $actor instanceof Model ? class_basename($actor) : null,
            'actor_id' => $actor instanceof Model ? $actor->getKey() : null,
            'action' => $action,
            'subject_type' => class_basename($subject),
            'subject' => $subject->name ?? $subject->email ?? '#'.$subject->getKey(),
            'client_id' => $subject instanceof Client ? (str_starts_with($action, 'deleted') ? null : $subject->id) : ($subject->client_id ?? null),
            'fields' => $fields ?: null,
        ]);
    }
}
