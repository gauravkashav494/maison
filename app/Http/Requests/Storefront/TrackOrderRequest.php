<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Validation\Validator;

/**
 * Order number plus the email *or* phone given at checkout (email is optional there, the phone
 * is not). The old `email` parameter is still read, so earlier tracking links keep working.
 */
class TrackOrderRequest extends StorefrontRequest
{
    /** A GET form: always come back to the empty tracking page rather than the previous URL. */
    protected $redirectRoute = 'track';

    protected function prepareForValidation(): void
    {
        $contact = trim((string) ($this->input('contact') ?? $this->input('email') ?? ''));
        if ($contact !== '') {
            $contact = str_contains($contact, '@') ? mb_strtolower($contact) : (static::normalisePhone($contact) ?? $contact);
        }
        $this->merge(['contact' => $contact]);
    }

    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-]+$/'],
            'contact' => ['required', 'string', 'max:190'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $contact = (string) $this->input('contact');
            if ($contact === '' || $validator->errors()->has('contact')) {
                return;
            }
            $valid = str_contains($contact, '@')
                ? filter_var($contact, FILTER_VALIDATE_EMAIL) !== false
                : preg_match('/^\d{10}$/', $contact) === 1;
            if (! $valid) {
                $validator->errors()->add('contact', 'Enter the email address or 10-digit phone number used at checkout.');
            }
        }];
    }

    /** Whether the contact given is an email (otherwise it is a normalised phone number). */
    public function isEmail(): bool
    {
        return str_contains((string) $this->input('contact'), '@');
    }

    public function messages(): array
    {
        return [
            'number.regex' => 'Order numbers contain only letters, digits and hyphens, e.g. ME-260916-AB12.',
            'contact.required' => 'Enter the email address or phone number used at checkout.',
        ] + parent::messages();
    }
}
