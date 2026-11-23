<?php

namespace App\Support;

use App\Models\Customer;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Finding and making a shopper's account by phone number. Used after a WhatsApp code
 * (OtpController) and, while codes are switched off (CustomerOtp::enabled()), straight from
 * the sign-up form (AccountController::register).
 */
class CustomerAccount
{
    /** This client's account for the number (a password or a verified phone), if any. */
    public static function forPhone(int $clientId, string $phone): ?Customer
    {
        return Customer::where('client_id', $clientId)->where('phone', $phone)->accounts()->orderBy('id')->first();
    }

    /**
     * A new account. The email is kept only when no other customer of this client has it (a
     * guest record keeps its address, claimed through the emailed link). $verified marks the
     * phone as proven by a code; an account made while codes are off leaves it empty.
     */
    public static function create(int $clientId, string $phone, string $name, ?string $email, ?string $password = null, bool $verified = true): Customer
    {
        $email = filled($email) ? strtolower(trim($email)) : null;
        if ($email && Customer::withTrashed()->where('client_id', $clientId)->whereRaw('lower(email) = ?', [$email])->exists()) {
            $email = null;
        }

        $attributes = ['client_id' => $clientId, 'name' => trim($name), 'phone' => $phone, 'is_active' => true];
        try {
            $customer = Customer::create($attributes + ['email' => $email, 'password' => $password]);
        } catch (UniqueConstraintViolationException) {
            // The address was taken a moment ago: the account is still made, without it.
            $customer = Customer::create($attributes + ['email' => null, 'password' => $password]);
        }
        if ($verified) {
            $customer->forceFill(['phone_verified_at' => now()])->save();
        }

        return $customer;
    }
}
