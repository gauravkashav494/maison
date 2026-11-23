<?php

namespace App\Rules;

use App\Models\Category;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a parent that is the category itself or one of its sub-categories (at any depth),
 * which would make a loop such as A → B → A. The form's dropdown already hides those options;
 * this is the server-side check for stale forms or two people editing at once.
 */
class NotOwnDescendantCategory implements ValidationRule
{
    public function __construct(private ?Category $category) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value) || ! $this->category?->exists) {
            return;
        }

        $parentId = (int) $value;
        if ($parentId === $this->category->getKey() || in_array($parentId, $this->category->descendantIds(), true)) {
            $fail("A category can't be placed under itself or one of its own child categories.");
        }
    }
}
