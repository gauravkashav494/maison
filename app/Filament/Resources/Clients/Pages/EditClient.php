<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Schemas\ClientForm;
use App\Models\ClientUser;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard\Step;

class EditClient extends EditRecord
{
    use EditRecord\Concerns\HasWizard;

    protected static string $resource = ClientResource::class;

    /** The same steps as "Create client", plus this client's admins. */
    public function getSteps(): array
    {
        return [
            ...ClientForm::steps(),
            Step::make('Client admins')->icon('heroicon-o-user-circle')
                ->description('Who signs in to run the store')
                ->schema([$this->adminsSection()]),
        ];
    }

    /** Edit, add or remove this client's Client Admins (their other staff stay with the client). */
    protected function adminsSection(): Section
    {
        $clientId = fn () => $this->getRecord()->getKey();

        return Section::make('Client admins')
            ->description('They can do everything in the store\'s panel, including managing their own team. A store always keeps at least one active admin.')
            ->schema([
                Repeater::make('clientAdmins')->hiddenLabel()
                    ->relationship()
                    ->columns(2)
                    ->minItems(1)
                    ->collapsible()
                    ->itemLabel(fn (array $state) => filled($state['name'] ?? null) ? $state['name'].' · '.($state['email'] ?? '') : 'New admin')
                    ->addActionLabel('Add client admin')
                    // A store's only admin cannot be removed, so there is no delete button for it.
                    ->deletable(fn (?array $state) => count($state ?? []) > 1)
                    ->deleteAction(fn ($action) => $action->requiresConfirmation()->modalDescription('The admin is moved to the trash and can no longer sign in. After saving, bring them back with "Restore admins" at the top of this page.'))
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data) => $data + ['role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => $data['is_active'] ?? true])
                    ->schema([
                        TextInput::make('name')->label('Full name')->required()->maxLength(120),
                        TextInput::make('email')->label('Email')->email()->required()->maxLength(190)->distinct()
                            ->unique(ClientUser::class, 'email', ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('client_id', $clientId())),
                        TextInput::make('phone')->label('Phone (optional)')->tel()->maxLength(30),
                        TextInput::make('password')->label('Password')->password()->revealable()->minLength(8)
                            ->required(fn (?ClientUser $record) => $record === null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->hint('Minimum 8 characters')
                            ->hintIcon('heroicon-m-information-circle', tooltip: 'Use at least 8 characters. A mix of letters, numbers and symbols is stronger.')
                            ->helperText(fn (?ClientUser $record) => $record ? 'Leave blank to keep their current password.' : null),
                        Toggle::make('is_active')->label('Can sign in')->default(true)->inline(false),
                    ]),
            ]);
    }

    /** Brings back Client Admins removed from this client; only shown while some are in the trash. */
    protected function restoreAdminsAction(): Action
    {
        $trashed = fn () => $this->getRecord()->clientAdmins()->onlyTrashed()->get();

        return Action::make('restoreAdmins')->label('Restore admins')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->visible(fn () => ! $this->getRecord()->trashed() && $trashed()->isNotEmpty())
            ->modalHeading('Restore deleted client admins')
            ->modalDescription('Restored admins can sign in again if "Can sign in" is on for them. Unsaved changes on this page are discarded.')
            ->modalSubmitActionLabel('Restore')
            ->schema([
                CheckboxList::make('admins')->hiddenLabel()->required()
                    ->options(fn () => $trashed()->mapWithKeys(fn (ClientUser $u) => [$u->getKey() => $u->name.' · '.$u->email])),
            ])
            ->action(function (array $data) {
                $this->getRecord()->clientAdmins()->onlyTrashed()->whereKey($data['admins'])->get()
                    ->each(fn (ClientUser $admin) => $admin->restore());

                $this->getRecord()->unsetRelation('clientAdmins');
                $this->fillForm();

                Notification::make()->success()->title('Admins restored')->send();
            });
    }

    /** Never save a store without an active admin: nobody could sign in to it. */
    protected function beforeSave(): void
    {
        $active = collect($this->data['clientAdmins'] ?? [])->filter(fn ($admin) => (bool) ($admin['is_active'] ?? false))->count();

        if ($active === 0) {
            Notification::make()->danger()->title('Keep at least one active client admin')
                ->body('Switch "Can sign in" on for one of them, or add a new admin, then save again.')->send();

            $this->halt();
        }
    }

    /** Editing: jump straight to any step by its header instead of clicking through them all. */
    protected function hasSkippableSteps(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveChanges')->label('Save changes')->icon('heroicon-o-check')
                ->hidden(fn () => $this->getRecord()->trashed())
                ->action(fn () => $this->save()),
            $this->restoreAdminsAction(),
            RestoreAction::make(),
            DeleteAction::make()
                ->modalHeading(fn () => 'Delete '.$this->getRecord()->name.'?')
                ->modalDescription('The storefront and client login stop working straight away. Staff, customers and the client database are kept, and the client can be restored from the "Deleted items" filter.')
                ->modalSubmitActionLabel('Delete')
                ->successNotificationTitle('Client deleted'),
        ];
    }
}
