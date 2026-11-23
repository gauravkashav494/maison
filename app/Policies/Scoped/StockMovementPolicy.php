<?php

namespace App\Policies\Scoped;

use App\Contracts\AdminIdentity;
use App\Models\StockMovement;
use App\Policies\StoreScopedPolicy;
use Illuminate\Database\Eloquent\Model;

/** The stock history is read-only: movements are written by the stock itself, never by hand. */
class StockMovementPolicy extends StoreScopedPolicy
{
    protected string $model = StockMovement::class;

    public function create(AdminIdentity $user): bool
    {
        return false;
    }

    public function update(AdminIdentity $user, Model $record): bool
    {
        return false;
    }

    public function delete(AdminIdentity $user, Model $record): bool
    {
        return false;
    }

    public function deleteAny(AdminIdentity $user): bool
    {
        return false;
    }

    public function restore(AdminIdentity $user, Model $record): bool
    {
        return false;
    }

    public function forceDelete(AdminIdentity $user, Model $record): bool
    {
        return false;
    }

    public function restoreAny(AdminIdentity $user): bool
    {
        return false;
    }

    public function forceDeleteAny(AdminIdentity $user): bool
    {
        return false;
    }

    public function reorder(AdminIdentity $user): bool
    {
        return false;
    }
}
