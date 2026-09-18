<?php

namespace App\Filament\Pages;

use App\Templates\Template;
use App\Templates\TemplateManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Appearance → Templates: choose which storefront template visitors see.
 * Switching only changes the presentation layer; every product, category, page,
 * order, customer and setting is shared and kept.
 */
class Templates extends Page
{
    protected string $view = 'filament.pages.templates';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|\UnitEnum|null $navigationGroup = 'Appearance';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Templates';

    protected static ?string $slug = 'templates';

    /** @return Collection<string, Template> */
    public function getTemplates(): Collection
    {
        return app(TemplateManager::class)->all();
    }

    public function getActiveId(): string
    {
        return app(TemplateManager::class)->activeId();
    }

    public function getActivatedAt(): ?string
    {
        return setting('appearance.activated_at');
    }

    public function activateAction(): Action
    {
        return Action::make('activate')
            ->label('Activate')
            ->icon('heroicon-m-check')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => 'Activate the '.$this->template($arguments)->name().' template?')
            ->modalDescription('Visitors will see this template immediately. All products, categories, pages, orders, customers and settings are kept — only the storefront design changes. You can switch back at any time.')
            ->modalSubmitActionLabel('Yes, activate')
            ->action(function (array $arguments) {
                $template = $this->template($arguments);
                if (! $template->isInstalled()) {
                    Notification::make()->warning()->title('Install the demo content first')->body('This template has no content yet. Use "Install demo content" and then activate it.')->send();

                    return;
                }
                app(TemplateManager::class)->activate($template->id());
                Notification::make()->success()->title($template->name().' is now live')->body('Visitors see the new template. Open the storefront to check it.')->send();
            });
    }

    public function previewAction(): Action
    {
        return Action::make('preview')
            ->label('Preview')
            ->icon('heroicon-m-eye')
            ->color('gray')
            ->url(fn (array $arguments) => url('/?preview_template='.$this->template($arguments)->id()))
            ->openUrlInNewTab();
    }

    public function installAction(): Action
    {
        return Action::make('install')
            ->label('Install demo content')
            ->icon('heroicon-m-arrow-down-tray')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => 'Install '.$this->template($arguments)->name().' demo content?')
            ->modalDescription('Adds this template\'s settings, navigation menus and a starter catalogue (categories and products) so it renders with real content. Existing data is never modified or deleted.')
            ->action(function (array $arguments) {
                $template = $this->template($arguments);
                $template->install();
                Notification::make()->success()->title('Demo content installed')->body('You can now preview or activate '.$template->name().'.')->send();
            });
    }

    private function template(array $arguments): Template
    {
        return app(TemplateManager::class)->get((string) ($arguments['template'] ?? ''));
    }
}
