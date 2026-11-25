<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Filament\Forms\Components\PhoneInput;
use App\Models\Client;
use App\Models\Setting;
use App\Support\Activity\ActivityLog;
use App\Support\IndianStates;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Str;

/**
 * The "Business details" step of creating a client: the business behind the store.
 *
 * Nothing new is kept on the client itself. Each answer goes into the store's own settings,
 * where the client can change it later: the invoice seller details (Site settings → Checkout →
 * Invoice) and the contact details (Site settings → Contact & social). So the store's invoices
 * carry the right name, GSTIN and address from the first order.
 */
class ClientBusinessDetails
{
    /** Format: 2-digit state code, 10-character PAN, entity number, Z, check character. */
    public const GSTIN_PATTERN = '/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';

    /** Five letters, four digits, one letter. */
    public const PAN_PATTERN = '/^[A-Z]{5}\d{4}[A-Z]$/';

    /** Form field => [settings group, key in that group]. */
    public const FIELDS = [
        'business_name' => ['checkout', 'invoice_business_name'],
        'contact_person' => ['site', 'contact_person'],
        'contact_email' => ['site', 'contact_email'],
        'contact_phone' => ['site', 'contact_phone'],
        'gstin' => ['checkout', 'invoice_gstin'],
        'pan' => ['checkout', 'invoice_pan'],
        'state' => ['checkout', 'invoice_state'],
        'address' => ['checkout', 'invoice_address'],
    ];

    public static function section(): Section
    {
        return Section::make('Business details')
            ->description('The business behind the store. These are the store\'s own Site settings (invoice seller details and contact details), which the client can also change.')
            ->statePath('business')
            ->columns(2)
            ->schema([
                // Required for a new client; an existing one may predate this step, and its
                // invoices then fall back to the site name.
                TextInput::make('business_name')->label('Registered business name')->maxLength(120)
                    ->required(fn (?Client $record) => $record === null)
                    ->live(onBlur: true)
                    // Starts the client's name (and so its subdomain) on the next step, if still empty.
                    ->afterStateUpdated(function ($set, $get, ?string $state) {
                        if (filled($state) && blank($get('../name'))) {
                            $set('../name', trim($state));
                            if (blank($get('../subdomain'))) {
                                $set('../subdomain', Str::slug($state));
                            }
                        }
                    })
                    ->helperText('As on the GST registration. Printed on invoices.'),
                TextInput::make('contact_person')->label('Owner / contact person')->maxLength(120),
                TextInput::make('contact_email')->label('Business email')->email()->maxLength(190),
                PhoneInput::make('contact_phone')->label('Business phone'),
                TextInput::make('gstin')->label('GSTIN')->maxLength(15)->placeholder('22AAAAA0000A1Z5')
                    ->regex(self::GSTIN_PATTERN.'i') // any case; saved in capitals
                    ->dehydrateStateUsing(fn (?string $state) => self::upper($state))
                    ->live(onBlur: true)
                    // The PAN and state are inside the GSTIN: fill them in if not entered yet.
                    ->afterStateUpdated(function ($set, $get, ?string $state) {
                        $gstin = self::upper($state);
                        if (! $gstin || ! preg_match(self::GSTIN_PATTERN, $gstin)) {
                            return;
                        }
                        if (blank($get('pan'))) {
                            $set('pan', substr($gstin, 2, 10));
                        }
                        if (blank($get('state')) && ($code = IndianStates::codeFromGstin($gstin))) {
                            $set('state', $code);
                        }
                    })
                    ->validationMessages(['regex' => 'Enter a valid 15-character GSTIN, e.g. 22AAAAA0000A1Z5.'])
                    ->helperText('Leave blank if the business is not GST-registered.'),
                TextInput::make('pan')->label('PAN')->maxLength(10)->placeholder('AAAAA0000A')
                    ->regex(self::PAN_PATTERN.'i')
                    ->dehydrateStateUsing(fn (?string $state) => self::upper($state))
                    // A GSTIN carries the business's PAN in characters 3–12.
                    ->rule(fn ($get) => function (string $attribute, mixed $value, \Closure $fail) use ($get) {
                        $gstin = self::upper($get('gstin'));
                        $pan = self::upper($value);
                        if ($pan && $gstin && preg_match(self::GSTIN_PATTERN, $gstin) && substr($gstin, 2, 10) !== $pan) {
                            $fail('This PAN does not match the GSTIN (its characters 3 to 12 are '.substr($gstin, 2, 10).').');
                        }
                    })
                    ->validationMessages(['regex' => 'Enter a valid 10-character PAN, e.g. AAAAA0000A.']),
                Select::make('state')->label('State the business sells from')
                    ->options(IndianStates::options())->searchable()->native(false)
                    ->in(array_keys(IndianStates::STATES))
                    ->helperText('Decides CGST + SGST or IGST on each order.'),
                Textarea::make('address')->label('Business address')->rows(3)->maxLength(500)
                    ->placeholder("Shop 4, Main Market\nNew Delhi 110001")
                    ->helperText('One line per row, as it should print on invoices.'),
            ]);
    }

    /** The client's business details as the form and detail page show them, from its own settings. */
    public static function load(Client $client): array
    {
        $groups = [];
        $data = [];
        foreach (self::FIELDS as $field => [$group, $key]) {
            $groups[$group] ??= Setting::forClient($client->id, $group) ?? [];
            $value = $groups[$group][$key] ?? null;
            $data[$field] = filled($value) ? $value : null;
        }

        return $data;
    }

    /**
     * Saves the details into the client's settings and writes one activity line naming what
     * changed. A new client's empty answers are left out, so its store keeps its defaults; when
     * editing, a field that was emptied is cleared.
     */
    public static function save(Client $client, array $data, bool $creating = false): void
    {
        $before = self::load($client);
        $describer = app(ActivityLog::class)->describer();

        $groups = [];
        $changed = [];
        foreach (self::FIELDS as $field => [$group, $key]) {
            $value = is_string($data[$field] ?? null) ? trim($data[$field]) : ($data[$field] ?? null);
            $value = filled($value) ? $value : null;
            if ($describer->sameValue($before[$field], $value)) {
                continue;
            }
            $groups[$group][$key] = $value;
            $changed[$field] = [$before[$field], $value];
        }

        if ($changed === []) {
            return;
        }

        foreach ($groups as $group => $values) {
            Setting::mergeForClient($client, $group, $values);
        }

        $log = app(ActivityLog::class);
        $labels = self::labels();
        $changes = [];
        foreach ($changed as $field => [$old, $new]) {
            [$group, $key] = self::FIELDS[$field];
            $changes[] = $describer->settingChange($group.'.'.$key, $labels[$field], $old, $new);
        }

        $description = $creating
            ? 'added the business details of '.$describer->reference($client)
            : 'updated the business details of '.$describer->reference($client).': '.self::listLabels(array_column($changes, 'label'));

        $log->record('updated', $description, $client, changes: $changes);
    }

    /** "GSTIN", "GSTIN and PAN", "GSTIN, PAN and business address". */
    private static function listLabels(array $labels): string
    {
        return count($labels) > 1
            ? implode(', ', array_slice($labels, 0, -1)).' and '.end($labels)
            : (string) ($labels[0] ?? '');
    }

    /** Form field => label, for the client's detail page. */
    public static function displayLabels(): array
    {
        return [
            'business_name' => 'Registered name',
            'contact_person' => 'Owner / contact',
            'contact_email' => 'Email',
            'contact_phone' => 'Phone',
            'gstin' => 'GSTIN',
            'pan' => 'PAN',
            'state' => 'State',
            'address' => 'Address',
        ];
    }

    /** Form field => its label in lower case, for the activity line. */
    private static function labels(): array
    {
        return [
            'business_name' => 'registered business name',
            'contact_person' => 'owner / contact person',
            'contact_email' => 'business email',
            'contact_phone' => 'business phone',
            'gstin' => 'GSTIN',
            'pan' => 'PAN',
            'state' => 'state the business sells from',
            'address' => 'business address',
        ];
    }

    private static function upper(?string $value): ?string
    {
        return filled($value) ? strtoupper(trim($value)) : null;
    }
}
