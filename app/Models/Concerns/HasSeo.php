<?php

namespace App\Models\Concerns;

use App\Support\Media;

/**
 * Shared accessors for the meta_title / meta_description / og_image / canonical_url / noindex columns.
 * Models may override seoFallbackTitle(), seoFallbackDescription() and seoFallbackImage().
 */
trait HasSeo
{
    public function seoTitle(): string
    {
        return $this->meta_title ?: $this->seoFallbackTitle();
    }

    public function seoDescription(): ?string
    {
        $text = $this->meta_description ?: $this->seoFallbackDescription();

        return $text ? str(strip_tags($text))->squish()->limit(160)->toString() : null;
    }

    public function seoImage(): ?string
    {
        return Media::url($this->og_image ?: $this->seoFallbackImage());
    }

    protected function seoFallbackTitle(): string
    {
        return $this->name ?? $this->title ?? config('app.name');
    }

    protected function seoFallbackDescription(): ?string
    {
        return $this->description ?? $this->excerpt ?? null;
    }

    protected function seoFallbackImage(): ?string
    {
        return $this->image ?? null;
    }
}
