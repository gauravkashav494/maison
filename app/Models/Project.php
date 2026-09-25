<?php

namespace App\Models;

use App\Models\Concerns\UsesStoreConnection;
use App\Models\Concerns\BelongsToTemplate;
use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Before/after showcase of completed plumbing work. */
class Project extends Model
{
    use UsesStoreConnection;

    use BelongsToTemplate;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'completed_on' => 'date'];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort_order')->orderByDesc('completed_on');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function getBeforeUrlAttribute(): ?string
    {
        return Media::url($this->before_image);
    }

    public function getAfterUrlAttribute(): ?string
    {
        return Media::url($this->after_image);
    }
}
