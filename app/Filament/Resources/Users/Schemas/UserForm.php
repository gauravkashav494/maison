<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Admin\PlatformModules;
use App\Filament\Forms\Components\PhoneInput;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Platform accounts: a Super Admin, or platform staff limited to the /admin modules ticked
 * here. A client's staff are created on the client and shoppers register on a storefront, so
 * neither can be made here — this form only ever produces a platform account.
 */
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Account')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120),
                TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                PhoneInput::make('phone'),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation) => $operation === 'create')
                    ->helperText(fn (string $operation) => $operation === 'create' ? 'Minimum 8 characters.' : 'Leave blank to keep the current password; enter a new one to reset it.')
                    ->minLength(8),
            ]),
            Section::make('Platform access')->description('A Super Admin manages the whole platform. Anyone else only reaches the modules ticked below.')->columns(2)->schema([
                Toggle::make('is_super_admin')
                    ->label('Super Admin')
                    ->default(false)
                    ->inline(false)
                    ->live()
                    // You cannot take your own Super Admin access away, so there is always one left.
                    ->disabled(fn (?User $record) => $record && $record->is(filament()->auth()->user()))
                    ->helperText('Full access to clients, stores, plans, platform accounts and platform settings.'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->inline(false)
                    ->disabled(fn (?User $record) => $record && $record->is(filament()->auth()->user()))
                    ->helperText('A disabled account cannot sign in to /admin.'),
                CheckboxList::make('admin_modules')
                    ->label('Modules')
                    ->options(PlatformModules::ALL)
                    ->columns(3)
                    ->bulkToggleable()
                    ->columnSpanFull()
                    ->hidden(fn (Get $get) => (bool) $get('is_super_admin'))
                    ->required(fn (Get $get) => ! $get('is_super_admin'))
                    ->validationMessages(['required' => 'Choose at least one module, or make the account a Super Admin.'])
                    ->helperText('Platform accounts and platform settings are always Super Admin only.'),
            ]),
        ]);
    }

    /** Access comes from the Super Admin flag and the module list only; role and is_admin follow it (see User::booted). */
    public static function normalise(array $data): array
    {
        $data['is_super_admin'] = (bool) ($data['is_super_admin'] ?? false);
        $data['admin_modules'] = $data['is_super_admin'] ? null : PlatformModules::clean($data['admin_modules'] ?? []);
        unset($data['storefront_id'], $data['role'], $data['is_admin']);

        return $data;
    }
}
