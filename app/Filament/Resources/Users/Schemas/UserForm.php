<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * SuperAdmin accounts. A client's staff are created on the client (Platform → Clients →
 * Staff) and shoppers register on a storefront, so neither can be made here — this form
 * only ever produces a platform account.
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
            Section::make('Platform access')->description('A Super Admin manages the whole platform: clients, stores, plans and every client\'s data.')->columns(2)->schema([
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->inline(false)
                    ->disabled(fn (?User $record) => $record && $record->is(filament()->auth()->user()))
                    ->helperText('A disabled account cannot sign in to /admin.'),
            ]),
            Grid::make(1)->schema([
                Toggle::make('is_admin')->hidden()->dehydrated(),
            ]),
        ]);
    }

    /** Every account created here is a Super Admin; the role is never taken from the form. */
    public static function normalise(array $data): array
    {
        $data['role'] = User::ROLE_SUPER_ADMIN;
        $data['is_admin'] = true;
        unset($data['storefront_id']);

        return $data;
    }
}
