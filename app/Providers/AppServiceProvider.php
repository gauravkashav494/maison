<?php

namespace App\Providers;

use App\Models\Menu;
use App\Templates\TemplateManager;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TemplateManager::class, fn () => new TemplateManager(config('templates.templates', [])));
    }

    public function boot(): void
    {
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
