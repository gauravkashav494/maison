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
use Filament\Actions\Action;
use Filament\Schemas\Components\Form;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsIconAlias;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;
use Livewire\Livewire;

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
        $this->registerTableFilterIndicators();
        $this->registerTableFilterLayout();

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

        // "Apply" in the filters dropdown also closes it, so the filtered rows are not hidden
        // behind the panel; the applied filters stay visible as chips in the toolbar. close()
        // keeps the normal apply request and adds Alpine's close. Only for the dropdown layout:
        // the modal layout already closes, and the other layouts have nothing to close.
        Table::configureUsing(fn (Table $table) => $table->filtersApplyAction(
            fn (Action $action) => $table->getFiltersLayout() === FiltersLayout::Dropdown ? $action->close() : $action,
        ));

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
    /**
     * Every table shows its active filter chips in the toolbar, beside the search field, rather
     * than in a separate row under it. The chips stay removable, with the same "remove all".
     *
     * The toolbar is drawn before Filament's own indicator row, so the row is hidden only for a
     * table whose chips were already drawn up top. A table without a search field, filter
     * button or column manager has no such spot and keeps the default row.
     */
    /**
     * Every table's filter panel lays itself out by how many filters it has, so no table sets
     * this by hand (a table that called filtersFormColumns() or filtersFormWidth() itself would
     * override the rule; don't):
     *
     *   1–3 filters   one per row, the default narrow panel
     *   4 filters     2 across, medium panel
     *   5+ filters    3 across, wide panel
     *
     * Columns drop to 2 on a tablet and 1 on a phone. Counted when the panel renders, so
     * filters that are hidden for this user (or this store) do not count.
     */
    private function registerTableFilterLayout(): void
    {
        Table::configureUsing(function (Table $table) {
            $count = fn () => count($table->getFilters());

            $table
                ->filtersFormColumns(fn () => match (true) {
                    $count() >= 5 => ['default' => 1, 'sm' => 2, 'lg' => 3],
                    $count() === 4 => ['default' => 1, 'sm' => 2],
                    default => 1,
                })
                ->filtersFormWidth(fn () => match (true) {
                    $count() >= 5 => Width::FourExtraLarge,
                    $count() === 4 => Width::TwoExtraLarge,
                    default => null, // Filament's default narrow panel
                });
        });
    }

    private function registerTableFilterIndicators(): void
    {
        $drawn = [];

        FilamentView::registerRenderHook(TablesRenderHook::TOOLBAR_SEARCH_BEFORE, function () use (&$drawn) {
            $component = Livewire::current();
            if (! $component instanceof HasTable) {
                return '';
            }

            $table = $component->getTable();
            $indicators = $table->getFilterIndicators();
            if (! $indicators) {
                return '';
            }
            $drawn[$component->getId()] = true;

            return view('filament.partials.table-filter-indicators', [
                'indicators' => $indicators,
                'removeAll' => collect($indicators)->contains(fn (Indicator $indicator) => $indicator->isRemovable())
                    ? $table->getFiltersRemoveAllAction()
                    : null,
            ]);
        });

        // A non-blank result replaces the default row; a blank one lets Filament draw it.
        FilamentView::registerRenderHook(TablesRenderHook::FILTER_INDICATORS, function () use (&$drawn) {
            $id = Livewire::current()?->getId();
            if (! $id || ! isset($drawn[$id])) {
                return '';
            }
            unset($drawn[$id]);

            return new HtmlString('<span hidden></span>');
        });
    }

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
