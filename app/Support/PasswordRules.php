<?php

namespace App\Support;

use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Text;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Password;

/**
 * The one password rule for every account on the platform: platform staff, a client's team and
 * storefront shoppers. The server check (rule()) and the live checklist under each new-password
 * field (checks()) are both built from here, so what the checklist ticks is exactly what is
 * accepted.
 *
 * Also installed as Password::defaults(), which Filament's own profile and reset pages use.
 */
class PasswordRules
{
    public const MIN = 8;

    public const MAX = 255;

    public static function rule(): Password
    {
        return Password::min(self::MIN)->max(self::MAX)->letters()->numbers();
    }

    /**
     * The checklist items: a label and a JavaScript pattern (tested with the `u` flag) matching
     * the part of rule() it stands for. \p{L} and \p{N} are the classes Laravel's letters()
     * and numbers() test.
     *
     * @return list<array{label: string, pattern: string}>
     */
    public static function checks(): array
    {
        return [
            ['label' => self::MIN.'+ characters', 'pattern' => '^[\s\S]{'.self::MIN.',}$'],
            ['label' => 'a letter', 'pattern' => '\p{L}'],
            ['label' => 'a number', 'pattern' => '\p{N}'],
        ];
    }

    /**
     * A Filament password field that sets a new password: the shared rule, with its checklist
     * right under the input. Pass helper text here, not through ->helperText(): Filament keeps
     * helper text in the same "below content" slot, so calling it afterwards would replace the
     * checklist.
     */
    public static function field(TextInput $input, string|Closure|null $helperText = null): TextInput
    {
        return $input
            ->password()
            ->revealable()
            ->autocomplete('new-password')
            ->rule(self::rule())
            ->belowContent(function (Component $component) use ($helperText): array {
                $helper = $component->evaluate($helperText);

                return array_values(array_filter([self::checklist(), filled($helper) ? Text::make($helper) : null]));
            });
    }

    /** The live checklist for a panel field; the panels load its script once per page. */
    public static function checklist(): HtmlString
    {
        return new HtmlString(view('components.password-checklist', ['assets' => false])->render());
    }

    /** "Passwords match" line for a panel's confirm-password field. */
    public static function matchHint(): HtmlString
    {
        return new HtmlString(view('components.password-match', ['assets' => false])->render());
    }
}
