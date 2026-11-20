<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use App\Models\Concerns\BelongsToTemplate;
use App\Models\Concerns\UsesClientConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Faq extends Model
{
    use BelongsToClient;
    use BelongsToTemplate;
    use SoftDeletes;
    use UsesClientConnection;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public const CATEGORIES = ['Orders', 'Shipping', 'Returns', 'Exchanges', 'Payments', 'Products', 'Account', 'General', 'Services', 'Booking'];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->ordered();
    }

    /**
     * The storefront order: as dragged in the FAQs list, then in the order they were written for
     * those sharing a place (every new question starts at 0).
     */
    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }
}
