<?php

namespace App\Filament\Resources\Menus\Pages;

use App\Filament\Resources\Menus\MenuResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateMenu extends CreateRecord
{
    protected static string $resource = MenuResource::class;

    /** Opened directly (an old link) with no location left: back to the list, saying why. */
    public function mount(): void
    {
        if (MenuResource::availableLocations() === []) {
            Notification::make()->warning()->title('Every menu location already has a menu')
                ->body('Change the links of an existing menu with ⋮ → Manage items.')->send();
            $this->redirect(MenuResource::getUrl('index'));

            return;
        }

        parent::mount();
    }
}
