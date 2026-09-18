<?php

namespace App\Templates\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts catalogue rows to those visible in the template rendering the request.
 * A null `template` column means "visible in every template". Registered on
 * storefront requests only, so the admin always sees the full catalogue.
 */
class TemplateVisibility implements Scope
{
    public const NAME = 'template_visibility';

    public function __construct(private readonly string $template) {}

    public function apply(Builder $builder, Model $model): void
    {
        $column = $model->qualifyColumn('template');
        $builder->where(fn (Builder $q) => $q->whereNull($column)->orWhere($column, $this->template));
    }
}
