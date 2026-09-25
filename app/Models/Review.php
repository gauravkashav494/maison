<?php

namespace App\Models;

use App\Models\Concerns\UsesStoreConnection;
use App\Models\Concerns\BelongsToTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use UsesStoreConnection;

    use BelongsToTemplate;

    protected $guarded = [];

    protected $casts = ['is_approved' => 'boolean'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('is_approved', true);
    }

    /** Keep the denormalised rating/review_count on the product in sync. */
    public static function syncProduct(int $productId): void
    {
        $stats = static::approved()->where('product_id', $productId)->reorder()
            ->selectRaw('count(*) as review_total, avg(rating) as rating_avg')->first();
        Product::whereKey($productId)->update([
            'review_count' => (int) ($stats->review_total ?? 0),
            'rating' => round((float) ($stats->rating_avg ?? 0), 1),
        ]);
    }

    protected static function booted(): void
    {
        static::saved(fn (self $r) => static::syncProduct($r->product_id));
        static::deleted(fn (self $r) => static::syncProduct($r->product_id));
    }
}
