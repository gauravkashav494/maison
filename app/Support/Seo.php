<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Value object handed to the <x-seo> component. Built from a model using HasSeo,
 * or manually for static/derived pages.
 */
class Seo
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $image = null,
        public ?string $canonical = null,
        public bool $noindex = false,
        public string $type = 'website',
        public array $jsonLd = [],
        public bool $appendSuffix = true,
    ) {}

    public static function forHome(): self
    {
        return new self(
            title: setting('seo.default_title', config('app.name')),
            description: setting('seo.default_description', setting('site.description')),
            image: Media::url(setting('seo.default_og_image')),
            canonical: url('/'),
            appendSuffix: false,
            jsonLd: [
                self::organization(),
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebSite',
                    'name' => setting('site.name'),
                    'url' => url('/'),
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => url('/search').'?q={search_term_string}',
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
            ],
        );
    }

    /** @param Model&\App\Models\Concerns\HasSeo $model */
    public static function forModel(Model $model, ?string $url = null, string $type = 'website', array $jsonLd = []): self
    {
        return new self(
            title: $model->seoTitle(),
            description: $model->seoDescription() ?? setting('seo.default_description'),
            image: $model->seoImage() ?? Media::url(setting('seo.default_og_image')),
            canonical: $model->canonical_url ?: ($url ?? url()->current()),
            noindex: (bool) $model->noindex,
            type: $type,
            jsonLd: $jsonLd,
        );
    }

    public static function simple(string $title, ?string $description = null, bool $noindex = false): self
    {
        return new self(
            title: $title,
            description: $description ?? setting('seo.default_description'),
            image: Media::url(setting('seo.default_og_image')),
            canonical: url()->current(),
            noindex: $noindex,
        );
    }

    public function fullTitle(): string
    {
        return $this->appendSuffix ? $this->title.setting('seo.title_suffix', '') : $this->title;
    }

    public static function organization(): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => setting('site.name'),
            'url' => url('/'),
            'logo' => Media::url(setting('seo.default_og_image')),
            'email' => setting('site.contact_email'),
            'telephone' => setting('site.contact_phone'),
            'sameAs' => array_values(array_filter([
                setting('site.social_instagram'),
                setting('site.social_pinterest'),
                setting('site.social_facebook'),
                setting('site.social_youtube'),
            ])),
        ]);
    }
}
