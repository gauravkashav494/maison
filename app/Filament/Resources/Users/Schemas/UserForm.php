<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Storefront;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Users: customers (no role) and staff. Staff pick a role; a Store Owner must be
 * assigned to exactly one store. Only super admins reach this form (UserPolicy).
 */
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Account')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120),
                TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                TextInput::make('phone')->maxLength(30),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation) => $operation === 'create')
                    ->helperText(fn (string $operation) => $operation === 'create' ? 'Minimum 8 characters.' : 'Leave blank to keep the current password; enter a new one to reset it.')
                    ->minLength(8),
            ]),
            Section::make('Role & store access')->description('Customers have no admin access. A Store Owner manages one store only; a Super Admin manages the whole platform.')->columns(2)->schema([
                Select::make('role')
                    ->label('Role')
                    ->options(['' => 'Customer (no admin access)'] + User::ROLES)
                    ->default('')
                    ->native(false)
                    ->live()
                    ->afterStateHydrated(fn (Select $component, $state) => $component->state($state ?? ''))
                    ->dehydrateStateUsing(fn ($state) => $state === '' ? null : $state)
                    ->disabled(fn (?User $record) => $record && $record->is(auth()->user()))
                    ->helperText(fn (?User $record) => $record && $record->is(auth()->user()) ? 'You cannot change your own role.' : null),
                Select::make('storefront_id')
                    ->label('Assigned store')
                    ->options(fn () => Storefront::orderBy('name')->pluck('name', 'id'))
                    ->native(false)
                    ->searchable()
                    ->visible(fn ($get) => $get('role') === User::ROLE_STORE_OWNER)
                    ->required(fn ($get) => $get('role') === User::ROLE_STORE_OWNER)
                    ->helperText('The only store this owner can see and manage.'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->inline(false)
                    ->disabled(fn (?User $record) => $record && $record->is(auth()->user()))
                    ->helperText('Disabled staff cannot sign in to the admin.'),
            ]),
            Grid::make(1)->schema([
                Toggle::make('is_admin')->hidden()->dehydrated(),
            ]),
        ]);
    }

    /** Derive the legacy staff flag from the role and drop the store for non-owners. */
    public static function normalise(array $data): array
    {
        $role = $data['role'] ?? null;
        $data['role'] = $role ?: null;
        $data['is_admin'] = (bool) $data['role'];
        if ($data['role'] !== User::ROLE_STORE_OWNER) {
            $data['storefront_id'] = null;
        }

        return $data;
    }
}
