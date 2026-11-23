<?php

namespace App\Support;

/**
 * Indian states and union territories by their GST state code (the first two digits of a
 * GSTIN), and reading a state typed at checkout ("Punjab", "PB", "punjab ") back to one.
 */
class IndianStates
{
    /** GST state codes, as printed for place of supply. */
    public const STATES = [
        '01' => 'Jammu and Kashmir',
        '02' => 'Himachal Pradesh',
        '03' => 'Punjab',
        '04' => 'Chandigarh',
        '05' => 'Uttarakhand',
        '06' => 'Haryana',
        '07' => 'Delhi',
        '08' => 'Rajasthan',
        '09' => 'Uttar Pradesh',
        '10' => 'Bihar',
        '11' => 'Sikkim',
        '12' => 'Arunachal Pradesh',
        '13' => 'Nagaland',
        '14' => 'Manipur',
        '15' => 'Mizoram',
        '16' => 'Tripura',
        '17' => 'Meghalaya',
        '18' => 'Assam',
        '19' => 'West Bengal',
        '20' => 'Jharkhand',
        '21' => 'Odisha',
        '22' => 'Chhattisgarh',
        '23' => 'Madhya Pradesh',
        '24' => 'Gujarat',
        '26' => 'Dadra and Nagar Haveli and Daman and Diu',
        '27' => 'Maharashtra',
        '29' => 'Karnataka',
        '30' => 'Goa',
        '31' => 'Lakshadweep',
        '32' => 'Kerala',
        '33' => 'Tamil Nadu',
        '34' => 'Puducherry',
        '35' => 'Andaman and Nicobar Islands',
        '36' => 'Telangana',
        '37' => 'Andhra Pradesh',
        '38' => 'Ladakh',
    ];

    /** Short forms and older names customers type, normalised as in key(). */
    private const ALIASES = [
        'jk' => '01', 'j and k' => '01', 'jammu kashmir' => '01',
        'hp' => '02',
        'pb' => '03',
        'ch' => '04',
        'uk' => '05', 'ut' => '05', 'uttaranchal' => '05',
        'hr' => '06',
        'dl' => '07', 'new delhi' => '07', 'nct of delhi' => '07', 'delhi ncr' => '07', 'national capital territory of delhi' => '07',
        'rj' => '08',
        'up' => '09',
        'br' => '10',
        'sk' => '11',
        'ar' => '12',
        'nl' => '13',
        'mn' => '14',
        'mz' => '15',
        'tr' => '16',
        'ml' => '17',
        'as' => '18',
        'wb' => '19',
        'jh' => '20',
        'od' => '21', 'or' => '21', 'orissa' => '21',
        'cg' => '22', 'ct' => '22', 'chattisgarh' => '22',
        'mp' => '23',
        'gj' => '24',
        'dn' => '26', 'dd' => '26', 'dnhdd' => '26', 'daman and diu' => '26', 'dadra and nagar haveli' => '26',
        'mh' => '27',
        'ka' => '29',
        'ga' => '30',
        'ld' => '31',
        'kl' => '32',
        'tn' => '33',
        'py' => '34', 'pondicherry' => '34', 'pondy' => '34',
        'an' => '35', 'andaman and nicobar' => '35', 'andaman' => '35',
        'ts' => '36', 'tg' => '36',
        'ap' => '37',
        'la' => '38',
    ];

    /** The GST state code for a state as typed, or null when it is not one we recognise. */
    public static function code(?string $state): ?string
    {
        $key = self::key($state);
        if ($key === '') {
            return null;
        }
        if (isset(self::STATES[$key])) { // already a code, e.g. "03"
            return $key;
        }
        foreach (self::STATES as $code => $name) {
            if (self::key($name) === $key) {
                return $code;
            }
        }

        return self::ALIASES[$key] ?? null;
    }

    /** The GST state code from a GSTIN's first two digits, or null. */
    public static function codeFromGstin(?string $gstin): ?string
    {
        $code = substr(trim((string) $gstin), 0, 2);

        return isset(self::STATES[$code]) ? $code : null;
    }

    /** "Punjab (03)". */
    public static function label(string $code): string
    {
        return (self::STATES[$code] ?? $code).' ('.$code.')';
    }

    /** Options for a select: code => "Punjab (03)", by name. */
    public static function options(): array
    {
        $options = array_map(fn (string $code) => self::label($code), array_combine(array_keys(self::STATES), array_keys(self::STATES)));
        asort($options);

        return $options;
    }

    /**
     * Every way of writing a state we read, as key() writes it, with its code: for the checkout
     * page, which names the tax (IGST or CGST + SGST) as the customer types their state.
     *
     * @return array<string, string>
     */
    public static function lookup(): array
    {
        $lookup = [];
        foreach (self::STATES as $code => $name) {
            $lookup[(string) $code] = (string) $code;
            $lookup[self::key($name)] = (string) $code;
        }

        return $lookup + self::ALIASES;
    }

    /** Ways of writing India, as key() writes them. */
    public const INDIA = ['india', 'in', 'ind', 'bharat'];

    /** Whether a country as typed at checkout is India. */
    public static function isIndia(?string $country): bool
    {
        return in_array(self::key($country), self::INDIA, true);
    }

    /** "  J&K. " → "j and k": lower case, "&" as "and", letters, digits and single spaces only. */
    private static function key(?string $value): string
    {
        $value = strtolower(str_replace('&', ' and ', (string) $value));
        $value = preg_replace('/[^a-z0-9 ]+/', ' ', $value);

        return trim(preg_replace('/\s+/', ' ', $value));
    }
}
