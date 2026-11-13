<?php

namespace App\Models;

use App\Models\Concerns\UsesPlatformConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use UsesPlatformConnection;

    protected $guarded = [];

    protected $casts = ['is_default' => 'boolean'];

    /** The storefront account that placed this, or null for a guest. */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /** Legacy link to the pre-split users table; kept so historic rows stay readable. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lines(): array
    {
        return array_values(array_filter([
            $this->name,
            $this->line1,
            $this->line2,
            trim("{$this->city}, {$this->state} {$this->postal_code}"),
            $this->country,
            $this->phone,
        ]));
    }
}
