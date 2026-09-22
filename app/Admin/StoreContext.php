<?php

namespace App\Admin;

use App\Models\Category;
use App\Models\Collection;
use App\Models\ContactMessage;
use App\Models\Coupon;
use App\Models\Faq;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Project;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\ServiceRequest;
use App\Models\Storefront;
use App\Models\Subscriber;
use App\Models\Testimonial;
use App\Models\User;
use App\Templates\Template;
use App\Templates\TemplateManager;

/**
 * Which store the admin session is working in.
 *
 * Store owners are locked to the template of their storefront (taken from the account,
 * never from the request). Super admins see everything, or narrow to one store with the
 * topbar switcher. ApplyAdminStoreScope resolves the context once per request and applies
 * the AdminStoreScope global scope to every model listed here.
 */
class StoreContext
{
    public const SESSION_KEY = 'admin.store';

    /** Models whose rows belong to one template (the `template` column; pages use storefront_template). */
    public const SCOPED_MODELS = [
        Product::class, Category::class, Collection::class, Coupon::class, Order::class, Review::class,
        Page::class, Post::class, Faq::class, Menu::class, ContactMessage::class, Subscriber::class,
        Service::class, ServiceArea::class, Testimonial::class, Project::class, ServiceRequest::class,
    ];

    /** Modules that only make sense for a catalogue (product) store. */
    public const CATALOGUE_MODELS = [Product::class, Category::class, Collection::class, Coupon::class, Order::class, Review::class];

    /** Modules that only make sense for a service business. */
    public const SERVICE_MODELS = [Service::class, ServiceArea::class, Testimonial::class, Project::class, ServiceRequest::class];

    private ?User $user = null;

    private ?string $template = null;

    private bool $locked = false;

    private bool $resolved = false;

    public function resolve(?User $user, ?string $chosenTemplate = null): void
    {
        $this->user = $user;
        $this->resolved = true;
        $this->locked = false;
        $this->template = null;

        if (! $user || ! $user->role) {
            return;
        }
        if ($user->isSuperAdmin()) {
            $this->template = $chosenTemplate && app(TemplateManager::class)->has($chosenTemplate) ? $chosenTemplate : null;

            return;
        }
        // Store owner: confined to the store on the account.
        $this->template = $user->managedTemplateId();
        $this->locked = true;
    }

    public function user(): ?User
    {
        return $this->user;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    /** Template id the session is narrowed to; null = all stores. */
    public function template(): ?string
    {
        return $this->template;
    }

    public function templateObject(): ?Template
    {
        return $this->template ? app(TemplateManager::class)->get($this->template) : null;
    }

    public function storefront(): ?Storefront
    {
        return $this->template ? Storefront::where('template', $this->template)->first() : null;
    }

    /** True for store owners: the template cannot be changed and is forced onto saved rows. */
    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->user?->isSuperAdmin();
    }

    /** Can this session open the given admin module (model class)? */
    public function allowsModel(string $model): bool
    {
        if (! $this->user) {
            return false;
        }
        if ($this->isSuperAdmin()) {
            return true;
        }
        if (! $this->template) {
            return false;
        }
        $services = (bool) $this->templateObject()?->supportsServices();
        if (in_array($model, self::CATALOGUE_MODELS, true)) {
            return ! $services;
        }
        if (in_array($model, self::SERVICE_MODELS, true)) {
            return $services;
        }

        return in_array($model, self::SCOPED_MODELS, true);
    }

    /** Can this session see rows of the given template? (null template = shared rows, super admin only) */
    public function allowsTemplate(?string $template): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->template !== null && $template === $this->template;
    }

    /** Can this session open a template's settings page? */
    public function allowsTemplatePage(string $pageClass): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        $template = $this->templateObject();

        return $template && in_array($pageClass, $template->adminPages(), true);
    }

    /** Label shown in the topbar. */
    public function label(): string
    {
        if ($this->template) {
            return $this->storefront()?->name ?? $this->templateObject()?->name() ?? $this->template;
        }

        return 'All stores';
    }
}
