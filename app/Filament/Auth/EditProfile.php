<?php

namespace App\Filament\Auth;

use App\Models\ClientUser;
use App\Support\Initials;
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
                                TextInput::make('phone')
                                    ->label('Phone number')
                                    ->tel()
                                    ->maxLength(32)
                                    ->placeholder('+91 98765 43210')
                                    ->prefixIcon(Heroicon::OutlinedPhone),
                            ]),

                        Section::make('Password & security')
                            ->description('Leave the password blank to keep your current one.')
                            ->icon(Heroicon::OutlinedLockClosed)
                            ->extraAttributes(['class' => 'fi-profile-section'])
                            ->columns(2)
                            ->schema([
                                $this->getPasswordFormComponent()->prefixIcon(Heroicon::OutlinedKey),
                                $this->getPasswordConfirmationFormComponent()->prefixIcon(Heroicon::OutlinedKey),
                                $this->getCurrentPasswordFormComponent()->prefixIcon(Heroicon::OutlinedShieldCheck)->columnSpanFull(),
                            ]),
                    ])->columnSpan(['lg' => 2]),

                    View::make('filament.auth.profile-summary')
                        ->viewData(fn (): array => $this->accountSummary())
                        ->columnSpan(['lg' => 1]),
                ]),
            ]);
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
