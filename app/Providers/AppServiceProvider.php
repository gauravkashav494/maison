<?php

namespace App\Providers;

use App\Admin\StoreContext;
use App\Clients\ClientAccess;
use App\Clients\ClientContext;
use App\Clients\ClientDatabase;
use App\Models\Client;
use App\Models\ClientRole;
use App\Models\ClientUser;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\Plan;
use App\Models\PlatformActivity;
use App\Models\Storefront;
use App\Models\User;
use App\Policies\ClientPolicy;
use App\Policies\ClientRolePolicy;
use App\Policies\ClientUserPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\PlanPolicy;
use App\Policies\PlatformActivityPolicy;
use App\Policies\StorefrontPolicy;
use App\Policies\UserPolicy;
use App\Support\DatabaseMacros;
use App\Templates\TemplateManager;
use Filament\Schemas\Components\Form;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Filament\View\PanelsIconAlias;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StoreContext::class);
        // One resolved client per request: the scopes, the policies and the connection all
        // read the same instance, so they cannot disagree about who is being served.
        $this->app->singleton(ClientContext::class);
        $this->app->singleton(ClientDatabase::class);
        $this->app->singleton(TemplateManager::class, fn () => new TemplateManager(config('templates.templates', [])));
    }

    public function boot(): void
    {
        $this->registerPanelIcons();

        // Picking a module in the sidebar collapses it to icons (desktop too, not only on phones), so
        // the page gets the room; the ☰ button opens it again. Group headers do not count as a pick.
        \Filament\Support\Facades\FilamentView::registerRenderHook(
            \Filament\View\PanelsRenderHook::BODY_END,
            fn () => new \Illuminate\Support\HtmlString(<<<'HTML'
                <script>
                    document.addEventListener('click', (event) => {
                        const item = event.target.closest('.fi-main-sidebar a.fi-sidebar-item-btn');
                        if (! item || event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) return;
                        window.Alpine?.store('sidebar')?.close();
                    });
                </script>
                HTML),
        );

        // The soft-delete filter on every table, in the same words as the "Delete" action.
        \Filament\Tables\Filters\TrashedFilter::configureUsing(fn (\Filament\Tables\Filters\TrashedFilter $filter) => $filter
            ->label('Deleted items')
            ->placeholder('Hide deleted')
            ->trueLabel('Show all')
            ->falseLabel('Only deleted'));

        // Clicking a table row does nothing: records open from the ⋮ menu (View / Edit) only.
        // Setting the URL here also stops resource list pages adding their default row link.
        Table::configureUsing(fn (Table $table) => $table->recordUrl(null));

        // Validation is the server's job: no browser "Please fill out this field" pop-ups, every
        // form is checked by its Laravel rules and the messages show under each field.
        Form::configureUsing(
            fn (Form $form) => $form->extraAttributes(['novalidate' => true], merge: true),
        );

        // database/migrations/client is NOT part of the default run: the main database gets its
        // tables from the ordinary migrations, and that folder is applied only inside a client's
        // own database by clients:provision.
        DatabaseMacros::register();

        // Admin authorization: one scoped policy per template-owned model, plus platform modules.
        foreach (StoreContext::SCOPED_MODELS as $model) {
            Gate::policy($model, 'App\\Policies\\Scoped\\'.class_basename($model).'Policy');
        }
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Storefront::class, StorefrontPolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Plan::class, PlanPolicy::class);
        Gate::policy(ClientUser::class, ClientUserPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(ClientRole::class, ClientRolePolicy::class);
        Gate::policy(PlatformActivity::class, PlatformActivityPolicy::class);

        // Inline table edits write straight to the row without calling a policy.
        ToggleColumn::configureUsing(fn ($column) => $column->disabled(fn (?Model $record) => ! ClientAccess::canEditInline($record)));
        SelectColumn::configureUsing(fn ($column) => $column->disabled(fn (?Model $record) => ! ClientAccess::canEditInline($record)));

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

    /** Sidebar toggle: the classic hamburger (☰) for both opening and collapsing, instead of chevrons. */
    private function registerPanelIcons(): void
    {
        $menu = Heroicon::Bars3;

        FilamentIcon::register([
            PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON => $menu,
            PanelsIconAlias::SIDEBAR_EXPAND_BUTTON => $menu,
            PanelsIconAlias::TOPBAR_CLOSE_SIDEBAR_BUTTON => Heroicon::XMark,
            PanelsIconAlias::TOPBAR_OPEN_SIDEBAR_BUTTON => $menu,
        ]);
    }
}
