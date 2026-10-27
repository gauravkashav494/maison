<?php

namespace App\Clients\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Admin-side isolation: every query on a client-owned model is limited to one client.
 *
 * Filament resolves records, table rows, relation selects, bulk actions, exports and
 * navigation badges through the model query, so a row belonging to another client does not
 * exist for this session — an id guessed from a URL gives a 404, not a 403 leak. Rows with
 * no client (platform-wide) are visible only to a SuperAdmin, which is a separate scope.
 */
class ClientOwned implements Scope
{
    public const NAME = 'client_owned';

    public function __construct(private readonly int $clientId) {}

    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->qualifyColumn('client_id'), $this->clientId);
    }
}
