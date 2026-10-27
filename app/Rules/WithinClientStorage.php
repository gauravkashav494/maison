<?php

namespace App\Rules;

use App\Clients\ClientQuota;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Rejects an upload that would take the client past its storage allowance. Applied to every
 * admin image field (Fields::image), so the check runs server-side on the uploaded file
 * itself — a hidden or disabled field would not be enough.
 */
class WithinClientStorage implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $quota = ClientQuota::current();
        if (! $quota || $quota->storageLimit() === null) {
            return;
        }

        $size = $value instanceof UploadedFile ? $value->getSize() : 0;
        if ($quota->wouldExceedStorage((int) $size)) {
            $fail(sprintf(
                'This account has used %s of its %s storage allowance. Delete some media or ask the platform admin to raise the limit.',
                ClientQuota::formatBytes($quota->storageUsed()),
                ClientQuota::formatBytes($quota->storageLimit()),
            ));
        }
    }
}
