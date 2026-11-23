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
use Illuminate\Database\Eloquent\SoftDeletes;

/** A city / locality served by the business, with its own SEO landing page. */
class ServiceArea extends Model
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

    protected $casts = ['localities' => 'array', 'is_active' => 'boolean', 'noindex' => 'boolean'];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->ordered();
    }

    /** The storefront order: as dragged in the service areas list, then A to Z for those sharing a place. */
    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }

    public function getImageUrlAttribute(): ?string
    {
        return Media::url($this->image);
    }

    public function getUrlAttribute(): string
    {
        return route('areas.show', $this->slug);
    }

    protected function seoFallbackTitle(): string
    {
        return 'Plumbing services in '.$this->name;
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
