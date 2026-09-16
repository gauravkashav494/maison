<?php

namespace App\Providers;

use App\Models\Menu;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Site chrome data shared with the storefront layout and its partials.
        View::composer(['layouts.app', 'partials.*'], function (ViewInstance $view) {
            $view->with([
                'site' => setting('site', []),
                'menus' => [
                    'header' => Menu::tree('header'),
                    'footer_shop' => Menu::tree('footer_shop'),
                    'footer_collections' => Menu::tree('footer_collections'),
                    'footer_about' => Menu::tree('footer_about'),
                    'footer_service' => Menu::tree('footer_service'),
                    'legal' => Menu::tree('legal'),
                ],
            ]);
        });
    }
}
