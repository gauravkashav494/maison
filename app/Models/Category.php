<?php

namespace App\Models;

use App\Models\Concerns\UsesStoreConnection;
use App\Models\Concerns\BelongsToTemplate;
use App\Models\Concerns\HasSeo;
use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use UsesStoreConnection;

    use BelongsToTemplate;

    use HasSeo;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'show_in_menu' => 'boolean', 'noindex' => 'boolean'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeTopLevel(Builder $q): Builder
    {
        return $q->whereNull('parent_id')->orderBy('sort_order');
    }

    public function getImageUrlAttribute(): ?string
    {
        return Media::url($this->image);
    }

    public function getUrlAttribute(): string
    {
        return route('shop.category', $this->slug);
    }
}
