<?php

namespace App\Rules;

use App\Clients\ClientQuota;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Enforces the client's product-image allowance (50 by default) when a product is saved.
 *
 * The rule is given the whole image list for the product being saved and counts it against
 * every other product's images, so it cannot be bypassed by uploading files one at a time,
 * by posting the field directly, or by re-using an image that is already counted. Images the
 * save removes free their slot in the same check.
 */
class WithinClientImageLimit implements ValidationRule
{
    /**
     * Run even when the field itself is empty: photos added elsewhere in the form (under a
     * colour, or on a combination) still count, and a product may have only those.
     */
    public bool $implicit = true;

    /**
     * @param  array<string>  $replacing  image paths the record holds today and that this save may drop
     * @param  array<int, mixed>  $extra  other images saved with it (a product's combination photos), in the same form
     */
    public function __construct(private readonly array $replacing = [], private readonly array $extra = []) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $quota = ClientQuota::current();
        if (! $quota || $quota->imageLimit() === null) {
            return;
        }

        // The field state mixes paths already on disk (strings) with files being uploaded now
        // (objects); each of the latter is a new image whatever it is eventually named.
        $paths = [];
        $added = 0;
        foreach ([...(is_array($value) ? array_values($value) : [$value]), ...$this->extra] as $item) {
            if (is_string($item) && filled($item)) {
                $paths[] = $item;
            } elseif ($item !== null && $item !== '') {
                $added++;
            }
        }

        if ($quota->wouldExceedImages($paths, $this->replacing, $added)) {
            $fail(sprintf(
                'This account is limited to %s product images and is using %s. Remove some images or ask the platform admin to raise the limit.',
                number_format($quota->imageLimit()),
                number_format($quota->imageCount()),
            ));
        }
    }
}
