<?php

namespace App\Templates;

use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Registry of storefront templates and the switch that says which one is live.
 * The active template is stored in the "appearance" settings group so it can be
 * changed from the admin without a deploy; an admin may also preview any template
 * for their own session without affecting visitors.
 */
class TemplateManager
{
    public const SETTING = 'appearance';

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

    /** The template activated in the admin (what visitors see). */
    public function activeId(): string
    {
        $id = (string) Setting::get(self::SETTING.'.active_template', config('templates.default'));

        return $this->has($id) ? $id : config('templates.default');
    }

    public function active(): Template
    {
        return $this->get($this->activeId());
    }

    /**
     * The template rendering the current request: the admin preview when one is
     * set for this session, otherwise the active template.
     */
    public function current(): Template
    {
        return $this->current ??= $this->get($this->previewId() ?? $this->activeId());
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

        return $id && $this->has($id) && $id !== $this->activeId() ? $id : null;
    }

    public function isPreviewing(): bool
    {
        return $this->previewId() !== null;
    }

    public function activate(string $id): void
    {
        $template = $this->get($id);
        Setting::set(self::SETTING, array_replace(Setting::get(self::SETTING, []) ?: [], [
            'active_template' => $template->id(),
            'activated_at' => now()->toIso8601String(),
        ]));
        Cache::flush(); // menus + settings caches
        $this->current = null;
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
