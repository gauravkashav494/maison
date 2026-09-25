<?php

namespace App\Rules;

use App\Admin\StoreQuota;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Rejects an upload that would take the store past its storage limit. Applied to every
 * admin image field (Fields::image), so the check runs server-side on the uploaded file
 * itself — hiding the field would not be enough.
 */
class WithinStoreStorage implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $quota = StoreQuota::current();
        if (! $quota || $quota->storageLimit() === null) {
            return;
        }

        $size = $value instanceof UploadedFile ? $value->getSize() : 0;
        if ($quota->wouldExceedStorage((int) $size)) {
            $fail(sprintf(
                'This store has used %s of its %s storage allowance. Delete some media or ask the platform admin to raise the limit.',
                StoreQuota::formatBytes($quota->storageUsed()),
                StoreQuota::formatBytes($quota->storageLimit()),
            ));
        }
    }
}
