<?php

namespace App\Http\Requests\Storefront;

use App\Services\Cart;
use Illuminate\Validation\Rule;

/** A coupon or shipping-method code posted from the cart. */
class CodeRequest extends StorefrontRequest
{
    protected array $lowercase = [];

    public function rules(): array
    {
        $rules = ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_\-]+$/'];
        if ($this->routeIs('cart.shipping')) {
            $rules[] = Rule::in(collect(app(Cart::class)->shippingMethods())->pluck('code')->all());
        }

        return ['code' => $rules];
    }

    public function messages(): array
    {
        return [
            'code.required' => $this->routeIs('cart.coupon') ? 'Please enter a coupon code.' : 'Please choose a delivery method.',
            'code.in' => 'That delivery method is not available.',
            'code.regex' => 'Codes contain only letters, digits, hyphens and underscores.',
        ] + parent::messages();
    }
}
