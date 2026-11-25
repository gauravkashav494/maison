<?php

namespace App\Support;

use App\Clients\ClientContext;
use App\Models\Customer;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * The customer an order or booking belongs to when the shopper did not sign in.
 *
 * As on most storefronts, every purchase is kept against a customer record so the store sees
 * who bought what, even from guests. A guest becomes a customer without a password: they
 * can use the account only after proving the address is theirs, through the emailed
 * set-password link (Register or Forgot password). Nothing is ever shown to whoever simply
 * typed the address at checkout.
 */
class GuestCustomer
{
    /** The id to store on the order: the signed-in customer, or the guest's record for this email. */
    public static function idFor(?Customer $signedIn, ?string $email, ?string $name = null, ?string $phone = null): ?int
    {
        if ($signedIn) {
            // Their own account: a phone given at checkout fills one they never saved.
            self::fillMissingPhone($signedIn, $phone);

            return $signedIn->id;
        }

        return self::forEmail($email, $name, $phone)?->id;
    }

    /**
     * Only ever fills an empty phone; a saved number is never replaced from a checkout. A number
     * another customer of the client already has is left off (one customer per phone).
     */
    private static function fillMissingPhone(Customer $customer, ?string $phone): void
    {
        if (blank($customer->phone) && filled($phone) && ! CustomerPhone::taken((int) $customer->client_id, $phone, $customer->id)) {
            $customer->forceFill(['phone' => trim($phone)])->saveQuietly();
        }
    }

    /** This client's customer for the address, created as a guest when there is none. */
    public static function forEmail(?string $email, ?string $name = null, ?string $phone = null, ?int $clientId = null): ?Customer
    {
        $email = strtolower(trim((string) $email));
        $clientId ??= app(ClientContext::class)->id();
        if ($email === '' || ! $clientId) {
            return null;
        }

        // An existing record is reused as it is (also one the store deactivated or deleted), and
        // never updated from what a guest typed — except that an earlier guest record (no
        // password: it was itself made by a checkout) gets a phone when it has none. A registered
        // account is never touched, as whoever typed its address may not be its owner.
        if ($existing = self::find($clientId, $email)) {
            if (! $existing->isAccount() && ! $existing->trashed()) {
                self::fillMissingPhone($existing, $phone);
            }

            return $existing;
        }

        try {
            return Customer::create([
                'client_id' => $clientId,
                'name' => filled($name) ? trim($name) : strstr($email, '@', true),
                'email' => $email,
                // One customer per phone: a number another customer has stays on the order only.
                'phone' => filled($phone) && ! CustomerPhone::taken($clientId, $phone) ? $phone : null,
                'password' => null,
                'is_active' => true,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two checkouts with the same address at the same moment: the other one created it.
            return self::find($clientId, $email);
        }
    }

    private static function find(int $clientId, string $email): ?Customer
    {
        return Customer::withTrashed()
            ->where('client_id', $clientId)
            ->whereRaw('lower(email) = ?', [$email])
            ->first();
    }
}
