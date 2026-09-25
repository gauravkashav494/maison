<?php

namespace App\Filament\Resources\Storefronts\Schemas;

use App\Models\Storefront;
use App\Models\User;
use App\Templates\TemplateManager;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class StorefrontForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Store')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120)->live(onBlur: true)
                    ->afterStateUpdated(fn ($set, $get, $state) => blank($get('slug')) ? $set('slug', Str::slug($state)) : null),
                TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true)->helperText('Used by the store switcher.'),
                Select::make('template')
                    ->label('Template')
                    ->options(fn () => app(TemplateManager::class)->options())
                    ->required()
                    ->native(false)
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (?Storefront $record) => $record !== null)
                    ->dehydrated()
                    ->helperText('The website design and features this store runs. Content is scoped by this template, so it cannot change once the store exists.'),
                Toggle::make('is_active')->label('Active')->default(true)->inline(false)->helperText('Inactive stores lock their owners out of the admin and stop answering on their address.'),
            ]),
            Section::make('Public address')->description(fn (?Storefront $record) => $record ? 'Share '.$record->publicUrl().' with customers. The entry link '.$record->entryUrl().' always works; the subdomain form needs a wildcard DNS record and SSL certificate, a custom domain needs its DNS pointed at this server.' : 'The store gets an entry link on this domain as soon as it is saved.')->columns(2)->schema([
                TextInput::make('domain')->label('Custom domain (optional)')->placeholder('shop.example.com')->maxLength(190)->unique(ignoreRecord: true)
                    ->rule('regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i')->helperText('Without one the store uses its subdomain. Enter the host only, no https://.'),
            ]),
            Section::make('Plan limits')->description('Leave a field blank for no limit. Limits apply to everyone working in this store, including you.')->columns(2)->schema([
                TextInput::make('product_limit')->label('Maximum products')->numeric()->minValue(0)->maxValue(1000000)->placeholder('Unlimited')
                    ->helperText(fn (?Storefront $record) => $record ? 'Currently using '.\App\Admin\StoreQuota::for($record)->productCount().' products.' : null),
                TextInput::make('storage_limit_mb')->label('Storage allowance (MB)')->numeric()->minValue(0)->maxValue(1048576)->placeholder('Unlimited')
                    ->helperText(fn (?Storefront $record) => $record ? 'Currently using '.\App\Admin\StoreQuota::formatBytes(\App\Admin\StoreQuota::for($record)->storageUsed()).' of uploaded media.' : null),
            ]),
            Section::make('Owners')->description('Staff accounts that manage this store. Create accounts under Platform → Users.')->schema([
                Select::make('owner_ids')
                    ->label('Store owners')
                    ->multiple()
                    ->options(fn () => User::where('role', User::ROLE_STORE_OWNER)->orderBy('name')->get()->mapWithKeys(fn (User $u) => [$u->id => $u->name.' — '.$u->email]))
                    ->searchable()
                    ->afterStateHydrated(fn (Select $component, ?Storefront $record) => $component->state($record?->owners()->pluck('id')->all() ?? []))
                    ->dehydrated(false)
                    ->helperText('Moving an owner here removes them from their previous store.'),
            ]),
        ]);
    }

    /** Sync the owners chosen in the form: assign the selected owners, unassign the rest. */
    public static function syncOwners(Storefront $store, array $ownerIds): void
    {
        User::where('role', User::ROLE_STORE_OWNER)->where('storefront_id', $store->id)->whereNotIn('id', $ownerIds)->update(['storefront_id' => null]);
        User::where('role', User::ROLE_STORE_OWNER)->whereIn('id', $ownerIds)->update(['storefront_id' => $store->id]);
    }
}
