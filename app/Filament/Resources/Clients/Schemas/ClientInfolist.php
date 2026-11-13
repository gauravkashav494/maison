<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Clients\ClientDatabase;
use App\Clients\ClientModules;
use App\Clients\ClientProvisioner;
use App\Clients\ClientQuota;
use App\Clients\ClientSetup;
use App\Models\Client;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Read-only overview of a client: a header card (identity, status, key facts), usage meters,
 * then the details — addresses and modules on the left, plan, database and record on the right.
 * The team is listed below the page by the Team relation manager.
 */
class ClientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $link = fn (string $name, string $label, \Closure $url, array $data = []) => ViewEntry::make($name)
            ->label($label)
            ->inlineLabel()
            ->state($url)
            ->view('filament.tables.columns.link-copy')
            ->viewData($data + ['inline' => true]);

        return $schema->columns(3)->components([
            // Only while a new client's database is being built in the background (or failed to).
            ViewEntry::make('setup_status')->hiddenLabel()->columnSpanFull()
                ->view('filament.clients.setup-status')
                ->visible(fn (Client $r) => ClientSetup::status($r) !== null),

            ViewEntry::make('overview')->hiddenLabel()->columnSpanFull()->view('filament.clients.overview'),
            ViewEntry::make('usage')->hiddenLabel()->columnSpanFull()->view('filament.clients.usage'),

            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 2])->schema([
                Section::make('Web addresses')
                    ->icon('heroicon-o-globe-alt')
                    ->description('Where shoppers and the client\'s team reach this store.')
                    ->schema([
                        $link('public_url', 'Storefront', fn (Client $r) => $r->publicUrl()),
                        $link('login_url', 'Client login', fn (Client $r) => $r->loginUrl(), ['icon' => 'heroicon-m-arrow-right-end-on-rectangle']),
                        TextEntry::make('domain')->label('Custom domain')->inlineLabel()
                            ->icon(fn (Client $r) => $r->domain ? 'heroicon-m-check-badge' : null)->iconColor('success')
                            ->placeholder('Not set — the store uses its subdomain'),
                    ]),

                Section::make('Modules')
                    ->icon('heroicon-o-squares-2x2')
                    ->description(function (Client $r) {
                        $available = ClientModules::optionsForStorefront($r->storefront_id);
                        $on = count(array_filter(array_keys($available), fn ($key) => $r->hasModule($key)));

                        return "{$on} of ".count($available).' modules switched on for this client\'s team.';
                    })
                    ->schema([
                        TextEntry::make('modules_overview')->hiddenLabel()->badge()
                            ->state(fn (Client $r) => array_values(ClientModules::optionsForStorefront($r->storefront_id)))
                            ->color(fn (string $state, Client $record) => self::moduleOn($record, $state) ? 'success' : 'gray')
                            ->icon(fn (string $state, Client $record) => self::moduleOn($record, $state) ? 'heroicon-m-check' : 'heroicon-m-minus')
                            ->placeholder('This store has no optional modules'),
                    ]),
            ]),

            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 1])->schema([
                Section::make('Plan & limits')
                    ->icon('heroicon-o-credit-card')
                    ->description('Blank limits fall back to the plan.')
                    ->schema([
                        TextEntry::make('plan.name')->label('Plan')->inlineLabel()->badge()->color('primary')->placeholder('No plan'),
                        TextEntry::make('limit_products')->label('Products')->inlineLabel()
                            ->state(fn (Client $r) => self::limit($r->productLimit()))
                            ->helperText(fn (Client $r) => self::source($r, 'product_limit')),
                        TextEntry::make('limit_images')->label('Images')->inlineLabel()
                            ->state(fn (Client $r) => self::limit($r->imageLimit()))
                            ->helperText(fn (Client $r) => self::source($r, 'image_limit')),
                        TextEntry::make('limit_storage')->label('Storage')->inlineLabel()
                            ->state(fn (Client $r) => $r->storageLimitMb() === null ? 'Unlimited' : ClientQuota::formatBytes($r->storageLimitMb() * 1048576))
                            ->helperText(fn (Client $r) => self::source($r, 'storage_limit_mb')),
                    ]),

                Section::make('Database')
                    ->icon('heroicon-o-circle-stack')
                    ->schema([
                        TextEntry::make('database_status')->label('Status')->inlineLabel()->badge()
                            ->state(fn (Client $r) => ! app(ClientDatabase::class)->isolated()
                                ? 'Shared database'
                                : (app(ClientProvisioner::class)->databaseExists($r) ? 'Provisioned' : 'Missing'))
                            ->color(fn (string $state) => match ($state) {
                                'Provisioned' => 'success',
                                'Missing' => 'danger',
                                default => 'gray',
                            })
                            ->icon(fn (string $state) => match ($state) {
                                'Provisioned' => 'heroicon-m-check-circle',
                                'Missing' => 'heroicon-m-exclamation-triangle',
                                default => 'heroicon-m-share',
                            }),
                        TextEntry::make('database_name')->label('Name')->inlineLabel()->fontFamily('mono')->size('sm')->copyable()
                            ->visible(fn () => app(ClientDatabase::class)->isolated())
                            ->state(fn (Client $r) => $r->databaseName()),
                    ]),

                Section::make('Record')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        TextEntry::make('created_at')->label('Created')->inlineLabel()->dateTime('d M Y, H:i'),
                        TextEntry::make('updated_at')->label('Last updated')->inlineLabel()->since()
                            ->tooltip(fn (Client $r) => $r->updated_at?->format('d M Y, H:i')),
                        TextEntry::make('deleted_at')->label('Deleted')->inlineLabel()->dateTime('d M Y, H:i')->color('danger')
                            ->visible(fn (Client $r) => $r->trashed()),
                    ]),
            ]),
        ]);
    }

    private static function moduleOn(Client $client, string $label): bool
    {
        $key = array_search($label, ClientModules::optionsForStorefront($client->storefront_id), true);

        return $key !== false && $client->hasModule($key);
    }

    private static function limit(?int $limit): string
    {
        return $limit === null ? 'Unlimited' : number_format($limit);
    }

    /** Where a limit comes from: the client's own figure, its plan, or the platform default. */
    private static function source(Client $client, string $column): string
    {
        return match (true) {
            $client->{$column} !== null => 'Custom for this client',
            $client->plan?->{$column} !== null => 'From the '.$client->plan->name.' plan',
            default => 'Platform default',
        };
    }
}
