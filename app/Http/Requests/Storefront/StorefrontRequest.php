<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Base for every storefront form.
 *
 * The storefront deliberately has no browser-side validation (forms are `novalidate`, no
 * `required`/`pattern` attributes, no JS checks), so these requests are the only gate a value
 * passes through. The shared field rules below keep the same field (a name, a phone number, a
 * postal code…) validated the same way on every form, with the same human-readable messages.
 */
abstract class StorefrontRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Lower-cases the given email fields before they are validated and stored. */
    protected array $lowercase = ['email'];

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach ($this->lowercase as $key) {
            if (is_string($this->input($key))) {
                $merge[$key] = mb_strtolower(trim($this->input($key)));
            }
        }
        if (is_string($this->input('phone')) && ($phone = static::normalisePhone($this->input('phone'))) !== null) {
            $merge['phone'] = $phone;
        }
        $merge && $this->merge($merge);
    }

    /**
     * A phone number as its 10 digits: spaces, hyphens, dots and brackets are dropped, and so is
     * a +91 / 91 country code or a leading 0 — "+91 98765-43210" and "098765 43210" both become
     * "9876543210". Anything else (letters, too few or too many digits) is returned as typed, so
     * the rule rejects it instead of it being quietly changed. Null for an empty value.
     */
    public static function normalisePhone(string $value): ?string
    {
        $clean = preg_replace('/[\s\-().]/', '', trim($value));
        if ($clean === '') {
            return null;
        }
        if (! preg_match('/^\+?\d+$/', $clean)) {
            return trim($value);
        }
        $digits = ltrim($clean, '+');
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return strlen($digits) === 10 ? $digits : trim($value);
    }

    // ---- Shared field rules ------------------------------------------------

    /** A person's name: letters (any script), spaces, dots, apostrophes and hyphens. */
    protected function personName(bool $required = true, int $max = 120): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'min:2', "max:{$max}", "regex:/^[\pL\pM][\pL\pM\s.'\-]*$/u"];
    }

    protected function email(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'email:rfc,filter', 'max:190'];
    }

    /** Exactly 10 digits (after normalisePhone() has removed spacing and any +91 or leading 0). */
    protected function phone(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'regex:/^\d{10}$/'];
    }

    /** Address lines: anything printable, but at least a few characters. */
    protected function addressLine(bool $required = true, int $max = 190): array
    {
        return [$required ? 'required' : 'nullable', 'string', $required ? 'min:3' : 'min:1', "max:{$max}"];
    }

    /** City, state or country: letters, spaces, dots, apostrophes, hyphens and ampersands. */
    protected function place(bool $required = true, int $max = 80): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'min:2', "max:{$max}", "regex:/^[\pL\pM][\pL\pM\s.'&\-]*$/u"];
    }

    /** A 6-digit PIN code for India, otherwise a generic 3–10 character postal code. */
    protected function postalCode(?string $country): array
    {
        $india = in_array(mb_strtolower(trim((string) $country)), ['india', 'in', 'bharat'], true);

        return ['required', 'string', $india ? 'regex:/^[1-9][0-9]{5}$/' : 'regex:/^[A-Za-z0-9][A-Za-z0-9\s\-]{1,8}[A-Za-z0-9]$/'];
    }

    protected function newPassword(): array
    {
        return ['required', 'string', 'max:255', 'confirmed', Password::min(8)->letters()->numbers()];
    }

    protected function message(bool $required = true, int $min = 10, int $max = 3000): array
    {
        return [$required ? 'required' : 'nullable', 'string', "min:{$min}", "max:{$max}"];
    }

    // ---- Messages ----------------------------------------------------------

    public function messages(): array
    {
        return [
            'required' => 'Please enter your :attribute.',
            'required_without' => 'Please enter your :attribute.',
            'email' => 'Please enter a valid email address, like name@example.com.',
            'min' => 'The :attribute must be at least :min characters.',
            'max' => 'The :attribute may not be more than :max characters.',
            'confirmed' => 'The :attribute confirmation does not match.',
            'boolean' => 'Please choose a valid option for :attribute.',
            'in' => 'Please choose a valid :attribute.',
            'exists' => 'The selected :attribute is not available.',
            'integer' => 'The :attribute must be a whole number.',
            'prohibited' => 'Your submission could not be accepted.',
            'name.regex' => 'The :attribute may only contain letters, spaces, dots, apostrophes and hyphens.',
            'shipping_name.regex' => 'The :attribute may only contain letters, spaces, dots, apostrophes and hyphens.',
            'phone.regex' => 'Please enter a 10-digit :attribute, e.g. 9876543210.',
            'postal_code.regex' => $this->postalMessage('country'),
            'shipping_postal_code.regex' => $this->postalMessage('shipping_country'),
            'city.regex' => 'The :attribute may only contain letters, spaces and . \' - &.',
            'state.regex' => 'The :attribute may only contain letters, spaces and . \' - &.',
            'country.regex' => 'The :attribute may only contain letters, spaces and . \' - &.',
            'shipping_city.regex' => 'The :attribute may only contain letters, spaces and . \' - &.',
            'shipping_state.regex' => 'The :attribute may only contain letters, spaces and . \' - &.',
            'shipping_country.regex' => 'The :attribute may only contain letters, spaces and . \' - &.',
            // Last: the patterns above are matched in order, so this only catches the rest.
            '*.regex' => 'The :attribute format is not valid.',
        ];
    }

    private function postalMessage(string $countryField): string
    {
        return in_array(mb_strtolower(trim((string) $this->input($countryField))), ['india', 'in', 'bharat'], true)
            ? 'Please enter a valid 6-digit PIN code.'
            : 'Please enter a valid :attribute.';
    }

    public function attributes(): array
    {
        return [
            'name' => 'name',
            'email' => 'email address',
            'phone' => 'phone number',
            'password' => 'password',
            'current_password' => 'current password',
            'label' => 'address label',
            'line1' => 'address',
            'line2' => 'apartment or landmark',
            'city' => 'city',
            'state' => 'state',
            'postal_code' => 'postal code',
            'country' => 'country',
            'shipping_name' => 'full name',
            'shipping_line1' => 'address',
            'shipping_line2' => 'apartment or landmark',
            'shipping_city' => 'city',
            'shipping_state' => 'state',
            'shipping_postal_code' => 'postal code',
            'shipping_country' => 'country',
            'shipping_method' => 'delivery method',
            'payment_method' => 'payment method',
            'notes' => 'order notes',
            'number' => 'order number',
            'code' => 'code',
            'subject' => 'topic',
            'message' => 'message',
            'title' => 'review title',
            'body' => 'review',
            'rating' => 'rating',
            'reference' => 'booking reference',
            'problem' => 'problem description',
            'address' => 'address',
            'area' => 'area',
            'service_name' => 'service',
            'preferred_date' => 'preferred date',
            'time_slot' => 'time slot',
            'pincode' => 'PIN code',
        ];
    }
}
