<?php

namespace App\Filament\Pages\Concerns;

use App\Admin\StoreContext;
use App\Filament\Support\Fields;
use App\Models\Setting;
use App\Support\Activity\ActivityLog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Js;
use Illuminate\Support\Str;

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
        $changed = [];
        $describer = app(ActivityLog::class)->describer();
        foreach ($this->settingGroups() as $group) {
            // Merge, so values of fields hidden for this store (not in the form state) are kept.
            $stored = Setting::get($group, []);
            $stored = is_array($stored) ? $stored : [];
            // What the form showed before this save (stored value, else the default it opened with).
            $before = array_replace($this->defaultsFor($group), $stored);
            foreach ($state[$group] ?? [] as $key => $value) {
                if (! $describer->sameValue($before[$key] ?? null, $value)) {
                    $changed[$group.'.'.$key] = [$before[$key] ?? null, $value];
                }
            }
            Setting::set($group, array_replace($stored, $state[$group] ?? []));
        }

        $labels = $this->logSettingsChange($changed);
        $page = trim(strip_tags((string) $this->getTitle())) ?: 'Settings';

        if ($labels === []) {
            Notification::make()->info()->title('No changes made')->body('Nothing has changed since the last update.')->send();

            return;
        }

        // "Store settings updated" / "Changed: store name, logo and 2 more."
        $shown = array_slice($labels, 0, 3);
        $more = count($labels) - count($shown);
        $list = $more > 0
            ? implode(', ', $shown).' and '.$more.' more'
            : (count($shown) > 1 ? implode(', ', array_slice($shown, 0, -1)).' and '.end($shown) : $shown[0]);

        Notification::make()->success()->title($page.' updated')->body('Changed: '.$list.'.')->send();
    }

    /**
     * One activity line per save, naming the fields by their form labels ("updated Site settings:
     * store name, logo and 3 more"), with short values before and after. Keys, tokens and other
     * secrets are named only (ActivityDescriber::settingChange).
     *
     * @param  array<string, array{0: mixed, 1: mixed}>  $changed  "group.key" => [before, after] of every changed setting
     * @return array<int, string> the changed fields' labels, for the toast
     */
    protected function logSettingsChange(array $changed): array
    {
        if ($changed === []) {
            return [];
        }

        $labels = [];
        foreach ($this->form->getFlatFields(withHidden: true) as $field) {
            $labels[Str::after($field->getStatePath(), 'data.')] = trim(strip_tags((string) $field->getLabel()));
        }

        $log = app(ActivityLog::class);
        $title = trim(strip_tags((string) $this->getTitle()));
        $changes = [];
        foreach ($changed as $path => [$old, $new]) {
            $label = Str::lower($labels[$path] ?? '') ?: $log->describer()->label(Str::afterLast($path, '.'));
            $changes[] = $log->describer()->settingChange($path, $label, $old, $new);
        }

        $log->record('updated', $log->describer()->settings($title, $changes), changes: $changes, area: 'Settings', subjectName: $title, subjectType: 'Settings');

        return array_values(array_unique(array_column($changes, 'label')));
    }
}
