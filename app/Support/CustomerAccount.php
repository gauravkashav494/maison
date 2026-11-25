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
    /** This client's account for the number (a password or a verified phone), in any saved shape. */
    public static function forPhone(int $clientId, string $phone): ?Customer
    {
        return CustomerPhone::query($clientId, $phone)->accounts()->orderBy('id')->first();
    }

    /**
     * This client's guest record for the number: a customer from an earlier checkout or sales
     * order, with no password and no proven phone. Never a sales person.
     */
    public static function guestForPhone(int $clientId, string $phone): ?Customer
    {
        return CustomerPhone::query($clientId, $phone)->guests()->where('is_salesperson', false)->orderBy('id')->first();
    }

    /**
     * The shopper's account. A phone is unique within a client, so a guest record that already
     * has this number becomes the account once the number is proven ($verified): its earlier
     * orders then show in the account. Otherwise a new account is made. The email is kept only
     * when no other customer of this client has it (a guest record keeps its address, claimed
     * through the emailed link). An account made while codes are off leaves the phone unproven.
     */
    public static function create(int $clientId, string $phone, string $name, ?string $email, ?string $password = null, bool $verified = true): Customer
    {
        $email = filled($email) ? strtolower(trim($email)) : null;

        if ($verified && ($guest = self::guestForPhone($clientId, $phone))) {
            return self::claim($guest, $name, $email, $password);
        }

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

    /** A guest record becomes the shopper's account: their name and password, the proven phone. */
    private static function claim(Customer $guest, string $name, ?string $email, ?string $password): Customer
    {
        $guest->forceFill([
            'name' => trim($name),
            'phone' => PhoneNumber::national('IN', $guest->phone) ?? $guest->phone,
            'password' => $password,
            // is_active is left as it is: a record the store disabled stays disabled.
            'phone_verified_at' => now(),
        ]);
        // Their email only when the record has none and no other customer of this client uses it.
        if ($email && blank($guest->email)
            && ! Customer::withTrashed()->where('client_id', $guest->client_id)->whereRaw('lower(email) = ?', [$email])->exists()) {
            $guest->email = $email;
        }
        $guest->save();

        return $guest;
    }
}
