<?php

namespace App\Clients;

use App\Admin\StoreContext;
use App\Models\Client;
use App\Models\Storefront;
use App\Templates\Template;
use App\Templates\TemplateManager;

/** Read access to config/client_modules.php: the modules, their permissions and default roles. */
class ClientModules
{
    /** @return array<string, array{label: string, group: string, model: ?string, actions: array<string>}> */
    public static function all(): array
    {
        $modules = [];
        foreach (config('client_modules.modules', []) as $key => $module) {
            $modules[$key] = $module + ['actions' => config('client_modules.default_actions', [])];
        }

        return $modules;
    }

    public static function get(string $module): ?array
    {
        return self::all()[$module] ?? null;
    }

    public static function forModel(string $model): ?string
    {
        foreach (self::all() as $key => $module) {
            if ($module['model'] === $model) {
                return $key;
            }
        }

        return null;
    }

    /** @return array<string> "<module>.<action>" for every permission in the catalogue */
    public static function permissions(): array
    {
        $permissions = [];
        foreach (self::all() as $key => $module) {
            foreach ($module['actions'] as $action) {
                $permissions[] = $key.'.'.$action;
            }
        }

        return $permissions;
    }

    /** @return array<string, string> action => label, for one module */
    public static function actionOptions(string $module): array
    {
        $options = [];
        foreach (self::get($module)['actions'] ?? [] as $action) {
            $options[$action] = config('client_modules.action_labels.'.$action, ucfirst($action));
        }

        return $options;
    }

    /** Modules that fit a store: a catalogue store has no bookings, a service business no orders. */
    public static function forTemplate(?Template $template): array
    {
        if (! $template) {
            return [];
        }

        $services = $template->supportsServices();

        return array_filter(self::all(), function (array $module) use ($services) {
            return $services
                ? ! in_array($module['model'], StoreContext::CATALOGUE_MODELS, true)
                : ! in_array($module['model'], StoreContext::SERVICE_MODELS, true);
        });
    }

    public static function forClient(Client $client): array
    {
        return self::forTemplate($client->templateObject());
    }

    /** @return array<string, string> module => label, for a storefront id */
    public static function optionsForStorefront(int|string|null $storefrontId): array
    {
        $template = $storefrontId ? Storefront::find($storefrontId)?->template : null;
        $modules = $template ? self::forTemplate(app(TemplateManager::class)->get($template)) : [];

        return collect($modules)->map(fn (array $module) => $module['label'])->all();
    }

    /** @return array<string, array{description: string, permissions: array<string>}> */
    public static function defaultRoles(): array
    {
        return collect(config('client_modules.default_roles', []))
            ->map(fn (array $role) => [
                'description' => $role['description'],
                'permissions' => self::expand($role['permissions']),
            ])
            ->all();
    }

    /** Turn ['orders' => '*', 'customers' => ['view']] into ['orders.view', 'orders.update', 'customers.view']. */
    private static function expand(array $grants): array
    {
        $permissions = [];
        foreach (self::all() as $key => $module) {
            $actions = $grants[$key] ?? $grants['*'] ?? [];
            foreach ($actions === '*' ? $module['actions'] : array_intersect((array) $actions, $module['actions']) as $action) {
                $permissions[] = $key.'.'.$action;
            }
        }

        return $permissions;
    }
}
