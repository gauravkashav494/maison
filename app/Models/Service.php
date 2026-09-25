<?php

namespace App\Models;

use App\Models\Concerns\UsesStoreConnection;
use App\Models\Concerns\BelongsToTemplate;
use App\Models\Concerns\HasSeo;
use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A bookable plumbing service (Plumbing Services template). */
class Service extends Model
{
    use UsesStoreConnection;

    use BelongsToTemplate;
    use HasSeo;

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
        return $q->where('is_active', true)->orderBy('sort_order')->orderBy('name');
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
}
