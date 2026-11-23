<?php

namespace App\Filament\Concerns;

use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * For a form split into tabs: when a save is refused, a message names what is missing and on
 * which tab ("Pricing & Variants: Price incl. tax, Tax"), and each tab with a problem shows how
 * many in a red badge (errorBadge()). Without it the form only switches to the first tab with an
 * error, which reads as being sent somewhere else for no reason.
 */
trait ExplainsMissingFields
{
    protected function onValidationError(ValidationException $exception): void
    {
        parent::onValidationError($exception);

        $byTab = [];
        foreach (array_keys($exception->errors()) as $key) {
            [$tab, $label] = $this->placeOfError($key);
            $byTab[$tab ?? 'Form'][$label] = true;
        }

        if ($byTab === []) {
            return;
        }

        $lines = array_map(
            fn (string $tab, array $labels) => '**'.$tab.':** '.implode(', ', array_keys($labels)),
            array_keys($byTab),
            $byTab,
        );

        Notification::make()->danger()
            ->title(count($exception->errors()) === 1 ? 'One thing needs fixing before saving' : 'Some things need fixing before saving')
            ->body(Str::markdown(implode("\n\n", $lines)))
            ->send();
    }

    /** How many of the current errors are on the tab $label; null when none (no badge). */
    public function errorCountOnTab(string $label): ?int
    {
        $count = 0;
        foreach (array_keys($this->getErrorBag()->toArray()) as $key) {
            if ($this->placeOfError($key)[0] === $label) {
                $count++;
            }
        }

        return $count ?: null;
    }

    /**
     * The tab an error's field is on and the field's name as the form shows it ("Variants ›
     * Stock" inside a repeater).
     *
     * @return array{0: ?string, 1: string}
     */
    private function placeOfError(string $key): array
    {
        $field = $this instanceof HasSchemas && ($form = $this->getSchema('form'))
            ? $form->getComponentByStatePath($key, withHidden: true, withAbsoluteStatePath: true)
            : null;

        if (! $field instanceof Component) {
            return [null, Str::of($key)->afterLast('.')->replace('_', ' ')->ucfirst()->toString()];
        }

        $names = [];
        $tab = null;
        for ($component = $field; $component; $component = $component->getContainer()->getParentComponent()) {
            if ($component instanceof Tab) {
                $tab = (string) $component->getLabel();
                break;
            }
            // Fields and the repeaters around them are named; layout boxes (sections, grids) are not.
            if (method_exists($component, 'getStatePath') && $component->hasStatePath() && filled($label = (string) $component->getLabel())) {
                array_unshift($names, $label);
            }
        }

        return [$tab, strip_tags(implode(' › ', $names)) ?: Str::of($key)->afterLast('.')->replace('_', ' ')->ucfirst()->toString()];
    }
}
