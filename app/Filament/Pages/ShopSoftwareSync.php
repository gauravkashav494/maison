<?php

namespace App\Filament\Pages;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Concerns\ManagesSyncAgent;
use App\Filament\Support\SyncStatus;
use App\Models\Client;
use App\Models\ClientUser;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Settings → Shop software sync, in the client's own panel: how to install the sync agent on the
 * shop PC (docs/SYNC.md), the buttons to do it, and how the agent is doing. A setup code is a
 * password to the store's data, so only the Client Admin sees this page.
 */
class ShopSoftwareSync extends Page
{
    use ManagesSyncAgent;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Shop software sync';

    protected static ?string $slug = 'shop-software-sync';

    public static function canAccess(): bool
    {
        $user = app(StoreContext::class)->user();

        return $user instanceof ClientUser && $user->isActiveAccount() && $user->isClientAdmin()
            && app(ClientContext::class)->client() !== null;
    }

    protected function syncClient(): Client
    {
        return app(ClientContext::class)->client();
    }

    protected function canManageSync(): bool
    {
        return static::canAccess();
    }

    protected function getHeaderActions(): array
    {
        // The downloads are in the status section, with this computer's build highlighted
        // (SyncStatus, ManagesSyncAgent::downloadAgentamd64Action()).
        return [
            $this->connectionAction()->button()->color('gray'),
            $this->setupCodeAction()->button()->color('primary'),
            $this->revokeSyncAction(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->record($this->syncClient())
            ->components([
                Section::make('Connect your billing software')
                    ->icon('heroicon-o-computer-desktop')
                    ->description('The sync agent keeps this online store and the billing software in your shop in step: products, stock and customers come in from the shop, web orders go out to it.')
                    ->schema([View::make('filament.pages.sync-install-steps')]),
                SyncStatus::section(),
            ]);
    }
}
