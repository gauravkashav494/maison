<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use App\Models\Concerns\BelongsToTemplate;
use App\Models\Concerns\UsesClientConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A newsletter sign-up. A subscriber who leaves through their unsubscribe link keeps their row
 * with unsubscribed_at set, so the store can see they opted out and never re-adds them by
 * mistake; a row an admin removes is soft-deleted instead.
 */
class Subscriber extends Model
{
    use BelongsToClient;
    use BelongsToTemplate;
    use SoftDeletes;
    use UsesClientConnection;

    protected $guarded = [];

    protected $hidden = ['unsubscribe_token'];

    protected function casts(): array
    {
        return ['unsubscribed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Subscriber $subscriber) {
            $subscriber->unsubscribe_token ??= Str::random(40);
        });
    }

    /** Still on the list: not unsubscribed (trashed rows are already left out by SoftDeletes). */
    public function scopeSubscribed(Builder $query): void
    {
        $query->whereNull('unsubscribed_at');
    }

    public function isUnsubscribed(): bool
    {
        return $this->unsubscribed_at !== null;
    }

    /**
     * The link to put in every email: opens a confirm page on the client's own storefront host.
     * Pass $storeUrl when building many links for one client (the CSV export) to skip the lookup.
     */
    public function unsubscribeUrl(?string $storeUrl = null): ?string
    {
        if (! $this->unsubscribe_token) {
            return null;
        }
        $storeUrl ??= static::storeUrlFor($this->client_id);

        return rtrim($storeUrl, '/').'/newsletter/unsubscribe/'.$this->unsubscribe_token;
    }

    /** Public storefront address of a client, or the app URL for a platform-wide row. */
    public static function storeUrlFor(?int $clientId): string
    {
        $client = $clientId ? Client::find($clientId) : null;

        return $client ? $client->publicUrl() : (string) config('app.url');
    }
}
