<?php

namespace App\Filament\Resources\PlatformActivities\Pages;

use App\Filament\Resources\PlatformActivities\PlatformActivityResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

/**
 * Everyone opens on their own activity. A Super Admin has a button in the header that switches
 * to everyone's activity and back; the choice is kept in the URL (?everyone=1).
 * Platform staff never get the button, and the resource query keeps them on their own lines anyway.
 */
class ListPlatformActivities extends ListRecords
{
    protected static string $resource = PlatformActivityResource::class;

    #[Url(as: 'everyone')]
    public bool $showEveryone = false;

    public function table(Table $table): Table
    {
        return parent::table($table)->modifyQueryUsing(function (Builder $query) {
            $user = filament()->auth()->user();

            if ($this->showsEveryone() || ! $user instanceof Model) {
                return $query;
            }

            return $query->byActor($user);
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleEveryone')
                ->label(fn () => $this->showsEveryone() ? 'Showing everyone\'s activity' : 'Showing my activity')
                ->icon(fn () => $this->showsEveryone() ? 'heroicon-m-user-group' : 'heroicon-m-user')
                ->color(fn () => $this->showsEveryone() ? 'primary' : 'gray')
                ->outlined(fn () => ! $this->showsEveryone())
                ->tooltip(fn () => $this->showsEveryone() ? 'Click to show only your own activity' : 'Click to show the activity of every platform user')
                ->visible(fn () => $this->isSuperAdmin())
                ->action(function () {
                    $this->showEveryone = ! $this->showEveryone;
                    $this->resetPage();
                }),
        ];
    }

    public function getSubheading(): ?string
    {
        return $this->showsEveryone() ? 'Changes made by everyone.' : 'Changes you made.';
    }

    private function showsEveryone(): bool
    {
        return $this->showEveryone && $this->isSuperAdmin();
    }

    private function isSuperAdmin(): bool
    {
        return (bool) filament()->auth()->user()?->isSuperAdmin();
    }
}
