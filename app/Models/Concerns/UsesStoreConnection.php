<?php

namespace App\Models\Concerns;

use App\Stores\TenantManager;

/**
 * Store content: lives in the store's own database when isolation is enabled
 * (config/stores.php), and on the default connection otherwise.
 */
trait UsesStoreConnection
{
    public function getConnectionName(): ?string
    {
        return app(TenantManager::class)->connection() ?? $this->connection;
    }
}
