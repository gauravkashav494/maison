<?php

namespace App\Http\Requests\Storefront;

use App\Support\IndianStates;
use App\Support\PasswordRules;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

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

    /**
     * Address field pairs (state field => country field) on this form. Deliveries are within
     * India only: before validation "IN" / "Bharat" is written as "India" and a state typed as
     * "MH" or "punjab" as its full name, so the stored address always reads the same way.
     */
    protected array $addressFields = [];

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
        foreach ($this->addressFields as $stateKey => $countryKey) {
            if (IndianStates::isIndia($this->input($countryKey))) {
                $merge[$countryKey] = 'India';
            }
            if (($code = IndianStates::code($this->input($stateKey))) !== null) {
                $merge[$stateKey] = IndianStates::STATES[$code];
            }
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

    /**
     * A 10-digit mobile number (after normalisePhone() has removed spacing and any +91 or leading
     * 0): landlines are refused wherever a number is entered. A lookup by a number already on an
     * account or order ($mobileOnly false) takes any 10 digits, so older numbers still match.
     */
    protected function phone(bool $required = true, bool $mobileOnly = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'regex:'.($mobileOnly ? PhoneNumber::INDIA_MOBILE : '/^\d{10}$/')];
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

    /** A 6-digit Indian PIN code: deliveries are within India only. */
    protected function postalCode(): array
    {
        return ['required', 'string', 'regex:/^[1-9][0-9]{5}$/'];
    }

    /** The country of a delivery address: India only ("India", "IN", "Bharat"). */
    protected function indiaCountry(): array
    {
        return ['required', 'string', 'max:80', function (string $attribute, mixed $value, \Closure $fail) {
            IndianStates::isIndia(is_string($value) ? $value : null) || $fail('We deliver within India only. Please enter India as the country.');
        }];
    }

    /** The state of a delivery address: an Indian state or union territory, by name or short form. */
    protected function indianState(): array
    {
        return ['required', 'string', 'max:80', function (string $attribute, mixed $value, \Closure $fail) {
            IndianStates::code(is_string($value) ? $value : null) !== null || $fail('Please enter a state or union territory of India, e.g. Maharashtra.');
        }];
    }

    protected function newPassword(): array
    {
        return ['required', 'string', 'confirmed', PasswordRules::rule()];
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
            'phone.regex' => 'Please enter a 10-digit mobile number starting with 6, 7, 8 or 9, e.g. 9876543210.',
            'postal_code.regex' => 'Please enter a valid 6-digit PIN code.',
            'shipping_postal_code.regex' => 'Please enter a valid 6-digit PIN code.',
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
