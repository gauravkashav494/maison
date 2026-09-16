<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class MenuItem extends Model
{
    protected $guarded = [];

    protected $casts = ['is_accent' => 'boolean', 'opens_in_new_tab' => 'boolean', 'is_active' => 'boolean'];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function getImageUrlAttribute(): ?string
    {
        return Media::url($this->image);
    }

    public function getHrefAttribute(): string
    {
        return $this->url ?: '#';
    }

    /**
     * Rewrite sort_order for a whole menu so a flat ORDER BY sort_order yields tree order:
     * each top-level item immediately followed by its children. Keeps the admin list readable
     * and lets drag-and-drop work on the flat list.
     *
     * @param  array<int>|null  $flatOrder  optional new visual order of item ids (from drag-and-drop)
     */
    public static function renumber(int $menuId, ?array $flatOrder = null): void
    {
        $items = static::where('menu_id', $menuId)->orderBy('sort_order')->orderBy('id')->get();
        if ($items->isEmpty()) {
            return;
        }

        $rank = $flatOrder ? array_flip(array_map('intval', array_values($flatOrder))) : [];
        $position = fn (self $i) => $rank[$i->id] ?? PHP_INT_MAX;

        $bySiblingOrder = fn (self $a, self $b) => ($position($a) <=> $position($b)) ?: ($a->sort_order <=> $b->sort_order);
        $topLevel = $items->whereNull('parent_id')->sort($bySiblingOrder)->values();

        // When a drag order is supplied, a child adopts the nearest top-level item above it.
        $parentOf = [];
        if ($flatOrder) {
            $topIds = $topLevel->pluck('id')->all();
            $current = null;
            foreach ($flatOrder as $id) {
                $id = (int) $id;
                if (in_array($id, $topIds, true)) {
                    $current = $id;
                } elseif ($current !== null) {
                    $parentOf[$id] = $current;
                }
            }
        }

        $children = $items->whereNotNull('parent_id')->groupBy(fn (self $i) => $parentOf[$i->id] ?? $i->parent_id);

        $n = 0;
        $updates = [];
        foreach ($topLevel as $top) {
            $updates[$top->id] = ['sort_order' => $n++, 'parent_id' => null];
            $kids = ($children[$top->id] ?? collect())->sort($bySiblingOrder)->values();
            foreach ($kids as $kid) {
                $updates[$kid->id] = ['sort_order' => $n++, 'parent_id' => $top->id];
            }
        }
        // Orphans (parent missing) become top-level at the end.
        foreach ($items as $item) {
            if (! isset($updates[$item->id])) {
                $updates[$item->id] = ['sort_order' => $n++, 'parent_id' => null];
            }
        }

        DB::transaction(function () use ($updates) {
            foreach ($updates as $id => $values) {
                DB::table('menu_items')->where('id', $id)->update($values);
            }
        });

        Menu::flush();
    }

    protected static function booted(): void
    {
        // New items join the end of the menu instead of jumping to the top.
        static::creating(function (self $item) {
            if (! $item->sort_order) {
                $item->sort_order = (int) static::where('menu_id', $item->menu_id)->max('sort_order') + 1;
            }
        });
        static::saved(fn (self $item) => static::renumber($item->menu_id));
        static::deleted(fn (self $item) => static::renumber($item->menu_id));
    }
}
