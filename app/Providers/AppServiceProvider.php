<?php

namespace App\Providers;

use App\Admin\StoreContext;
use App\Models\Menu;
use App\Models\Store;
use App\Models\Storefront;
use App\Models\User;
use App\Policies\StorefrontPolicy;
use App\Policies\StorePolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use App\Templates\TemplateManager;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StoreContext::class);
        $this->app->singleton(\App\Stores\TenantManager::class);
        $this->app->singleton(TemplateManager::class, fn () => new TemplateManager(config('templates.templates', [])));
    }

    public function boot(): void
    {
        \App\Support\DatabaseMacros::register();

        // Admin authorization: one scoped policy per template-owned model, plus platform modules.
        foreach (StoreContext::SCOPED_MODELS as $model) {
            Gate::policy($model, 'App\\Policies\\Scoped\\'.class_basename($model).'Policy');
        }
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Storefront::class, StorefrontPolicy::class);
        Gate::policy(Store::class, StorePolicy::class);

        // Every template's view folder is registered as a fallback location so Blade can resolve
        // template components (<x-section-head>, <x-empty-state>, ...) when compiling outside a
        // request, e.g. `php artisan view:cache` during deployment. At runtime ResolveTemplate still
        // prepends the active template's folder, so it always wins over these fallbacks.
        foreach (app(TemplateManager::class)->all() as $template) {
            if ($path = $template->viewPath()) {
                View::addLocation(resource_path('views/'.$path));
            }
        }

        // Site chrome data shared with the storefront layout and its partials. Menus are
        // resolved for the template rendering the request (alias => cached tree).
        View::composer(['layouts.app', 'partials.*'], function (ViewInstance $view) {
            $template = template();

            $menus = [];
            foreach ($template->menuLocations() as $alias => [$location]) {
                $menus[$alias] = Menu::tree($location);
            }

            $view->with([
                'site' => setting('site', []),
                'menus' => $menus,
            ] + $template->viewData());
        });
    }
}
