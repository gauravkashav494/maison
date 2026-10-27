<?php

namespace App\Clients;

use App\Contracts\AdminIdentity;
use App\Models\Client;

/**
 * Which client the current request works in, and how that was decided.
 *
 * This is the single place a client is selected, and it is always decided server-side:
 *
 *   - a client's staff account is bound to the client on its own row (`locked`); a client id
 *     in the request is ignored, so there is no way to switch client from the outside;
 *   - a SuperAdmin may choose a client, and the choice is validated against the clients table
 *     before it is used (an unknown or inactive id selects nothing rather than falling through
 *     to another client's data);
 *   - a storefront request resolves the client from the host (or the entry-link cookie), which
 *     only ever selects public content.
 *
 * The resolved client drives three things at once: the database connection content models use,
 * the `client_id` global scope, and the policies. None of them is sufficient on its own, and
 * all three are applied.
 */
class ClientContext
{
    public const SESSION_KEY = 'admin.client';

    private ?Client $client = null;

    private bool $locked = false;

    private bool $superAdmin = false;

    private bool $resolved = false;

    private string $source = 'none';

    /** Signed-in admin: a client's staff is locked to its own client, a SuperAdmin may choose. */
    public function resolveForIdentity(?AdminIdentity $identity, int|string|null $chosen = null): void
    {
        $this->reset();
        $this->resolved = true;

        if (! $identity || ! $identity->isActiveAccount()) {
            return;
        }

        if ($identity->isSuperAdmin()) {
            $this->superAdmin = true;
            $this->client = $this->validated($chosen);
            $this->source = $this->client ? 'super_admin_choice' : 'super_admin_all';

            return;
        }

        // Staff account: the client comes from the account row, never from the request.
        $this->client = $identity->clientId() ? Client::active()->find($identity->clientId()) : null;
        $this->locked = true;
        $this->source = 'account';
    }

    /** Storefront request: the client is whichever one this host (or entry link) belongs to. */
    public function resolveForPublic(?Client $client, string $source = 'host'): void
    {
        $this->reset();
        $this->resolved = true;
        $this->client = $client && $client->is_active ? $client : null;
        $this->locked = true;
        $this->source = $this->client ? $source : 'none';
    }

    public function forget(): void
    {
        $this->reset();
    }

    private function reset(): void
    {
        $this->client = null;
        $this->locked = false;
        $this->superAdmin = false;
        $this->resolved = false;
        $this->source = 'none';
    }

    /** Only an id that names an existing, active client is ever accepted. */
    private function validated(int|string|null $chosen): ?Client
    {
        if ($chosen === null || $chosen === '' || $chosen === 'all') {
            return null;
        }

        return Client::active()->find($chosen);
    }

    public function client(): ?Client
    {
        return $this->client;
    }

    public function id(): ?int
    {
        return $this->client?->id;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    /** True when the client cannot be changed by this session (staff and storefront requests). */
    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function isSuperAdmin(): bool
    {
        return $this->superAdmin;
    }

    /** How the client was selected — recorded so the choice can be audited. */
    public function source(): string
    {
        return $this->source;
    }

    /** May this session touch a row owned by the given client? */
    public function owns(int|string|null $clientId): bool
    {
        if ($clientId === null) {
            return $this->superAdmin; // platform-wide rows belong to the SuperAdmin
        }

        return $this->superAdmin || ((int) $clientId === $this->id() && $this->id() !== null);
    }

    public function label(): string
    {
        return $this->client?->name ?? ($this->superAdmin ? 'All clients' : 'No client');
    }

    public function templateId(): ?string
    {
        return $this->client?->templateId();
    }
}
