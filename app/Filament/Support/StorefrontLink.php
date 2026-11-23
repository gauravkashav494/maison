<?php

namespace App\Filament\Support;

use App\Clients\ClientContext;
use App\Models\Client;
use Illuminate\Database\Eloquent\Model;

/**
 * A record's page on its own client's storefront. route() would point at the admin host, so the
 * link is built from the client's public address instead.
 */
class StorefrontLink
{
    public static function to(Model $record, string $path): ?string
    {
        $client = filled($record->client_id ?? null)
            ? Client::find($record->client_id)
            : app(ClientContext::class)->client();

        return $client ? rtrim($client->publicUrl(), '/').'/'.ltrim($path, '/') : null;
    }
}
