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
 * renders the form with a sticky save button, and writes each group back on save.
 */
trait HasSettingsForm
{
    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @return array<string> setting groups edited by this page */
    abstract protected function settingGroups(): array;

    public function mount(): void
    {
        $state = [];
        foreach ($this->settingGroups() as $group) {
            $state[$group] = Setting::get($group, []);
        }
        $this->form->fill($state);
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
                    ])->sticky(),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        foreach ($this->settingGroups() as $group) {
            Setting::set($group, $state[$group] ?? []);
        }

        Notification::make()->success()->title('Settings saved')->send();
    }
}
