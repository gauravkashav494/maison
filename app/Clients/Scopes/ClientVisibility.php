<?php

namespace App\Clients\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Storefront isolation: a visitor on a client's site sees that client's rows, plus the
 * platform-wide rows that have no client of their own (client_id null), exactly like the
 * template-null rows the template scope allows.
 */
class ClientVisibility implements Scope
{
    public const NAME = 'client_visibility';

    public function __construct(private readonly int $clientId) {}

    public function apply(Builder $builder, Model $model): void
    {
        $column = $model->qualifyColumn('client_id');
        $builder->where(fn (Builder $query) => $query->whereNull($column)->orWhere($column, $this->clientId));
    }
}
