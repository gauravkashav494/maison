<?php

namespace App\Http\Middleware;

use App\Clients\ClientContext;
use App\Clients\ClientDatabase;
use App\Models\SyncToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Selects the client from the agent's token. A client id in the request is never read. */
class AuthenticateSyncAgent
{
    public function __construct(
        private readonly ClientContext $context,
        private readonly ClientDatabase $databases,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        $token = $plain ? SyncToken::active()->where('token_hash', SyncToken::hash($plain))->first() : null;
        $client = $token?->client;

        if (! $client || ! $client->is_active) {
            return response()->json(['message' => 'Invalid sync token.'], 401);
        }

        $this->context->resolveForPublic($client, 'sync_token');
        $this->databases->use($client);
        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        return $next($request);
    }
}
