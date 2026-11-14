<?php

namespace App\Filament\Pages\Concerns;

use App\Admin\StoreContext;
use App\Filament\Support\Fields;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Js;

/**
 * Shared plumbing for settings pages: loads the named setting groups into the form,
 * renders the form with a save button, and writes each group back on save.
 */
trait HasSettingsForm
{
    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @return array<string> setting groups edited by this page */
    abstract protected function settingGroups(): array;

    /** Super admins open every settings page; a store owner only the pages their template declares. */
    public static function canAccess(): bool
    {
        return app(StoreContext::class)->allowsTemplatePage(static::class);
    }

    public function mount(): void
    {
        $state = [];
        foreach ($this->settingGroups() as $group) {
            $state[$group] = array_replace($this->defaultsFor($group), Setting::get($group, []) ?: []);
        }
        $this->form->fill($state);
    }

    /** Values shown when a group has not been saved yet (templates supply config defaults). */
    protected function defaultsFor(string $group): array
    {
        return [];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Save changes')->submit('save')->keyBindings(['mod+s']),
                        // Empties the fields on the open tab. Nothing is stored until "Save changes",
                        // so reloading the page undoes it.
                        Action::make('clear')
                            ->label('Clear')
                            ->icon('heroicon-o-x-mark')
                            ->color('gray')
                            ->alpineClickHandler(fn (Action $action): string => "\$wire.mountAction('clear', { tab: \$el.closest('form')?.querySelector('.fi-sc-tabs-tab.fi-active')?.id ?? null }, ".Js::from($action->getContext()).')')
                            ->action(fn (array $arguments) => $this->clearFields($arguments['tab'] ?? null)),
                    ])->key('form-actions'),
                ]),
        ]);
    }

    /**
     * Empty every optional field on the open tab (the whole form when the page has no tabs).
     * Required fields keep their value so "Save changes" still passes validation.
     */
    protected function clearFields(?string $tabId): void
    {
        $tabs = collect($this->form->getFlatComponents())->filter(fn ($component): bool => $component instanceof Tab);

        // The browser sends the panel's HTML id, which may carry the schema prefix ("form.footer::data::tab").
        $tab = $tabs->first(fn (Tab $tab): bool => filled($tabId)
            && ($tab->getId() === $tabId || str_ends_with($tabId, '.'.$tab->getId()) || str_ends_with($tab->getId(), '.'.$tabId)));

        if ($tabs->isNotEmpty() && ! $tab) {
            Notification::make()->title('Nothing was cleared')->body('Open the tab you want to clear and try again.')->warning()->send();

            return;
        }

        $fields = $tab ? $tab->getChildSchema()->getFlatFields() : $this->form->getFlatFields();

        foreach ($fields as $field) {
            if (! $field->isRequired()) {
                $field->state(Fields::emptyStateFor($field));
            }
        }

        Notification::make()
            ->title($tab ? 'Cleared "'.$tab->getLabel().'"' : 'Form cleared')
            ->body('Click "Save changes" to keep it, or reload the page to undo.')
            ->info()
            ->send();
    }

    public function save(): void
    {
        $state = $this->form->getState();
        foreach ($this->settingGroups() as $group) {
            // Merge, so values of fields hidden for this store (not in the form state) are kept.
            $stored = Setting::get($group, []);
            Setting::set($group, array_replace(is_array($stored) ? $stored : [], $state[$group] ?? []));
        }

        Notification::make()->success()->title('Settings saved')->send();
    }
}
