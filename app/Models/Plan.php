<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A plan carries the default limits its clients inherit. Billing is not implemented:
 * `price_label` is free text (₹X) so the admin can show a figure without a payment flow.
 */
class Plan extends Model
{
    protected $guarded = [];

    protected $casts = ['is_default' => 'boolean'];

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public static function default(): ?self
    {
        return static::where('is_default', true)->orderBy('position')->first()
            ?? static::orderBy('position')->first();
    }

    public function getLabelAttribute(): string
    {
        return $this->name.' — '.$this->price_label;
    }
}
