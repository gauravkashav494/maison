<?php

namespace App\Http\Requests\Storefront;

use App\Models\Subscriber;
use Illuminate\Validation\Validator;

class NewsletterRequest extends StorefrontRequest
{
    public function rules(): array
    {
        return [
            'email' => $this->email(),
            'source' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * Someone already on this store's list can't sign up twice. A person who unsubscribed, or
     * whom staff removed, may sign up again: that is a fresh opt-in and brings their row back.
     *
     * Added after the rules (not as a closure rule) because the base 'email' message would
     * otherwise replace this text with "Please enter a valid email address".
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->has('email')) {
                return;
            }
            if (Subscriber::query()->subscribed()->where('email', $this->input('email'))->exists()) {
                $validator->errors()->add('email', 'This email is already subscribed to our newsletter.');
            }
        }];
    }
}
