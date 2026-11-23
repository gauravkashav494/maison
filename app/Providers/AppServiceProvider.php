<?php

namespace App\Providers;

use App\Admin\StoreContext;
use App\Clients\ClientAccess;
use App\Clients\ClientContext;
use App\Clients\ClientDatabase;
use App\Clients\ClientPasswordBrokerManager;
use App\Clients\StoreFeatures;
use App\Filament\Pages\SiteSettings;
use App\Filament\Support\RecordToast;
use App\Filament\Support\TimestampColumns;
use App\Filament\Support\ToggleToast;
use App\Http\Controllers\Admin\PanelLogoutController;
use App\Http\Responses\LoginResponse;
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
use App\Support\Activity\ActivityLog;
use App\Support\Activity\SignInActivity;
use App\Support\CustomerOtp;
use App\Support\DatabaseMacros;
use App\Support\PasswordRules;
use App\Templates\TemplateManager;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Auth\Http\Controllers\LogoutController;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Group;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\FiltersResetActionPosition;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsIconAlias;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
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
        // Panel logout signs out the panel account only, not a shopper in the same browser.
        $this->app->bind(LogoutController::class, PanelLogoutController::class);
        // Shopper reset tokens are kept per client (see ClientScopedTokenRepository). `extend`
        // rather than a new binding: the framework's deferred provider would replace that.
        $this->app->extend('auth.password', fn ($manager, $app) => new ClientPasswordBrokerManager($app));
        $this->app->singleton(StoreFeatures::class);
        // After sign-in, never send an account back to a page it is not allowed to open.
        $this->app->bind(\Filament\Auth\Http\Responses\Contracts\LoginResponse::class, LoginResponse::class);
        // One log per request, so a bulk action's held-back lines are written together.
        $this->app->singleton(ActivityLog::class);
        $this->app->singleton(TemplateManager::class, fn () => new TemplateManager(config('templates.templates', [])));
    }

    public function boot(): void
    {
        // One password rule everywhere, including Filament's own profile and reset pages.
        Password::defaults(fn () => PasswordRules::rule());

        $this->registerPanelIcons();
        $this->hideTableFilterIndicators();
        $this->registerTableFilterLayout();
        $this->registerSelects();

        // Saved images in upload fields open large in a lightbox, and load from the browser cache.
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn () => view('filament.partials.image-lightbox'),
        );

        // Site settings: "View on storefront" for the open tab, at the right of the tab bar.
        FilamentView::registerRenderHook(
            SiteSettings::TABS_END_HOOK,
            fn () => view('filament.pages.site-settings-store-link', ['links' => SiteSettings::storefrontLinks()]),
        );

        // Every form save scrolls back to the top, where the "Saved" toast shows.
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn () => view('filament.partials.scroll-top-on-save'),
        );

        // Picking a module in the sidebar collapses it to icons (desktop too, not only on phones), so
        // the page gets the room; the ☰ button opens it again. Group headers do not count as a pick.
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn () => new HtmlString(<<<'HTML'
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
        TrashedFilter::configureUsing(fn (TrashedFilter $filter) => $filter
            ->label('Deleted items')
            ->placeholder('Hide deleted')
            ->trueLabel('Show all')
            ->falseLabel('Only deleted'));

        // View is the same blue as Edit everywhere (Filament draws it grey by default, which reads as disabled).
        ViewAction::configureUsing(fn (ViewAction $action) => $action->color('primary'));

        // Clicking a table row does nothing: records open from the ⋮ menu (View / Edit) only.
        // Setting the URL here also stops resource list pages adding their default row link.
        Table::configureUsing(fn (Table $table) => $table->recordUrl(null));

        // "Created at" and "Updated at" in every table's column menu, hidden until switched on.
        TimestampColumns::register();

        // "Apply columns" in a table's Columns menu applies the choice and closes the menu
        // (Filament leaves it open). close() is the dropdown's own, around the menu.
        Table::configureUsing(fn (Table $table) => $table->columnManagerApplyAction(
            fn (Action $action) => $action->alpineClickHandler('applyTableColumnManager(); close()'),
        ));

        // Filters apply as soon as they change (registerTableFilterLayout()), so there is no Apply
        // button. A table that opts back into an Apply button (deferFilters()) gets one that reads
        // "Apply", and in a filters dropdown it also closes the dropdown.
        Table::configureUsing(fn (Table $table) => $table->filtersApplyAction(
            fn (Action $action) => ($table->getFiltersLayout() === FiltersLayout::Dropdown ? $action->close() : $action)->label('Apply'),
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

        // Activity log: sign-ins of client staff, and one line per bulk action rather than one
        // per record ("moved 12 products to the trash: …"). Lines still held when the request
        // ends (an action that stopped half way) are written then, one by one.
        SignInActivity::register();
        BulkAction::configureUsing(fn (BulkAction $action) => $action
            ->before(fn () => app(ActivityLog::class)->startGroup())
            ->after(fn () => app(ActivityLog::class)->flush()));
        $this->app->terminating(fn () => app(ActivityLog::class)->flush());
        $this->registerRecordToasts();

        // Inline table edits write straight to the row without calling a policy.
        // A toast confirms each switch ("Visible turned on"), on every table.
        ToggleColumn::configureUsing(fn ($column) => $column
            ->disabled(fn (?Model $record) => ! ClientAccess::canEditInline($record))
            ->afterStateUpdated(fn (ToggleColumn $column, $state) => ToggleToast::send($column, $state)));
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

        // @module('reviews') ... @endmodule: shown only while the client has that module switched on.
        Blade::if('module', fn (string $module) => app(StoreFeatures::class)->on($module));
        // @otp … @else … @endotp: whether WhatsApp codes are asked (CustomerOtp::enabled()).
        Blade::if('otp', fn () => CustomerOtp::enabled());

        // Site chrome data shared with the storefront layout and its partials. Menus are
        // resolved for the template rendering the request (alias => cached tree).
        View::composer(['layouts.app', 'partials.*'], function (ViewInstance $view) {
            $template = template();

            // Links into a module switched off for this client are left out (StoreFeatures).
            $menus = [];
            foreach ($template->menuLocations() as $alias => [$location]) {
                $menus[$alias] = app(StoreFeatures::class)->filterMenu(Menu::tree($location));
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
     * Every table shows its filters in a bar above the table that is always open (Above
     * content), as most e-commerce admins do, so staff change a filter without opening anything
     * first, and each filter applies as soon as it changes; the active filters also show as
     * chips in the search row. No table sets this by hand (a
     * table that called filtersLayout(), filtersFormColumns() or filtersFormWidth() itself
     * would override the rule; don't).
     *
     * The bar is as wide as the table, so its fields sit side by side: 1 on a phone, 2 on a
     * tablet, 3 on a laptop and 4 on a wide screen, however many filters the table has. A date
     * filter with several fields takes two of those columns, and Reset sits at the end of the
     * row (theme.css), so a typical bar is one line of fields.
     */
    private function registerTableFilterLayout(): void
    {
        Table::configureUsing(fn (Table $table) => $table
            ->filtersLayout(FiltersLayout::AboveContent)
            // A filter applies as soon as it is picked: no Apply button. The date-range picker
            // has its own Apply in the calendar, so a range applies once both days are chosen.
            ->deferFilters(false)
            // Reset sits at the end of the fields, so the bar needs no heading line (theme.css hides it).
            ->filtersResetActionPosition(FiltersResetActionPosition::Footer)
            // Every field is one column wide. On a wide screen the bar has a column per field (4 to
            // 6), so a table with up to six fields (Product + Variant counts as two) fits on one row.
            ->filtersFormColumns(fn () => ['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => max(4, min(self::filterFieldCount($table), 6))])
            // A filter with several fields (Product / Variant) keeps them on one line, two columns
            // wide, so each of its fields is as wide as any other filter.
            ->filtersFormSchema(fn (array $filters) => array_values(array_map(
                function (Group $group, string $name) use ($table) {
                    $filter = $table->getFilter($name);
                    if (self::filterWidth($filter) < 2) {
                        return $group;
                    }

                    // Every breakpoint, not just sm: the filter's own span of 1 is stored as lg => 1.
                    return $group->columns(['default' => 1, 'sm' => 2])
                        ->columnSpan(['default' => 1, 'sm' => 2, 'lg' => 2, 'xl' => 2, '2xl' => 2]);
                },
                $filters,
                array_keys($filters),
            ))));
    }

    /** Columns a filter takes in the bar: 2 for one with several fields, else 1. */
    private static function filterWidth(?BaseFilter $filter): int
    {
        if ($filter === null || $filter->getColumnSpan() !== 1) { // a span set on the filter itself wins
            return 1;
        }

        return count($filter->getSchemaComponents()) >= 2 ? 2 : 1;
    }

    /** Columns all of a table's filters take together. */
    private static function filterFieldCount(Table $table): int
    {
        return array_sum(array_map(self::filterWidth(...), $table->getFilters()));
    }

    /** A list longer than this can be searched by typing. */
    public const SEARCHABLE_SELECT_FROM = 8;

    /**
     * Every select in the panels (form fields, table filters and inline table selects) opens the
     * panel's own styled list rather than the browser's, so they all look the same. A list with
     * more than SEARCHABLE_SELECT_FROM options, or one read from the database (categories,
     * brands, products…, which grow), can also be searched by typing; a short fixed list (status,
     * payment method) stays a plain pick. A select that sets native(), searchable() or preload()
     * itself keeps its own choice.
     */
    private function registerSelects(): void
    {
        $long = fn (array $options) => count($options) > self::SEARCHABLE_SELECT_FROM;

        Select::configureUsing(fn (Select $select) => $select
            ->native(false)
            // A relationship select answers first, so its options (which ask whether the
            // select is searchable) are never read from inside this check.
            ->searchable(fn () => $select->getRelationshipName() !== null || $long($select->getOptions()))
            ->preload(fn () => $select->getRelationshipName() !== null));

        SelectFilter::configureUsing(fn (SelectFilter $filter) => $filter
            ->native(false)
            ->searchable(fn () => $filter->queriesRelationships() || $long($filter->getOptions()))
            ->preload(fn () => $filter->queriesRelationships()));

        SelectColumn::configureUsing(fn (SelectColumn $column) => $column->native(false));

        // A Yes / No filter left empty reads "All", like every other filter (Filament shows "-").
        // The Deleted items filter keeps its own wording ("Hide deleted").
        TernaryFilter::configureUsing(fn (TernaryFilter $filter) => $filter instanceof TrashedFilter ? $filter : $filter->placeholder('All'));
    }

    /**
     * No "active filters" chips on any table: the filters bar above every table is always open
     * and already shows what is picked, and Reset clears it. A non-blank result replaces
     * Filament's chip row, so it is never drawn.
     */
    private function hideTableFilterIndicators(): void
    {
        FilamentView::registerRenderHook(TablesRenderHook::FILTER_INDICATORS, fn () => new HtmlString('<span hidden></span>'));
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

    /**
     * Toasts that name the record instead of a bare "Deleted" / "Saved", on every table and modal
     * ('Product "Linen Shirt" deleted' / 'Moved to the trash, so it can still be restored.').
     * Create and Edit pages do the same through App\Filament\Support\Pages. An action given its
     * own successNotificationTitle() keeps that title.
     */
    private function registerRecordToasts(): void
    {
        CreateAction::configureUsing(fn (CreateAction $action) => $action
            ->successNotificationTitle(fn (?Model $record) => $record ? RecordToast::createdTitle($record) : 'Created'));

        // Modal edits: the record and what changed, or "No changes to save".
        EditAction::configureUsing(fn (EditAction $action) => $action
            ->successNotification(fn (Notification $notification, ?Model $record) => $record && $notification->getTitle() === __('filament-actions::edit.single.notifications.saved.title')
                ? RecordToast::saved($record, app(ActivityLog::class)->since())
                : $notification));

        DeleteAction::configureUsing(fn (DeleteAction $action) => $action
            ->successNotificationTitle(fn (?Model $record) => ($record = RecordToast::subject($record, 'deleted')) ? RecordToast::deletedTitle($record) : 'Deleted')
            ->successNotification(fn (Notification $notification, ?Model $record) => ($record = RecordToast::subject($record, 'deleted'))
                ? $notification->body(RecordToast::deletedBody($record))
                : $notification));

        ForceDeleteAction::configureUsing(fn (ForceDeleteAction $action) => $action
            ->successNotificationTitle(fn (?Model $record) => ($record = RecordToast::subject($record, 'deleted permanently')) ? RecordToast::forceDeletedTitle($record) : 'Deleted')
            ->successNotification(fn (Notification $notification) => $notification->body('This can’t be undone.')));

        RestoreAction::configureUsing(fn (RestoreAction $action) => $action
            ->successNotificationTitle(fn (?Model $record) => $record ? RecordToast::restoredTitle($record) : 'Restored')
            ->successNotification(fn (Notification $notification, ?Model $record) => $record
                ? $notification->body(RecordToast::restoredBody($record))
                : $notification));

        ReplicateAction::configureUsing(fn (ReplicateAction $action) => $action
            ->successNotificationTitle(fn (?Model $record) => $record ? RecordToast::replicatedTitle($record) : 'Duplicated'));

        // Bulk: "3 products deleted" / "Linen Shirt, Silk Saree and 1 more.", from the lines the
        // bulk action is holding for the activity log. Filament's own words if nothing was logged.
        $bulk = function (BulkAction $action, string $verb): void {
            $default = $action->getSuccessNotificationTitle();
            $action
                ->successNotificationTitle(fn () => RecordToast::bulkTitle($verb, $action->getModel()) ?? $default)
                ->successNotification(fn (Notification $notification) => $notification->body(RecordToast::bulkBody($verb, $action->getModel())));
        };
        DeleteBulkAction::configureUsing(fn (DeleteBulkAction $action) => $bulk($action, 'deleted'));
        ForceDeleteBulkAction::configureUsing(fn (ForceDeleteBulkAction $action) => $bulk($action, 'deleted permanently'));
        RestoreBulkAction::configureUsing(fn (RestoreBulkAction $action) => $bulk($action, 'restored'));
    }
}
