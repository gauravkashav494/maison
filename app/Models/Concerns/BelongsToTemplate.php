<?php

namespace App\Models\Concerns;

use App\Admin\StoreContext;

/**
 * Content that can be limited to one storefront template. A null value in the
 * template column means the record is shared by every template.
 *
 * Inside the admin, a store owner's session is locked to their store: whatever is saved
 * gets that template, so a row can never be moved to (or created in) another store.
 */
trait BelongsToTemplate
{
    public static function bootBelongsToTemplate(): void
    {
        static::saving(function ($model) {
            $context = app(StoreContext::class);
            if (! $context->isResolved() || ! $context->template()) {
                return;
            }
            $column = static::templateColumn();
            if ($context->isLocked() || blank($model->{$column})) {
                $model->{$column} = $context->template();
            }
        });
    }

    /** Column holding the owning template id (pages use a different name). */
    public static function templateColumn(): string
    {
        return 'template';
    }

    public function templateId(): ?string
    {
        return $this->{static::templateColumn()};
    }
}
