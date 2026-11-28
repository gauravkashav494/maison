<?php

namespace App\Filament\Support;

use App\Models\Client;
use App\Sync\SyncAccess;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;

/**
 * The shop PC's sync agent as it last reported (docs/SYNC.md): set up, syncing or in trouble.
 * Shown on the client's page in the admin panel and on Settings → Shop software sync in the
 * client's own panel; the schema's record is the client.
 */
class SyncStatus
{
    public static function section(): Section
    {
        $status = fn (Client $r) => app(SyncAccess::class)->status($r);
        $report = fn (Client $r) => $status($r)['agent']?->last_report ?? [];

        return Section::make('Shop software sync')
            ->icon('heroicon-o-arrows-right-left')
            ->description('The agent on the shop PC that syncs this store with the shop\'s billing software.')
            ->schema([
                TextEntry::make('sync_status')->label('Status')->inlineLabel()->badge()
                    ->state(fn (Client $r) => $status($r)['label'])
                    ->color(fn (Client $r) => $status($r)['color'])
                    ->helperText(fn (Client $r) => match ($status($r)['state']) {
                        'none' => 'Create an agent setup code to connect the shop.',
                        'waiting' => 'A setup code is ready; the agent has not synced yet.',
                        'revoked' => 'The agent can no longer sync. Create a new setup code to connect it again.',
                        'quiet' => 'The agent syncs every 5 minutes. Check that the shop PC is on and online.',
                        default => null,
                    }),
                TextEntry::make('sync_last_seen')->label('Last sync')->inlineLabel()
                    ->state(fn (Client $r) => $status($r)['agent']?->last_seen_at)
                    ->since()
                    ->tooltip(fn (Client $r) => $status($r)['agent']?->last_seen_at?->format('d M Y, H:i'))
                    ->placeholder('Never'),
                TextEntry::make('sync_message')->label('Last result')->inlineLabel()
                    ->state(fn (Client $r) => $report($r)['message'] ?? null)
                    ->placeholder('—'),
                TextEntry::make('sync_counts')->label('Last cycle')->inlineLabel()->badge()->color('gray')
                    ->state(fn (Client $r) => collect($report($r)['counts'] ?? [])
                        ->map(fn ($count, $key) => str_replace(['.', '_'], ' ', (string) $key).': '.$count)
                        ->values()->all())
                    ->placeholder('Nothing to report'),
                TextEntry::make('sync_errors')->label('Errors')->inlineLabel()->color('danger')->listWithLineBreaks()->bulleted()
                    ->state(fn (Client $r) => $report($r)['errors'] ?? [])
                    ->visible(fn (Client $r) => ($report($r)['errors'] ?? []) !== []),
                TextEntry::make('sync_version')->label('Agent version')->inlineLabel()->fontFamily('mono')->size('sm')
                    ->state(fn (Client $r) => ($version = $status($r)['agent']?->agent_version) ? substr($version, 0, 12) : null)
                    ->tooltip(fn (Client $r) => $status($r)['agent']?->agent_version)
                    ->placeholder('—'),
                TextEntry::make('sync_connection')->label('Billing software')->inlineLabel()
                    ->state(fn (Client $r) => app(SyncAccess::class)->connection($r)['url'])
                    ->fontFamily('mono')->size('sm')
                    ->helperText(fn (Client $r) => app(SyncAccess::class)->connection($r)['key'] === null
                        ? 'No API key yet: use Billing software connection to add it, before installing.'
                        : 'API key saved.')
                    ->color(fn (Client $r) => app(SyncAccess::class)->connection($r)['key'] === null ? 'danger' : null),
                TextEntry::make('sync_access')->label('Access codes')->inlineLabel()
                    ->state(fn (Client $r) => match ($count = app(SyncAccess::class)->activeTokens($r)) {
                        0 => 'None active',
                        1 => '1 active',
                        default => "{$count} active",
                    }),
                // The two builds, the one this computer needs highlighted; only for who may download.
                ViewEntry::make('sync_download')->label('Download the agent')
                    ->view('filament.clients.sync-agent-download')
                    ->visible(fn ($livewire, Client $r) => ! $r->trashed()
                        && method_exists($livewire, 'mayManageSync') && $livewire->mayManageSync()),
            ]);
    }
}
