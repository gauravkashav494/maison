<?php

namespace App\Filament\Concerns;

use App\Models\Client;
use App\Models\SyncAgent;
use App\Sync\SyncAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use RuntimeException;

/**
 * The buttons that connect a client's shop PC (docs/SYNC.md): a setup code for the agent, the
 * agent program itself, and revoking its access. Used by the client's page in the admin panel
 * and by Settings → Shop software sync in the client's own panel. Each is in the activity log
 * (SyncAccess).
 */
trait ManagesSyncAgent
{
    /** The client whose agent these buttons manage. */
    abstract protected function syncClient(): Client;

    /** May the signed-in account manage it? */
    abstract protected function canManageSync(): bool;

    /** For the status section's download box (SyncStatus): shown only to who may use it. */
    public function mayManageSync(): bool
    {
        return $this->canManageSync();
    }

    /**
     * The header buttons. The downloads are not among them: they are in the status section's
     * download box, with this computer's build highlighted.
     *
     * @return list<Action>
     */
    protected function syncAgentActions(): array
    {
        return [$this->connectionAction(), $this->setupCodeAction(), $this->revokeSyncAction()];
    }

    /**
     * Where the agent finds the shop's billing software: its API address and key, from the
     * software's developer. The agent reads them at install and every cycle, so the installer
     * asks only for the setup code, and a change here needs no reinstall.
     */
    protected function connectionAction(): Action
    {
        return Action::make('syncConnection')
            ->label('Billing software connection')
            ->icon('heroicon-o-computer-desktop')
            ->authorize(fn () => $this->canManageSync())
            ->modalHeading('Billing software connection')
            ->modalDescription('Where the sync agent finds the shop\'s billing software. The software\'s developer gives you both. The agent picks up a change on its next sync; nothing needs reinstalling.')
            ->fillForm(fn () => ['url' => app(SyncAccess::class)->connection($this->syncClient())['url']])
            ->schema([
                TextInput::make('url')->label('API address')->required()->maxLength(500)
                    ->url()->rule('starts_with:http://,https://')
                    ->helperText('The standard address is '.SyncAgent::DEFAULT_LOCAL_API_URL.'. Change it only if the developer gave you another.'),
                TextInput::make('key')->label('API key')->password()->revealable()->maxLength(255)
                    ->required(fn () => app(SyncAccess::class)->connection($this->syncClient())['key'] === null)
                    ->placeholder(fn () => app(SyncAccess::class)->connection($this->syncClient())['key'] !== null ? 'Saved. Leave blank to keep it.' : null),
            ])
            ->modalSubmitActionLabel('Save')
            ->action(function (array $data) {
                app(SyncAccess::class)->saveConnection($this->syncClient(), $data['url'], $data['key'] ?? null);
                Notification::make()->success()->title('Billing software connection saved')->send();
            });
    }

    /** A new setup code for the shop PC's agent, shown once in showSetupCode. */
    protected function setupCodeAction(): Action
    {
        return Action::make('syncSetupCode')
            ->label('Create agent setup code')
            ->icon('heroicon-o-key')
            ->authorize(fn () => $this->canManageSync())
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-key')
            ->modalHeading(fn () => 'Create a setup code for '.$this->syncClient()->name.'?')
            ->modalDescription('It is pasted into the sync agent when it is installed on the shop PC. It works like a password and is shown only once. Codes made earlier keep working until sync access is revoked.')
            ->modalSubmitActionLabel('Create code')
            ->action(function () {
                try {
                    $code = app(SyncAccess::class)->issueSetupCode($this->syncClient());
                } catch (RuntimeException $problem) {
                    Notification::make()->danger()->title('No setup code was made')->body($problem->getMessage())->send();

                    return;
                }

                $this->replaceMountedAction('showSetupCode', ['code' => $code]);
            });
    }

    public function showSetupCodeAction(): Action
    {
        return Action::make('showSetupCode')
            ->modalHeading(fn () => 'Setup code for '.$this->syncClient()->name)
            ->modalIcon('heroicon-o-key')
            ->modalContent(fn (array $arguments) => view('filament.clients.sync-setup-code', ['code' => $arguments['code'] ?? '']))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Done');
    }

    // The download box (SyncStatus) mounts these by name: the installer for each Windows version.
    public function downloadAgentamd64Action(): Action
    {
        return $this->downloadAgentAction('amd64');
    }

    public function downloadAgent386Action(): Action
    {
        return $this->downloadAgentAction('386');
    }

    // Windows 7 and 8.1: builds made with Go 1.20 (AgentPackage::ARCHITECTURES).
    public function downloadAgentamd64win7Action(): Action
    {
        return $this->downloadAgentAction('amd64-win7');
    }

    public function downloadAgent386win7Action(): Action
    {
        return $this->downloadAgentAction('386-win7');
    }

    protected function downloadAgentAction(string $arch): Action
    {
        return Action::make('downloadAgent'.str_replace('-', '', $arch))
            ->label('Download installer ('.SyncAccess::windows($arch).')')
            ->icon('heroicon-o-arrow-down-tray')
            ->authorize(fn () => $this->canManageSync())
            ->action(function () use ($arch) {
                try {
                    $path = app(SyncAccess::class)->agentDownload($this->syncClient(), $arch);
                } catch (RuntimeException $problem) {
                    Notification::make()->danger()->title('The installer can\'t be downloaded')->body($problem->getMessage())->send();

                    return null;
                }

                return response()->download($path, basename($path));
            });
    }

    protected function revokeSyncAction(): Action
    {
        return Action::make('revokeSync')
            ->label('Revoke sync access')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->authorize(fn () => $this->canManageSync())
            ->visible(fn () => app(SyncAccess::class)->activeTokens($this->syncClient()) > 0)
            ->requiresConfirmation()
            ->modalHeading(fn () => 'Revoke sync access for '.$this->syncClient()->name.'?')
            ->modalDescription('The shop PC\'s agent stops syncing at once. Other stores are not affected. To connect the shop again, create a new setup code and run the agent\'s setup with it.')
            ->modalSubmitActionLabel('Revoke')
            ->action(function () {
                $count = app(SyncAccess::class)->revoke($this->syncClient());
                Notification::make()->success()->title('Sync access revoked')
                    ->body($count === 1 ? 'The agent can no longer sync.' : "{$count} access codes revoked.")->send();
            });
    }
}
