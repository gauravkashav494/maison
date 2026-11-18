<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use App\Models\Concerns\BelongsToTemplate;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\UsesClientConnection;
use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Category extends Model
{
    use BelongsToClient;
    use BelongsToTemplate;
    use HasSeo;
    use SoftDeletes;
    use UsesClientConnection;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'show_in_menu' => 'boolean', 'noindex' => 'boolean'];

    /**
     * Last line of defence against a loop in the tree: imports, seeders or any code saving a
     * category directly get the same check as the admin form.
     */
    protected static function booted(): void
    {
        static::saving(function (Category $category) {
            if (! $category->exists || blank($category->parent_id) || ! $category->isDirty('parent_id')) {
                return;
            }

            $parentId = (int) $category->parent_id;
            if ($parentId === $category->getKey() || in_array($parentId, $category->descendantIds(), true)) {
                throw ValidationException::withMessages([
                    'parent_id' => "A category can't be placed under itself or one of its own sub-categories.",
                ]);
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Ids of every category below this one (children, grandchildren, …). These can't become
     * its parent: that would make a loop. Walks level by level and remembers what it has seen,
     * so it also ends if the data already holds a loop.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $found = [];
        $level = [$this->getKey()];

        while ($level !== []) {
            $level = self::withoutGlobalScopes()
                ->where('client_id', $this->client_id)
                ->whereIn('parent_id', $level)
                ->whereNotIn('id', [$this->getKey(), ...$found])
                ->pluck('id')
                ->all();
            array_push($found, ...$level);
        }

        return $found;
    }

    /** Every product listed under this category, also those whose main category is another. */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
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
