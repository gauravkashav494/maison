<?php

namespace App\Models\Concerns;

/**
 * Content that can be limited to one storefront template. A null value in the
 * template column means the record is shared by every template.
 */
trait BelongsToTemplate
{
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
