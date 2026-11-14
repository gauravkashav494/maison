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
 * Platform → Templates: the storefront templates a store can be built on. Each store's
 * template is set on the store itself (its host decides what visitors see); here a super
 * admin installs demo content, builds the public theme preview and opens a template's settings.
 */
class Templates extends Page
{
    protected string $view = 'filament.pages.templates';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Templates';

    // Not /templates: public/templates holds the stores' app icons, and the web server would
    // answer that folder (403) before the app.
    protected static ?string $slug = 'appearance/templates';

    /** Installing content and building theme previews: super admin only. */
    public static function canAccess(): bool
    {
        return (bool) filament()->auth()->user()?->isSuperAdmin();
    }

    /** @return Collection<string, Template> */
    public function getTemplates(): Collection
    {
        return app(TemplateManager::class)->all();
    }

    public function previewAction(): Action
    {
        return Action::make('preview')
            ->label('Preview')
            ->icon('heroicon-m-eye')
            ->color('gray')
            // The public theme preview, theme-<template>.<base domain>.
            ->url(fn (array $arguments) => \App\Models\Client::themeFor($this->template($arguments)->id())?->publicUrl())
            ->visible(fn (array $arguments) => \App\Models\Client::themeFor($this->template($arguments)->id()) !== null)
            ->openUrlInNewTab();
    }

    /** Builds the public theme preview with sample content, or adds what is missing to it. */
    public function themeAction(): Action
    {
        return Action::make('theme')
            ->label(fn (array $arguments) => \App\Models\Client::themeFor($this->template($arguments)->id()) ? 'Update theme' : 'Set up theme')
            ->icon('heroicon-m-sparkles')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => 'Theme preview of '.$this->template($arguments)->name())
            ->modalDescription(fn (array $arguments) => 'A public sample store at '
                .\App\Models\Client::themeSubdomain($this->template($arguments)->id()).'.'.\App\Models\Client::baseDomain()
                .', with its own sample content. Visitors can browse it but cannot order, book, sign in or send forms. '
                .'Setting it up takes about half a minute; updating it only adds what is missing.')
            ->modalSubmitActionLabel('Go ahead')
            ->action(function (array $arguments) {
                $result = app(\App\Clients\ThemeBuilder::class)->build($this->template($arguments)->id());
                $result['ok']
                    ? Notification::make()->success()->title('Theme preview ready')->body($result['client']->publicUrl())->send()
                    : Notification::make()->danger()->persistent()->title('The theme preview could not be set up')->body($result['message'])->send();
            });
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
                Notification::make()->success()->title('Demo content installed')->body($template->name().' is ready to preview and to build stores on.')->send();
            });
    }

    private function template(array $arguments): Template
    {
        return app(TemplateManager::class)->get((string) ($arguments['template'] ?? ''));
    }
}
