<?php

namespace App\Models;

use App\Exceptions\CategoryInUseException;
use App\Models\Concerns\BelongsToClient;
use App\Models\Concerns\BelongsToTemplate;
use App\Models\Concerns\GeneratesSlug;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\UsesClientConnection;
use App\Support\Media;
use App\Templates\TemplateManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Category extends Model
{
    use BelongsToClient;
    use BelongsToTemplate;
    use GeneratesSlug;
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
                    'parent_id' => "A category can't be placed under itself or one of its own child categories.",
                ]);
            }
        });

        // A sub-category must never point at a parent in the trash: it would show as "Top level"
        // in the panel yet be missing from every storefront menu. The panel refuses these with a
        // message first (CategoryDeleteActions); this is the backstop for any other code.
        static::deleting(function (Category $category) {
            if (! $category->isForceDeleting() && ($reason = $category->whyNotDeletable())) {
                throw new CategoryInUseException($reason);
            }
        });

        static::restoring(function (Category $category) {
            if ($reason = $category->whyNotRestorable()) {
                throw new CategoryInUseException($reason);
            }
        });
    }

    /** Why this category can't go to the trash (it still has sub-categories), or null when it can. */
    public function whyNotDeletable(): ?string
    {
        $names = $this->children()->pluck('name');
        if ($names->isEmpty()) {
            return null;
        }

        return sprintf('"%s" still has %s: %s. Move them to another category or delete them first.',
            $this->name,
            $names->count() === 1 ? '1 sub-category' : $names->count().' sub-categories',
            $names->take(3)->join(', ').($names->count() > 3 ? ' and '.($names->count() - 3).' more' : ''));
    }

    /** Why this category can't come back from the trash (its parent is still there), or null when it can. */
    public function whyNotRestorable(): ?string
    {
        if (blank($this->parent_id)) {
            return null;
        }

        $parent = self::withoutGlobalScopes()->withTrashed()->find($this->parent_id);

        return $parent?->trashed()
            ? sprintf('Its parent category "%s" is in the trash. Restore "%s" first.', $parent->name, $parent->name)
            : null;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
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

    /**
     * This category and the ones above it, from the top: Men, Shirts, Formal. Stops if the data
     * holds a loop.
     *
     * @return list<self>
     */
    public function lineage(): array
    {
        $path = [];
        $seen = [];
        for ($category = $this; $category && ! in_array($category->getKey(), $seen, true); $category = $category->parent) {
            $seen[] = $category->getKey();
            array_unshift($path, $category);
        }

        return $path;
    }

    /** Every product listed under this category, also those whose main category is another. */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * Adds categories under this one by name ("Shirts", "Pants"). Names already under it are
     * skipped. Saved as normal models, so each is in the activity log.
     *
     * @param  array<string>  $names
     * @return list<self>
     */
    public function addChildren(array $names): array
    {
        $existing = $this->children()->pluck('name')->map(fn ($n) => Str::lower(trim($n)))->all();
        // New ones go after those already there.
        $position = $this->children()->exists() ? (int) $this->children()->max('sort_order') + 1 : 0;
        $added = [];

        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '' || in_array(Str::lower($name), $existing, true)) {
                continue;
            }
            $existing[] = Str::lower($name);

            $added[] = self::create(['sort_order' => $position++] + $this->newChildAttributes($name));
        }

        return $added;
    }

    /**
     * What a new category under this one starts with: this one's client, store and visibility.
     * Its slug is made on save, led by this one's ("men-shirts").
     *
     * @return array<string, mixed>
     */
    public function newChildAttributes(string $name): array
    {
        return [
            'client_id' => $this->client_id,
            'template' => $this->template,
            'parent_id' => $this->getKey(),
            'name' => trim($name),
            'is_active' => $this->is_active ?? true,
            'show_in_menu' => $this->show_in_menu ?? true,
        ];
    }

    /** A sub-category's slug is led by its parent's: "men-shirts", so Men and Women can both have "Shirts". */
    protected function slugSource(): string
    {
        $parent = $this->parent_id ? self::withoutGlobalScopes()->find($this->parent_id) : null;

        return trim(($parent?->slug ?? '').' '.$this->name);
    }

    /**
     * The name with the categories above it, "Men › Shirts", so two "Shirts" (one under Men, one
     * under Women) can be told apart in lists and pickers.
     */
    public function pathName(string $separator = ' › '): string
    {
        return implode($separator, [...$this->namesAbove(), $this->name]);
    }

    /**
     * The names of the categories above this one, top first: ["Men"] for Men › Shirts.
     *
     * @return list<string>
     */
    public function namesAbove(): array
    {
        $names = [];
        $seen = [$this->getKey()];
        $category = $this;
        while ($category->parent_id && ! in_array($category->parent_id, $seen, true) && ($category = $category->parent)) {
            $seen[] = $category->getKey();
            array_unshift($names, $category->name);
        }

        return $names;
    }

    /** The parent chain eager-loaded, for pathName() on many categories at once. */
    public const PATH = 'parent.parent.parent';

    /** The top-level category (department) this one sits in: itself when it is top-level. */
    public function root(): self
    {
        $root = $this;
        $seen = [];
        while ($root->parent_id && ! in_array($root->parent_id, $seen, true) && ($parent = $root->parent)) {
            $seen[] = $root->getKey();
            $root = $parent;
        }

        return $root;
    }

    /**
     * Whether "Feature in menu & homepage" applies to a category of this template. Bulk Order lists
     * every visible category, so it has no such switch. Null (every template) counts as yes.
     */
    public static function featurableIn(?string $template): bool
    {
        $templates = app(TemplateManager::class);

        return $template === null || ! $templates->has($template) || $templates->get($template)->featuresCategories();
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeTopLevel(Builder $q): Builder
    {
        return $q->whereNull('parent_id')->ordered();
    }

    /**
     * The storefront order: as dragged in the categories list, then A to Z for those sharing a
     * place (every new category starts at 0).
     */
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
        return route('shop.category', $this->slug);
    }
}
