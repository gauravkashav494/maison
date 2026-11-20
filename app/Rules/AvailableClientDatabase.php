<?php

namespace App\Rules;

use App\Clients\ClientDatabase;
use App\Models\Client;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The client form's "Database name": the name the client will actually use — the one typed in,
 * or client_<subdomain> when it is left blank — must be free (ClientDatabase::problemWithName).
 *
 * Implicit, so it also runs on a blank field: a blank name still resolves to a generated one,
 * and that one can clash with a name another client typed in.
 */
class AvailableClientDatabase implements ValidationRule
{
    public bool $implicit = true;

    public function __construct(private ?Client $record, private ?string $subdomain) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $subdomain = $this->record?->subdomain ?? $this->subdomain;
        if (blank($value) && blank($subdomain)) {
            return; // nothing to resolve yet; the subdomain field reports that it is required
        }

        // Left empty while editing keeps the database the client has now.
        $client = new Client(['subdomain' => $subdomain, 'database' => filled($value) ? trim((string) $value) : $this->record?->database]);

        if ($problem = app(ClientDatabase::class)->problemWithName($client->databaseName(), $this->record)) {
            $fail($problem);
        }
    }
}
