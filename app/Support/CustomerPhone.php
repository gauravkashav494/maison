<?php

namespace App\Support;

use App\Models\Customer;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * A customer's phone number is unique within a client. Numbers have been saved in more than one
 * shape over time ("9876543210", "+91 98765 43210", "919876543210"…), so every check matches all
 * the shapes of the same number rather than the exact text.
 */
class CustomerPhone
{
    /**
     * Every shape this number may have been saved in. Empty for something that is not an
     * Indian mobile or landline number.
     *
     * @return list<string>
     */
    public static function forms(?string $phone): array
    {
        $national = PhoneNumber::national('IN', $phone) ?? preg_replace('/\D+/', '', (string) $phone);
        if (! preg_match('/^\d{10}$/', (string) $national)) {
            return filled($phone) ? [trim((string) $phone)] : [];
        }
        $formatted = PhoneNumber::format('IN', $national);

        return array_values(array_unique(array_filter([
            $national,
            $formatted,
            $formatted ? preg_replace('/\s+/', '', $formatted) : null,
            '91'.$national,
            '0'.$national,
            substr($national, 0, 5).' '.substr($national, 5),
        ])));
    }

    /** Customers of this client with this number, in any of its shapes (deleted ones not included). */
    public static function query(int $clientId, ?string $phone): Builder
    {
        return Customer::query()->where('client_id', $clientId)->whereIn('phone', self::forms($phone) ?: ['']);
    }

    /** Is the number already another customer's in this client? */
    public static function taken(int $clientId, ?string $phone, ?int $ignoreId = null): bool
    {
        return filled($phone) && self::query($clientId, $phone)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists();
    }

    /**
     * Validation rule: no other customer of the client has this number.
     *
     * @param  int|Closure(): ?int  $clientId
     */
    public static function uniqueRule(int|Closure $clientId, ?int $ignoreId = null, string $message = 'Another customer already has this phone number.'): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($clientId, $ignoreId, $message): void {
            $client = $clientId instanceof Closure ? $clientId() : $clientId;
            if ($client && is_string($value) && self::taken((int) $client, $value, $ignoreId)) {
                $fail($message);
            }
        };
    }
}
