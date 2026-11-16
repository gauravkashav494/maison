<?php

namespace App\Filament\Forms\Components;

use App\Support\PhoneNumber;
use Closure;
use Filament\Forms\Components\Concerns\HasPlaceholder;
use Filament\Forms\Components\Field;

/**
 * One phone box with the country picker inside it, on the left where the dial code shows
 * ("IN +91 ▾"). The state is a single string, so it drops in wherever a phone TextInput was:
 * the number is checked against the chosen country and saved as "+91 98765 43210".
 */
class PhoneInput extends Field
{
    use HasPlaceholder;

    protected string $view = 'filament.forms.components.phone-input';

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
            if (filled($value) && PhoneNumber::normalise((string) $value) === null) {
                $fail(PhoneNumber::errorFor((string) $value));
            }
        });

        // Whatever spacing was typed, the number is saved in one shape; blank saves null.
        $this->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? (PhoneNumber::normalise($state) ?? $state) : null);
    }

    /** @return list<array{code: string, name: string, dial: string, digits?: int, example: string, hint: string}> */
    public function getCountries(): array
    {
        return collect(PhoneNumber::enabled())
            ->map(fn (array $c, string $code) => ['code' => $code] + array_intersect_key($c, array_flip(['name', 'dial', 'digits', 'example', 'hint'])))
            ->values()
            ->all();
    }

    public function getDefaultCountry(): string
    {
        return PhoneNumber::DEFAULT_COUNTRY;
    }
}
