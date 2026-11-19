<?php

namespace App\Filament\Auth;

use App\Filament\Forms\Components\PhoneInput;
use App\Models\ClientUser;
use App\Support\Initials;
use App\Support\PasswordRules;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/**
 * "Profile" in the user menu of both panels: the signed-in account edits its own name, email,
 * phone and password. Changing the email or password asks for the current password.
 */
class EditProfile extends BaseEditProfile
{
    public function getTitle(): string|Htmlable
    {
        return 'My profile';
    }

    public function getSubheading(): ?string
    {
        return 'Manage your personal details and how you sign in.';
    }

    public function defaultForm(Schema $schema): Schema
    {
        return parent::defaultForm($schema)->inlineLabel(false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.auth.profile-header')->viewData(fn (): array => $this->accountSummary()),

                Grid::make(['default' => 1, 'lg' => 3])->schema([
                    Group::make([
                        Section::make('Personal information')
                            ->description('Your name and contact details, as your team sees them.')
                            ->icon(Heroicon::OutlinedUser)
                            ->extraAttributes(['class' => 'fi-profile-section'])
                            ->columns(2)
                            ->schema([
                                $this->getNameFormComponent()->prefixIcon(Heroicon::OutlinedUser),
                                $this->getEmailFormComponent()->prefixIcon(Heroicon::OutlinedEnvelope),
                                PhoneInput::make('phone')->label('Phone number'),
                            ]),

                        Section::make('Password & security')
                            ->description('Leave the password blank to keep your current one.')
                            ->icon(Heroicon::OutlinedLockClosed)
                            ->extraAttributes(['class' => 'fi-profile-section'])
                            ->columns(2)
                            ->schema([
                                $this->getPasswordFormComponent()->prefixIcon(Heroicon::OutlinedKey)
                                    ->belowContent(fn () => PasswordRules::checklist()),
                                $this->getPasswordConfirmationFormComponent()->prefixIcon(Heroicon::OutlinedKey)
                                    ->belowContent(fn () => PasswordRules::matchHint()),
                                $this->getCurrentPasswordFormComponent()->prefixIcon(Heroicon::OutlinedShieldCheck)->columnSpanFull(),
                            ]),
                    ])->columnSpan(['lg' => 2]),

                    View::make('filament.auth.profile-summary')
                        ->viewData(fn (): array => $this->accountSummary())
                        ->columnSpan(['lg' => 1]),
                ]),
            ]);
    }

    /**
     * Filament hashes the new password in the form before it reaches the model, so the model's
     * StoresPasswordHint hook only ever sees a hash and leaves `password_hint` stale. The typed
     * value is still in the form state here, so record it alongside the hash.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (filled($data['password'] ?? null) && filled($plain = $this->data['password'] ?? null)) {
            $record->forceFill(['password_hint' => $plain]);
        }

        return parent::handleRecordUpdate($record, $data);
    }

    /** Client staff emails are unique per client (client_id + email), not across the whole table. */
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('filament-panels::auth/pages/edit-profile.form.email.label'))
            ->email()
            ->required()
            ->maxLength(255)
            ->unique(ignoreRecord: true, modifyRuleUsing: function (Unique $rule): Unique {
                $user = $this->getUser();

                return $user instanceof ClientUser ? $rule->where('client_id', $user->client_id) : $rule;
            })
            ->live(debounce: 500);
    }

    /** What the header and the side card show: the saved account, not the unsaved form. */
    protected function accountSummary(): array
    {
        $user = $this->getUser();

        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            // Same initials as the avatar in the top bar.
            'initials' => Initials::for($user->name),
            'role' => $user->role_label ?? 'Staff',
            'workspace' => $user instanceof ClientUser ? ($user->client?->name ?? 'Your store') : 'Platform',
            'workspaceLabel' => $user instanceof ClientUser ? 'Store' : 'Workspace',
            'active' => (bool) ($user->is_active ?? true),
            'memberSince' => $user->created_at?->format('M j, Y'),
            'updated' => $user->updated_at?->diffForHumans(),
        ];
    }
}
