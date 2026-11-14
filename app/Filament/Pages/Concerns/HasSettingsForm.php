<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;

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
        return app(\App\Admin\StoreContext::class)->allowsTemplatePage(static::class);
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
                    ]),
                ]),
        ]);
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
