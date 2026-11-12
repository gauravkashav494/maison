<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Credential of one client's local sync agent. Only the hash is stored, and the client comes
 * from this row, so the token alone decides whose data a request can reach.
 */
class SyncToken extends Model
{
    protected $guarded = [];

    protected $casts = ['last_used_at' => 'datetime', 'revoked_at' => 'datetime'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /** @return array{0: self, 1: string} the record, and the token (shown this once only) */
    public static function issue(Client $client, string $name): array
    {
        $plain = 'evs_'.Str::random(48);

        return [static::create(['client_id' => $client->id, 'name' => $name, 'token_hash' => static::hash($plain)]), $plain];
    }
}
