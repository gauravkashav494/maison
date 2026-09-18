<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTemplate;
use App\Templates\TemplateManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;

class Menu extends Model
{
    use BelongsToTemplate;

    protected $guarded = [];

    public const LOCATIONS = [
        'header' => 'Header (primary navigation)',
        'footer_shop' => 'Footer — Shop',
        'footer_collections' => 'Footer — Collections',
        'footer_about' => 'Footer — About',
        'footer_service' => 'Footer — Customer Service',
        'legal' => 'Footer — Legal links',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->whereNull('parent_id')->orderBy('sort_order');
    }

    public function allItems(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order');
    }

    /** Cached tree for a location: active top-level items with nested `children`. */
    public static function tree(string $location): SupportCollection
    {
        return Cache::rememberForever("menu.{$location}", function () use ($location) {
            $menu = static::where('location', $location)->first();
            if (! $menu) {
                return collect();
            }
            $byParent = $menu->allItems()->where('is_active', true)->get()->groupBy('parent_id');
            $build = function ($parentId) use (&$build, $byParent) {
                return ($byParent[$parentId] ?? collect())
                    ->map(function (MenuItem $item) use (&$build) {
                        $item->setRelation('children', $build($item->id));

                        return $item;
                    })
                    ->values();
            };

            return $build(null);
        });
    }

    public static function flush(): void
    {
        foreach (array_keys(app(TemplateManager::class)->allMenuLocations()) as $loc) {
            Cache::forget("menu.{$loc}");
        }
    }

    protected static function booted(): void
    {
        // The owning template is derived from the location so admin filters stay accurate.
        static::saving(function (Menu $menu) {
            $menu->template = app(TemplateManager::class)->templateForLocation((string) $menu->location)?->id() ?? $menu->template;
        });
        static::saved(function (Menu $menu) {
            static::flush();
            Cache::forget("menu.{$menu->location}");
        });
        static::deleted(fn () => static::flush());
    }
}
