<?php

namespace App\Http\Requests\Storefront;

use App\Models\Order;
use App\Services\Cart;
use Illuminate\Validation\Rule;

class CheckoutRequest extends StorefrontRequest
{
    public function rules(): array
    {
        $payments = (array) setting('checkout.payment_methods', array_keys(Order::PAYMENT_METHODS));
        $shipping = collect(app(Cart::class)->shippingMethods())->pluck('code')->filter()->all();

        return [
            'email' => $this->email(),
            'phone' => $this->phone(),
            'shipping_name' => $this->personName(),
            'shipping_line1' => $this->addressLine(),
            'shipping_line2' => $this->addressLine(required: false),
            'shipping_city' => $this->place(),
            'shipping_state' => $this->place(),
            'shipping_postal_code' => $this->postalCode($this->input('shipping_country')),
            'shipping_country' => $this->place(),
            'shipping_method' => ['required', 'string', $shipping ? Rule::in($shipping) : 'max:40'],
            'payment_method' => ['required', 'string', Rule::in($payments)],
            'notes' => ['nullable', 'string', 'max:500'],
            'save_address' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'shipping_method.required' => 'Please choose a delivery method.',
            'payment_method.required' => 'Please choose a payment method.',
            'payment_method.in' => 'That payment method is not available.',
            'shipping_method.in' => 'That delivery method is not available.',
        ] + parent::messages();
    }
}
