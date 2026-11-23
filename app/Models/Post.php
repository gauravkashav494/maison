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

class Post extends Model
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

    protected $casts = ['published_at' => 'datetime', 'is_featured' => 'boolean', 'noindex' => 'boolean'];

    public function scopePublished(Builder $q): Builder
    {
        return $q->whereNotNull('published_at')->where('published_at', '<=', now())->orderByDesc('published_at');
    }

    public function getImageUrlAttribute(): ?string
    {
        return Media::url($this->image);
    }

    public function getUrlAttribute(): string
    {
        return route('journal.show', $this->slug);
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
