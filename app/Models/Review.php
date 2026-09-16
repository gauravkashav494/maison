<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
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
        $stats = static::approved()->where('product_id', $productId)
            ->selectRaw('count(*) as c, avg(rating) as r')->first();
        Product::whereKey($productId)->update([
            'review_count' => (int) ($stats->c ?? 0),
            'rating' => round((float) ($stats->r ?? 0), 1),
        ]);
    }

    protected static function booted(): void
    {
        static::saved(fn (self $r) => static::syncProduct($r->product_id));
        static::deleted(fn (self $r) => static::syncProduct($r->product_id));
    }
}
