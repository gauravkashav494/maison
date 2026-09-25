<?php

namespace App\Models;

use App\Models\Concerns\UsesStoreConnection;
use App\Models\Concerns\BelongsToTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use UsesStoreConnection;

    use BelongsToTemplate;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public const CATEGORIES = ['Orders', 'Shipping', 'Returns', 'Exchanges', 'Payments', 'Products', 'Account', 'General', 'Services', 'Booking'];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort_order');
    }
}
