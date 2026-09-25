<?php

namespace App\Models;

use App\Models\Concerns\UsesStoreConnection;
use App\Models\Concerns\BelongsToTemplate;
use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Customer review of a service (not tied to a product like Review is). */
class Testimonial extends Model
{
    use UsesStoreConnection;

    use BelongsToTemplate;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'rating' => 'integer'];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort_order')->orderByDesc('id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return Media::url($this->image);
    }
}
