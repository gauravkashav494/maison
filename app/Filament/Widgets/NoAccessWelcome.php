<?php

namespace App\Filament\Widgets;

use App\Admin\StoreContext;
use App\Clients\ClientAccess;
use App\Clients\ClientModules;
use App\Models\ClientUser;
use Filament\Widgets\Widget;

/**
 * The client dashboard of a staff account given no module yet: a welcome and who to ask for
 * access, instead of an empty page. Shown to Admin Staff whose role grants nothing in the
 * store's switched-on modules; a Client Admin holds every module and never sees it. (The
 * platform dashboard has its own greeting for every account: PlatformWelcome.)
 */
class NoAccessWelcome extends Widget
{
    protected string $view = 'filament.widgets.no-access-welcome';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected static ?int $sort = -3;

    public static function canView(): bool
    {
        $user = app(StoreContext::class)->user();

        return $user instanceof ClientUser && ! $user->isClientAdmin() && ! self::hasAnyClientModule($user);
    }

    private static function hasAnyClientModule(ClientUser $user): bool
    {
        foreach (ClientModules::forClient($user->client) as $key => $module) {
            foreach ($module['actions'] as $action) {
                if (ClientAccess::allows($user, $key, $action)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function getViewData(): array
    {
        $user = app(StoreContext::class)->user();

        // Who can hand out access: the store's Client Admin.
        $contact = ClientUser::where('client_id', $user->client_id)->where('role', ClientUser::ROLE_CLIENT_ADMIN)
            ->where('is_active', true)->whereKeyNot($user->getKey())->orderBy('id')->first();

        return [
            'firstName' => strtok((string) $user->name, ' ') ?: $user->name,
            'contact' => $contact,
        ];
    }
}
