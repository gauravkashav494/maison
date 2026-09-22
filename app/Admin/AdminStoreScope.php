<?php

namespace App\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope applied inside the admin panel: limits every query on a template-owned
 * model to the store the session works in. Filament resolves records, table rows, relation
 * selects, bulk actions and navigation badges through the model query, so an id from another
 * store simply does not exist here (404). Shared rows (template null) are visible only when
 * the session is not narrowed to a store.
 */
class AdminStoreScope implements Scope
{
    public const NAME = 'admin_store';

    public function __construct(private readonly string $template) {}

    public function apply(Builder $builder, Model $model): void
    {
        $column = method_exists($model, 'templateColumn') ? $model::templateColumn() : 'template';
        $builder->where($model->qualifyColumn($column), $this->template);
    }
}
