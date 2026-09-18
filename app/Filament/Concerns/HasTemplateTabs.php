<?php

namespace App\Filament\Concerns;

use App\Templates\TemplateManager;
use Filament\Actions\CreateAction;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Splits a resource list into one tab per storefront template plus "Shared"
 * (records visible in every template) and "All". The active storefront template
 * is selected by default, and "New …" pre-fills the template of the open tab.
 */
trait HasTemplateTabs
{
    public function getTabs(): array
    {
        $model = static::getResource()::getModel();
        $column = $model::templateColumn();
        $tabs = [];

        foreach (app(TemplateManager::class)->all() as $template) {
            $id = $template->id();
            $tabs[$id] = Tab::make($template->name())
                ->modifyQueryUsing(fn (Builder $query) => $query->where($column, $id))
                ->badge(fn () => $model::query()->where($column, $id)->count());
        }

        $tabs['shared'] = Tab::make('Shared')
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNull($column))
            ->badge(fn () => $model::query()->whereNull($column)->count());

        $tabs['all'] = Tab::make('All');

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return app(TemplateManager::class)->activeId();
    }

    /** "New …" opens the create form with the current tab's template pre-selected. */
    protected function templateCreateAction(): CreateAction
    {
        return CreateAction::make()->url(function () {
            $tab = (string) ($this->activeTab ?? '');
            $template = app(TemplateManager::class)->has($tab) ? $tab : null;

            return static::getResource()::getUrl('create', array_filter(['template' => $template]));
        });
    }
}
