<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use App\Models\Concerns\BelongsToTemplate;
use App\Models\Concerns\GeneratesSlug;
use App\Models\Concerns\UsesClientConnection;
use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Before/after showcase of completed plumbing work. */
class Project extends Model
{
    use BelongsToClient;
    use BelongsToTemplate;

    // After BelongsToClient and BelongsToTemplate: their saving hooks fill the client and
    // template the unique slug is checked within.
    use GeneratesSlug;
    use SoftDeletes;
    use UsesClientConnection;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'completed_on' => 'date'];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->ordered();
    }

    /**
     * The storefront order: as dragged in the projects list, then for those sharing a place the
     * most recently completed first, and the newest added among those with the same (or no) date.
     */
    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderByDesc('completed_on')->orderByDesc('id');
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

    /** The slug is made from the title. */
    protected function slugSource(): string
    {
        return (string) $this->title;
    }

    /** Platform content (no store): each storefront template may have its own record with this slug. */
    protected function slugUniqueWithin(): array
    {
        return [static::templateColumn()];
    }
}
