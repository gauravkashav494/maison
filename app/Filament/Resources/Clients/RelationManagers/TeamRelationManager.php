<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\ClientRole;
use App\Models\ClientUser;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The client's staff accounts, listed on the client's view page. Accounts are edited under Team. */
class TeamRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Team';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $pageClass === ViewClient::class;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $client = $this->getOwnerRecord();

        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('medium'),
                TextColumn::make('email')->searchable()->sortable()->copyable(),
                TextColumn::make('phone')->placeholder('—')->color('gray')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('role')->label('Access')->badge()
                    ->formatStateUsing(fn (?string $state, ClientUser $r) => $r->isClientAdmin() ? ClientUser::ROLES[$state] : ($r->clientRole?->name ?? 'No role'))
                    ->color(fn (?string $state, ClientUser $r) => $r->isClientAdmin() ? 'info' : ($r->clientRole ? 'gray' : 'danger')),
                IconColumn::make('is_active')->label('Active')->boolean()->sortable(),
                TextColumn::make('created_at')->label('Added')->date('d M Y')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('role')->label('Account type')->options(ClientUser::ROLES),
                SelectFilter::make('client_role_id')->label('Role')
                    ->options(fn () => ClientRole::where('client_id', $client->getKey())->orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
                TernaryFilter::make('is_active')->label('Active')
                    ->trueLabel('Active only')->falseLabel('Inactive only'),
            ])
            // The summary sits in the toolbar, on the same row as the search field.
            ->toolbarActions([
                Action::make('summary')
                    ->label(fn () => $client->users()->count().' staff account(s), '.$client->customers()->count().' customer(s).')
                    ->link()
                    ->color('gray')
                    ->disabled(),
            ])
            ->recordUrl(null)
            ->recordAction(null)
            ->emptyStateHeading('No staff accounts yet')
            ->emptyStateIcon('heroicon-o-user-group');
    }
}
