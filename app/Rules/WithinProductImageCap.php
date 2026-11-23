<?php

namespace App\Rules;

use App\Filament\Support\Fields;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Keeps one product's gallery to Fields::MAX_IMAGES. The gallery field caps its own files, but
 * photos uploaded under a colour (Variants → Colour → Browse) join the gallery on save
 * (Product::assignColourPhotos), so they are counted with it here.
 */
class WithinProductImageCap implements ValidationRule
{
    /** Run even when the gallery is empty: the colour uploads alone may be over the cap. */
    public bool $implicit = true;

    /** @param  array<int, mixed>  $joining  colour uploads that will join the gallery on save */
    public function __construct(private readonly array $joining = []) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $stored = [];
        $new = 0;
        foreach ([...(is_array($value) ? array_values($value) : [$value]), ...$this->joining] as $item) {
            if (is_string($item) && filled($item)) {
                $stored[$item] = true;
            } elseif ($item !== null && $item !== '') {
                $new++;
            }
        }

        $total = count($stored) + $new;
        if ($total > Fields::MAX_IMAGES) {
            $fail(sprintf(
                'A product can have up to %d images, including photos uploaded under a colour. This one would have %d. Remove some before saving.',
                Fields::MAX_IMAGES,
                $total,
            ));
        }
    }
}
