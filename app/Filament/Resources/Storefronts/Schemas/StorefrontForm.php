<?php

namespace App\Filament\Resources\Storefronts\Schemas;

use App\Models\Storefront;
use App\Templates\TemplateManager;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * A store is the product on offer: one template with its features. Clients are what rent it
 * (Platform → Clients), so limits and staff accounts live on the client, and several clients
 * may run the same store without sharing anything.
 */
class StorefrontForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Store')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120)->live(onBlur: true)
                    ->afterStateUpdated(fn ($set, $get, $state) => blank($get('slug')) ? $set('slug', Str::slug($state)) : null),
                TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true)->helperText('Used in admin links.'),
                Select::make('template')
                    ->label('Template')
                    ->options(fn () => app(TemplateManager::class)->options())
                    ->required()
                    ->native(false)
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (?Storefront $record) => $record !== null)
                    ->dehydrated()
                    ->helperText('The website design and features this store runs. Content is scoped by this template, so it cannot change once the store exists.'),
                Toggle::make('is_active')->label('Active')->default(true)->inline(false)
                    ->helperText('Inactive stores stop answering on their address and lock out the clients running them.'),
            ]),
            Section::make('Public address')
                ->description(fn (?Storefront $record) => $record
                    ? 'Each client running this store has its own address (Platform → Clients). '.$record->publicUrl().' is the store\'s own demo address.'
                    : 'The store gets an address on this domain as soon as it is saved.')
                ->columns(2)
                ->schema([
                    TextInput::make('domain')->label('Demo domain (optional)')->placeholder('demo.example.com')->maxLength(190)->unique(ignoreRecord: true)
                        ->rule('regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i')->helperText('Host only, no https://. A client\'s own domain is set on the client.'),
                ]),
            Section::make('Default plan limits')
                ->description('Starting point for a new client on this store. A client\'s own limits (or its plan) always win; leave blank for no limit.')
                ->columns(2)
                ->schema([
                    TextInput::make('product_limit')->label('Maximum products')->numeric()->minValue(0)->maxValue(1000000)->placeholder('Unlimited'),
                    TextInput::make('storage_limit_mb')->label('Storage allowance (MB)')->numeric()->minValue(0)->maxValue(1048576)->placeholder('Unlimited'),
                ]),
        ]);
    }
}
