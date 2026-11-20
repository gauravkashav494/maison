<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use App\Models\Concerns\BelongsToTemplate;
use App\Models\Concerns\GeneratesSlug;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\UsesClientConnection;
use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A bookable plumbing service (Plumbing Services template). */
class Service extends Model
{
    use BelongsToClient;
    use BelongsToTemplate;

    // After BelongsToClient and BelongsToTemplate: their saving hooks fill the client and
    // template the unique slug is checked within.
    use GeneratesSlug;
    use HasSeo;
    use SoftDeletes;
    use UsesClientConnection;

    protected $guarded = [];

    protected $casts = [
        'problems' => 'array',
        'included' => 'array',
        'faqs' => 'array',
        'is_emergency' => 'boolean',
        'is_popular' => 'boolean',
        'is_active' => 'boolean',
        'noindex' => 'boolean',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->ordered();
    }

    /** The storefront order: as dragged in the services list, then A to Z for those sharing a place. */
    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Popular services first ("Popular (shown first on the homepage)"), each group in the
     * storefront order. Replaces any order set before it.
     */
    public function scopePopularFirst(Builder $q): Builder
    {
        return $q->reorder()->orderByDesc('is_popular')->ordered();
    }

    public function scopePopular(Builder $q): Builder
    {
        return $q->where('is_popular', true);
    }

    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return Media::url($this->image);
    }

    public function getUrlAttribute(): string
    {
        return route('services.show', $this->slug);
    }

    public function getBookUrlAttribute(): string
    {
        return route('booking.create', ['service' => $this->slug]);
    }

    protected function seoFallbackDescription(): ?string
    {
        return $this->excerpt ?: $this->description;
    }

    /** Platform content (no store): each storefront template may have its own record with this slug. */
    protected function slugUniqueWithin(): array
    {
        return [static::templateColumn()];
    }
}
