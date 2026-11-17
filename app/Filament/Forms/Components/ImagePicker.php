<?php

namespace App\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Picks some of the images already uploaded elsewhere in the form (a product's own images) instead
 * of uploading new ones — how a combination's photos are chosen, as on most stores. The state is
 * the list of chosen paths, the same shape an upload field saves, so nothing else changes.
 *
 * Images still being uploaded cannot be picked until the product is saved (they have no path yet).
 * A path chosen earlier that is no longer offered (e.g. a photo once uploaded on the combination
 * itself) stays listed and chosen, so opening and saving the form never loses it.
 */
class ImagePicker extends Field
{
    protected string $view = 'filament.forms.components.image-picker';

    /** @var array<int, string>|Closure */
    protected array|Closure $images = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);
        $this->afterStateHydrated(fn (ImagePicker $component, $state) => $component->state(array_values(array_filter((array) $state, 'is_string'))));
        $this->dehydrateStateUsing(fn ($state) => array_values(array_unique(array_filter((array) $state, fn ($p) => is_string($p) && $p !== ''))));
    }

    protected bool $showsInlineLabel = true;

    /** Leaves out the label printed above the thumbnails, where the row already names them. */
    public function withoutInlineLabel(): static
    {
        $this->showsInlineLabel = false;

        return $this;
    }

    public function showsInlineLabel(): bool
    {
        return $this->showsInlineLabel;
    }

    /** The images that can be picked: paths on the public disk, or full URLs. */
    public function images(array|Closure $images): static
    {
        $this->images = $images;

        return $this;
    }

    /** @return list<array{path: string, url: string}> */
    public function getOptions(): array
    {
        $offered = array_filter((array) $this->evaluate($this->images), fn ($p) => is_string($p) && $p !== '');
        $chosen = array_filter((array) $this->getState(), fn ($p) => is_string($p) && $p !== '');

        return collect([...$offered, ...$chosen])->unique()->values()
            ->map(fn (string $path) => ['path' => $path, 'url' => self::url($path)])
            ->all();
    }

    /** True when the form holds images that are still uploading (they can be picked once saved). */
    public function hasPendingImages(): bool
    {
        return collect((array) $this->evaluate($this->images))->contains(fn ($p) => $p !== null && ! is_string($p));
    }

    /** Same-host URL, as the upload field uses (the disk URL carries APP_URL; a client edits on its subdomain). */
    private static function url(string $path): string
    {
        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        $url = Storage::disk('public')->url($path);

        return parse_url($url, PHP_URL_PATH) ?: $url;
    }
}
