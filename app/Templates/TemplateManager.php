<?php

namespace App\Templates;

use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Registry of storefront templates. Which template a request renders comes from the
 * store its host belongs to (see ResolveTemplate); an admin may also preview any
 * template for their own session without affecting visitors.
 */
class TemplateManager
{
    public const PREVIEW_SESSION = 'template.preview';

    /** @var Collection<string, Template> */
    private Collection $templates;

    private ?Template $current = null;

    public function __construct(array $classes)
    {
        $this->templates = collect($classes)
            ->map(fn (string $class) => app($class))
            ->keyBy(fn (Template $t) => $t->id());
    }

    /** @return Collection<string, Template> */
    public function all(): Collection
    {
        return $this->templates;
    }

    public function has(string $id): bool
    {
        return $this->templates->has($id);
    }

    public function get(string $id): Template
    {
        return $this->templates[$id] ?? throw new InvalidArgumentException("Unknown template [{$id}].");
    }

    /** Fallback template when a request belongs to no store (config "templates.default"). */
    public function defaultId(): string
    {
        $id = (string) config('templates.default');

        return $this->has($id) ? $id : (string) $this->templates->keys()->first();
    }

    /**
     * The template rendering the current request: set by ResolveTemplate from the host's
     * store, or the admin preview for this session, otherwise the default.
     */
    public function current(): Template
    {
        return $this->current ??= $this->get($this->previewId() ?? $this->defaultId());
    }

    /** Called by the middleware once the request (and its session) is known. */
    public function setCurrent(Template $template): void
    {
        $this->current = $template;
    }

    public function previewId(): ?string
    {
        if (! app()->bound('session.store') || ! session()->isStarted()) {
            return null;
        }
        $id = session(self::PREVIEW_SESSION);

        return $id && $this->has($id) ? $id : null;
    }

    public function isPreviewing(): bool
    {
        return $this->previewId() !== null;
    }

    /** Every menu location across all templates: key => label. */
    public function allMenuLocations(): array
    {
        $out = [];
        foreach ($this->templates as $t) {
            foreach ($t->locationLabels() as $key => $label) {
                $out[$key] = $t->name().' — '.$label;
            }
        }

        return $out;
    }

    /**
     * Menu locations to offer in the admin: only the given template's own (as "Header quick
     * links", not "Indian Grocery — Header quick links"), or every template's when no template
     * is given (a super admin working across all stores).
     */
    public function menuLocationsFor(?string $templateId): array
    {
        return $templateId !== null && $this->has($templateId)
            ? $this->get($templateId)->locationLabels()
            : $this->allMenuLocations();
    }

    /** Which template owns a menu location (null when unknown). */
    public function templateForLocation(string $location): ?Template
    {
        return $this->templates->first(fn (Template $t) => in_array($location, $t->locationKeys(), true));
    }

    /** id => name, for admin selects. */
    public function options(): array
    {
        return $this->templates->map(fn (Template $t) => $t->name())->all();
    }
}
