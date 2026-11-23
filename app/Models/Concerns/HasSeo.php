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
        return $this->meta_description
            ? str(strip_tags($this->meta_description))->squish()->limit(160)->toString()
            : $this->seoDefaultDescription();
    }

    public function seoImage(): ?string
    {
        return Media::url($this->og_image ?: $this->seoFallbackImage());
    }

    /** What the title is when meta_title is left blank (shown as a placeholder in the SEO module). */
    public function seoDefaultTitle(): string
    {
        return $this->seoFallbackTitle();
    }

    public function seoDefaultDescription(): ?string
    {
        $text = $this->seoFallbackDescription();

        return $text ? str(strip_tags($text))->squish()->limit(160)->toString() : null;
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
