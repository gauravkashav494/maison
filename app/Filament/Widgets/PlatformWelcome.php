<?php

namespace App\Filament\Widgets;

use App\Admin\PlatformModules;
use App\Filament\Pages\ClientTotalsReport;
use App\Filament\Pages\Templates;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Plans\PlanResource;
use App\Filament\Resources\Storefronts\StorefrontResource;
use App\Models\User;
use Filament\Widgets\Widget;

/**
 * Top of the platform dashboard, for every platform account: a greeting and a button for each
 * platform module the account holds, so no account lands on an empty dashboard whatever it was
 * given. An account with no module at all (only older ones; the form requires one) is told who
 * can give it access instead.
 */
class PlatformWelcome extends Widget
{
    protected string $view = 'filament.widgets.platform-welcome';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected static ?int $sort = -3;

    public static function canView(): bool
    {
        return filament()->auth()->user() instanceof User;
    }

    /** @return array<string, array{label: string, url: \Closure(): string}> */
    private static function sections(): array
    {
        return [
            PlatformModules::CLIENTS => ['label' => 'Clients', 'url' => fn () => ClientResource::getUrl('index')],
            PlatformModules::STOREFRONTS => ['label' => 'Stores', 'url' => fn () => StorefrontResource::getUrl('index')],
            PlatformModules::PLANS => ['label' => 'Plans', 'url' => fn () => PlanResource::getUrl('index')],
            PlatformModules::TEMPLATES => ['label' => 'Templates', 'url' => fn () => Templates::getUrl()],
            PlatformModules::REPORTS => ['label' => 'Client totals report', 'url' => fn () => ClientTotalsReport::getUrl()],
        ];
    }

    protected function getViewData(): array
    {
        /** @var User $user */
        $user = filament()->auth()->user();

        $sections = collect(self::sections())
            ->filter(fn (array $section, string $module) => PlatformModules::allows($user, $module))
            ->map(fn (array $section) => ['label' => $section['label'], 'url' => ($section['url'])()])
            ->values();

        return [
            'firstName' => strtok((string) $user->name, ' ') ?: $user->name,
            'superAdmin' => $user->isSuperAdmin(),
            'sections' => $sections,
            'contact' => $sections->isEmpty()
                ? User::where('is_super_admin', true)->where('is_active', true)->whereKeyNot($user->getKey())->orderBy('id')->first()
                : null,
        ];
    }
}
