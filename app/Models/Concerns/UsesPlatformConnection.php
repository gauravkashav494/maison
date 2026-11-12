<?php

namespace App\Models\Concerns;

/**
 * Platform data (clients, accounts, customers, settings…): always on the default connection.
 *
 * Without this, a relation from a client row (an order in a client database) to a platform
 * model would inherit the client connection and look for e.g. `customers` in the client
 * database. Naming the connection explicitly keeps cross-database relations pointed home.
 */
trait UsesPlatformConnection
{
    public function getConnectionName(): ?string
    {
        return $this->connection ?? config('database.default');
    }

    public function initializeUsesPlatformConnection(): void
    {
        $this->connection ??= config('database.default');
    }
}
