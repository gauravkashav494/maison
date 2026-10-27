<?php

namespace App\Models\Concerns;

use App\Clients\ClientDatabase;

/**
 * Client content: lives in the client's own database when isolation is enabled
 * (config/clients.php), and on the default connection otherwise. Either way the rows
 * carry `client_id` and are scoped by it, so isolation does not rest on the connection.
 */
trait UsesClientConnection
{
    public function getConnectionName(): ?string
    {
        return app(ClientDatabase::class)->connection() ?? $this->connection;
    }
}
