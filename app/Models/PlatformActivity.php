<?php

namespace App\Models;

use App\Models\Concerns\UsesPlatformConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the activity log: who did what, as a sentence ("changed the price of product
 * "Silk Saree" from ₹4,500.00 to ₹3,999.00"). Written by App\Support\Activity\ActivityLog —
 * for model changes through the LogsActivity trait, for sign-ins, settings and downloads
 * explicitly. Lines are kept for twelve months (`model:prune`, scheduled daily).
 *
 * It lives on the platform connection whatever the client isolation mode, so a client's
 * history outlives its own database and a Super Admin can audit every client in one place.
 */
class PlatformActivity extends Model
{
    use MassPrunable;
    use UsesPlatformConnection;

    public const UPDATED_AT = null;

    public const KEEP_MONTHS = 12;

    protected $guarded = [];

    protected $casts = ['fields' => 'array', 'changes' => 'array'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /** Lines written by this account (a platform account and a client account never share one). */
    public function scopeByActor(Builder $query, Model $actor): Builder
    {
        return $query->where('actor_model', class_basename($actor))->where('actor_id', $actor->getKey());
    }

    /** Lines written by a client's own staff about that client — what the client panel lists. */
    public function scopeForClientStaff(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId)->where('actor_model', 'ClientUser');
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subMonths(self::KEEP_MONTHS));
    }

    /**
     * The sentence, starting with what was done. Lines written before descriptions existed are
     * rebuilt from their action, type and name ("updated Plan "Starter": name, price label").
     */
    public function sentence(): string
    {
        if (filled($this->description)) {
            return $this->description;
        }

        $line = $this->action.' '.strtolower((string) $this->subject_type).' "'.$this->subject.'"';

        return $this->fields ? $line.': '.implode(', ', $this->fields) : $line;
    }
}
