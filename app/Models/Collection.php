<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use App\Models\Concerns\BelongsToTemplate;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\UsesClientConnection;
use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Collection extends Model
{
    use BelongsToClient;
    use BelongsToTemplate;
    use HasSeo;
    use SoftDeletes;
    use UsesClientConnection;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'is_featured' => 'boolean', 'noindex' => 'boolean'];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('sort_order')->orderByPivot('sort_order');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort_order');
    }

    public function getImageUrlAttribute(): ?string
    {
        return Media::url($this->image);
    }

    public function getHeroImageUrlAttribute(): ?string
    {
        return Media::url($this->hero_image ?: $this->image);
    }

    public function getUrlAttribute(): string
    {
        return route('collections.show', $this->slug);
    }
}
