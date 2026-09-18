<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTemplate;
use App\Models\Concerns\HasSeo;
use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use BelongsToTemplate;

    use HasSeo;

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
}
