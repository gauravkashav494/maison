<?php

namespace App\Policies;

use App\Admin\StoreContext;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base authorization for every template-owned admin model. Module access depends on the
 * role and the store's template type (catalogue vs service); record actions additionally
 * verify that the row belongs to the store the session works in. This runs on top of the
 * AdminStoreScope global scope, so a cross-store id is both invisible and forbidden.
 *
 * One tiny subclass per model (app/Policies/Scoped) sets $model; they are registered in
 * AuthServiceProvider.
 */
abstract class StoreScopedPolicy
{
    /** @var class-string<Model> */
    protected string $model;

    public function __construct(protected readonly StoreContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->allowsModel($this->model);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->owns($record);
    }

    public function create(User $user): bool
    {
        return $this->context->allowsModel($this->model);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->owns($record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->owns($record);
    }

    public function deleteAny(User $user): bool
    {
        return $this->context->allowsModel($this->model); // each selected row is still checked via the scoped query
    }

    public function restore(User $user, Model $record): bool
    {
        return $this->owns($record);
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return $this->owns($record);
    }

    public function reorder(User $user): bool
    {
        return $this->context->allowsModel($this->model);
    }

    protected function owns(Model $record): bool
    {
        if (! $this->context->allowsModel($this->model)) {
            return false;
        }
        $column = method_exists($record, 'templateColumn') ? $record::templateColumn() : 'template';

        return $this->context->allowsTemplate($record->{$column});
    }
}
