<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Hash;

/**
 * Keeps a readable copy of the password in `password_hint` every time a plain password is
 * assigned (admin forms, storefront sign-up, reset, profile change), before the `hashed`
 * cast turns it into a hash. A value that is already a hash is left alone, so the copy is
 * never overwritten with one. The column is in $hidden so it never leaves through JSON.
 */
trait StoresPasswordHint
{
    public function initializeStoresPasswordHint(): void
    {
        $this->makeHidden('password_hint');
    }

    public function setAttribute($key, $value)
    {
        if ($key === 'password' && is_string($value) && $value !== '' && ! Hash::isHashed($value)) {
            parent::setAttribute('password_hint', $value);
        }

        return parent::setAttribute($key, $value);
    }
}
