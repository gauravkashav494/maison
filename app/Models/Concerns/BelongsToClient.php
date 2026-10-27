<?php

namespace App\Models\Concerns;

use App\Clients\ClientContext;
use App\Models\Client;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Content owned by one client.
 *
 * Whatever a client's staff saves is stamped with their own client id, so a row can never be
 * created in — or moved to — another client, whether the id was omitted, guessed or posted
 * deliberately. A SuperAdmin working inside a client gets the same stamp; working across all
 * clients they may leave it null, which marks a platform-wide row.
 */
trait BelongsToClient
{
    public static function bootBelongsToClient(): void
    {
        static::saving(function ($model) {
            $context = app(ClientContext::class);
            if (! $context->isResolved() || ! $context->id()) {
                return;
            }
            // A locked session (client staff, storefront) always wins over a submitted value.
            if ($context->isLocked() || blank($model->client_id)) {
                $model->client_id = $context->id();
            }
        });
    }

    public function client(): BelongsTo
    {
        // Client is platform data: it stays on the default connection even when this row lives
        // in a client database, so the relation crosses databases by design.
        return $this->belongsTo(Client::class);
    }

    public function clientId(): ?int
    {
        return $this->client_id;
    }
}
