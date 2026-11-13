<?php

namespace App\Models;

use App\Contracts\AdminIdentity;
use App\Models\Concerns\UsesPlatformConnection;
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

    public static function record(Model $subject, string $action, ?AdminIdentity $actor, array $fields = []): void
    {
        if (! $actor) {
            return;
        }

        static::create([
            'actor' => $actor->name ?? $actor->email ?? 'Unknown',
            'actor_type' => $actor->isSuperAdmin() ? 'Super admin' : 'Client staff',
            'action' => $action,
            'subject_type' => class_basename($subject),
            'subject' => $subject->name ?? $subject->email ?? '#'.$subject->getKey(),
            'client_id' => $subject instanceof Client ? ($action === 'deleted' ? null : $subject->id) : ($subject->client_id ?? null),
            'fields' => $fields ?: null,
        ]);
    }
}
