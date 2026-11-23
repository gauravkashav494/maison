<?php

namespace App\Models\Concerns;

use App\Clients\ClientContext;
use Illuminate\Support\Str;

/**
 * Fills a blank slug from the record's name when it is saved ("Oxford Shirt" → oxford-shirt),
 * adding -2, -3 … when another record of the same client already has it (also one in the
 * trash, which could be restored). A slug that is set is never changed, so renaming a record
 * keeps its URL. Use after BelongsToClient, whose saving hook fills client_id first.
 */
trait GeneratesSlug
{
    public static function bootGeneratesSlug(): void
    {
        static::saving(function ($model) {
            if (blank($model->slug)) {
                $model->slug = $model->uniqueSlug($model->slugSource());
            }
        });
    }

    /** The text the slug is made from. */
    protected function slugSource(): string
    {
        return (string) $this->name;
    }

    /**
     * Columns a slug of platform content (no client) only has to be unique within. Content kept
     * per storefront template (pages, posts, services …) returns its template column, so each
     * design can have its own "about" page. A store's own records ignore this: its shared pages
     * and its own are served at the same addresses, so a slug is used once per store.
     *
     * @return list<string>
     */
    protected function slugUniqueWithin(): array
    {
        return [];
    }

    /**
     * Slugs this record may not take, because another route already answers that address.
     *
     * @return list<string>
     */
    protected function reservedSlugs(): array
    {
        return [];
    }

    public function uniqueSlug(string $text): string
    {
        $base = Str::limit(Str::slug($text), 240, '') ?: Str::kebab(class_basename($this));
        $clientId = $this->client_id ?? app(ClientContext::class)->id();

        // A null client or template matches null (where() turns it into whereNull).
        $taken = fn (string $slug) => static::withoutGlobalScopes()
            ->where('client_id', $clientId)
            ->where('slug', $slug)
            ->when($this->exists, fn ($q) => $q->whereKeyNot($this->getKey()))
            ->when($clientId === null, fn ($q) => $q->where(fn ($q) => array_map(fn (string $column) => $q->where($column, $this->{$column}), $this->slugUniqueWithin())))
            ->exists();

        $reserved = $this->reservedSlugs();
        $slug = $base;
        for ($n = 2; in_array($slug, $reserved, true) || $taken($slug); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }
}
