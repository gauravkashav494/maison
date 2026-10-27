<?php

namespace App\Policies;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Contracts\AdminIdentity;
use Illuminate\Database\Eloquent\Model;

/**
 * Base authorization for every content model in both admin panels.
 *
 * Module access depends on the role and on the kind of store the client runs (catalogue vs
 * service); record actions additionally verify that the row belongs to the client — and the
 * store — the session works in. This runs on top of the ClientOwned and AdminStoreScope
 * global scopes, so a row from another client is both invisible and forbidden: an id taken
 * from someone else's URL gives a 404 from the scope, and would still be refused here.
 *
 * One tiny subclass per model (app/Policies/Scoped) sets $model; AppServiceProvider
 * registers them.
 */
abstract class StoreScopedPolicy
{
    /** @var class-string<Model> */
    protected string $model;

    public function __construct(
        protected readonly StoreContext $context,
        protected readonly ClientContext $clients,
    ) {}

    public function viewAny(AdminIdentity $user): bool
    {
        return $this->context->allowsModel($this->model);
    }

    public function view(AdminIdentity $user, Model $record): bool
    {
        return $this->owns($record);
    }

    public function create(AdminIdentity $user): bool
    {
        return $this->context->allowsModel($this->model);
    }

    public function update(AdminIdentity $user, Model $record): bool
    {
        return $this->owns($record);
    }

    public function delete(AdminIdentity $user, Model $record): bool
    {
        return $this->owns($record);
    }

    public function deleteAny(AdminIdentity $user): bool
    {
        return $this->context->allowsModel($this->model); // each selected row is still checked via the scoped query
    }

    public function restore(AdminIdentity $user, Model $record): bool
    {
        return $this->owns($record);
    }

    public function forceDelete(AdminIdentity $user, Model $record): bool
    {
        return $this->owns($record);
    }

    public function reorder(AdminIdentity $user): bool
    {
        return $this->context->allowsModel($this->model);
    }

    protected function owns(Model $record): bool
    {
        if (! $this->context->allowsModel($this->model)) {
            return false;
        }

        // The owning client decides first: a row of another client is never writable, whatever
        // template it carries.
        if (! $this->clients->owns($record->client_id ?? null)) {
            return false;
        }

        $column = method_exists($record, 'templateColumn') ? $record::templateColumn() : 'template';

        return $this->context->allowsTemplate($record->{$column});
    }
}
