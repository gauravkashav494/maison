<?php

namespace App\Support;

/**
 * Phone numbers checked against the rules of their country, and stored in one shape:
 * "+<dial code> <national number>", e.g. "+91 98765 43210".
 *
 * Each country lists its dial code, a pattern for the national number (digits only, without
 * the leading 0 used for domestic calls) and an example. What people type is forgiving: spaces,
 * dashes, brackets, a leading 0 and the country's own dial code are all removed before checking.
 */
class PhoneNumber
{
    public const DEFAULT_COUNTRY = 'IN';

    /**
     * Countries offered in the phone field and accepted on save. India only for now; add a code
     * from COUNTRIES here and the field shows its country dropdown again.
     */
    public const ENABLED = ['IN'];

    /** @var array<string, array{name: string, dial: string, pattern: string, example: string, hint: string}> */
    public const COUNTRIES = [
        'IN' => ['name' => 'India', 'dial' => '91', 'pattern' => '/^[1-9]\d{9}$/', 'digits' => 10, 'groups' => [5, 5], 'example' => '9876543210', 'hint' => '10 digits: a mobile, or a landline with its area code'],
        // One entry for +1: the number alone cannot tell the two apart.
        'US' => ['name' => 'United States / Canada', 'dial' => '1', 'pattern' => '/^[2-9]\d{2}[2-9]\d{6}$/', 'example' => '201 555 0123', 'hint' => '10 digits: area code and number'],
        'GB' => ['name' => 'United Kingdom', 'dial' => '44', 'pattern' => '/^[1-37]\d{8,9}$/', 'example' => '7400 123456', 'hint' => '9 or 10 digits after the leading 0'],
        'AE' => ['name' => 'United Arab Emirates', 'dial' => '971', 'pattern' => '/^(5\d{8}|[2-9]\d{7})$/', 'example' => '50 123 4567', 'hint' => '9 digits starting with 5 for mobiles, 8 for landlines'],
        'SA' => ['name' => 'Saudi Arabia', 'dial' => '966', 'pattern' => '/^(5\d{8}|1\d{7})$/', 'example' => '51 234 5678', 'hint' => '9 digits starting with 5 for mobiles'],
        'QA' => ['name' => 'Qatar', 'dial' => '974', 'pattern' => '/^[3-7]\d{7}$/', 'example' => '3312 3456', 'hint' => '8 digits'],
        'KW' => ['name' => 'Kuwait', 'dial' => '965', 'pattern' => '/^[124-69]\d{7}$/', 'example' => '5001 2345', 'hint' => '8 digits'],
        'OM' => ['name' => 'Oman', 'dial' => '968', 'pattern' => '/^[279]\d{7}$/', 'example' => '9212 3456', 'hint' => '8 digits'],
        'SG' => ['name' => 'Singapore', 'dial' => '65', 'pattern' => '/^[3689]\d{7}$/', 'example' => '8123 4567', 'hint' => '8 digits'],
        'MY' => ['name' => 'Malaysia', 'dial' => '60', 'pattern' => '/^1\d{8,9}$/', 'example' => '12 345 6789', 'hint' => '9 or 10 digits starting with 1 (mobile)'],
        'AU' => ['name' => 'Australia', 'dial' => '61', 'pattern' => '/^[2-478]\d{8}$/', 'example' => '412 345 678', 'hint' => '9 digits after the leading 0'],
        'NZ' => ['name' => 'New Zealand', 'dial' => '64', 'pattern' => '/^[2-9]\d{7,9}$/', 'example' => '21 123 4567', 'hint' => '8 to 10 digits after the leading 0'],
        'NP' => ['name' => 'Nepal', 'dial' => '977', 'pattern' => '/^(9[78]\d{8}|[1-8]\d{6,7})$/', 'example' => '984 1234567', 'hint' => '10 digits starting with 97 or 98 for mobiles'],
        'BD' => ['name' => 'Bangladesh', 'dial' => '880', 'pattern' => '/^1[3-9]\d{8}$/', 'example' => '1812 345678', 'hint' => '10 digits starting with 1 (mobile)'],
        'LK' => ['name' => 'Sri Lanka', 'dial' => '94', 'pattern' => '/^[1-9]\d{8}$/', 'example' => '71 234 5678', 'hint' => '9 digits after the leading 0'],
        'PK' => ['name' => 'Pakistan', 'dial' => '92', 'pattern' => '/^3\d{9}$/', 'example' => '301 2345678', 'hint' => '10 digits starting with 3 (mobile)'],
        'DE' => ['name' => 'Germany', 'dial' => '49', 'pattern' => '/^[1-9]\d{6,12}$/', 'example' => '1512 3456789', 'hint' => '7 to 13 digits after the leading 0'],
        'FR' => ['name' => 'France', 'dial' => '33', 'pattern' => '/^[1-9]\d{8}$/', 'example' => '6 12 34 56 78', 'hint' => '9 digits after the leading 0'],
        'ZA' => ['name' => 'South Africa', 'dial' => '27', 'pattern' => '/^[1-8]\d{8}$/', 'example' => '71 123 4567', 'hint' => '9 digits after the leading 0'],
    ];

    /** @return array<string, array{name: string, dial: string, pattern: string, example: string, hint: string}> */
    public static function enabled(): array
    {
        return array_intersect_key(self::COUNTRIES, array_flip(self::ENABLED));
    }

    public static function country(?string $code): array
    {
        return self::enabled()[$code] ?? self::COUNTRIES[self::DEFAULT_COUNTRY];
    }

    /** The national digits of what was typed, or null when it is not a valid number for the country. */
    public static function national(?string $country, ?string $input): ?string
    {
        $rules = self::country($country);
        $raw = trim((string) $input);
        $digits = preg_replace('/\D+/', '', $raw);

        // "+91 98765..." or "0091 98765..." typed into the number box: drop the country's own code.
        if (str_starts_with($raw, '+') || str_starts_with($digits, '00')) {
            $digits = ltrim($digits, '0');
            if (! str_starts_with($digits, $rules['dial'])) {
                return null; // another country's dial code
            }
            $digits = substr($digits, strlen($rules['dial']));
        }

        // The domestic trunk 0 ("098765 43210" in India, "07400..." in the UK).
        $digits = ltrim($digits, '0');

        return preg_match($rules['pattern'], $digits) ? $digits : null;
    }

    /** "+91 98765 43210", or null when invalid. */
    public static function format(?string $country, ?string $input): ?string
    {
        $national = self::national($country, $input);

        return $national === null ? null : '+'.self::country($country)['dial'].' '.self::group($country, $national);
    }

    /**
     * Spaces the digits by the country's groups (India: "98765 43210"), or as its example is spaced,
     * so a number reads well wherever it is shown; a length that differs from the example stays unspaced.
     */
    private static function group(?string $country, string $national): string
    {
        $rules = self::country($country);
        $chunks = $rules['groups'] ?? array_map('strlen', explode(' ', $rules['example']));
        if (array_sum($chunks) !== strlen($national)) {
            return $national;
        }

        $parts = [];
        $offset = 0;
        foreach ($chunks as $length) {
            $parts[] = substr($national, $offset, $length);
            $offset += $length;
        }

        return implode(' ', $parts);
    }

    /** A whole value as the phone field holds it ("+91 98765 43210") in its saved shape, or null when invalid. */
    public static function normalise(string $value): ?string
    {
        [$country, $number] = self::split($value);
        $known = preg_match('/^\+/', trim($value)) !== 1 || self::dialIsKnown($value);

        return $known ? self::format($country, $number) : null;
    }

    /** The message shown under the field for an invalid value. */
    public static function errorFor(string $value): string
    {
        if (preg_match('/^\+/', trim($value)) === 1 && ! self::dialIsKnown($value)) {
            $enabled = self::enabled();

            return count($enabled) === 1
                ? 'Only '.reset($enabled)['name'].' (+'.reset($enabled)['dial'].') numbers are accepted.'
                : 'Choose the country from the list; this country code is not supported.';
        }
        $country = self::country(self::split($value)[0]);

        return 'Enter a valid '.$country['name'].' number ('.$country['hint'].').';
    }

    private static function dialIsKnown(string $value): bool
    {
        $digits = preg_replace('/\D+/', '', $value);

        return collect(self::enabled())->contains(fn (array $c) => str_starts_with($digits, $c['dial']));
    }

    /**
     * Splits a stored number back into [country, national digits] for the form. Numbers saved
     * before countries were checked (no "+") are shown as they are, under the default country.
     *
     * @return array{0: string, 1: ?string}
     */
    public static function split(?string $stored): array
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return [self::DEFAULT_COUNTRY, null];
        }

        if (preg_match('/^\+(\d+)\s*(.*)$/', $stored, $m)) {
            // "+91 98765…" as this class writes it: the code before the space is the dial code.
            $exact = collect(self::enabled())->search(fn (array $c) => $c['dial'] === $m[1]);
            if ($exact !== false && $m[2] !== '') {
                return [$exact, $m[2]];
            }

            $all = $m[1].preg_replace('/\D+/', '', $m[2]);
            // The longest matching dial code wins (+971 rather than +9…); every dial code is listed once.
            $matches = array_filter(self::enabled(), fn (array $c) => str_starts_with($all, $c['dial']));
            uasort($matches, fn ($a, $b) => strlen($b['dial']) <=> strlen($a['dial']));
            if ($matches) {
                $code = array_key_first($matches);

                return [$code, substr($all, strlen(self::COUNTRIES[$code]['dial']))];
            }
        }

        return [self::DEFAULT_COUNTRY, $stored];
    }
}
