<?php

namespace App\Models;

use App\Models\Concerns\UsesPlatformConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A plan carries the default limits its clients inherit. Billing is not implemented:
 * `price_label` is free text (₹X) so the admin can show a figure without a payment flow.
 */
class Plan extends Model
{
    use Concerns\LogsPlatformActivity;
    use SoftDeletes;
    use UsesPlatformConnection;

    protected $guarded = [];

    protected $casts = ['is_default' => 'boolean'];

    /**
     * Exactly one plan is the default for new clients. Making a plan the default takes it away
     * from every other plan (deleted ones too, so a restore cannot bring back a second default),
     * and deleting the default hands it to the first remaining plan by order.
     */
    protected static function booted(): void
    {
        static::saved(function (Plan $plan) {
            if ($plan->is_default && ($plan->wasRecentlyCreated || $plan->wasChanged('is_default'))) {
                static::withTrashed()->whereKeyNot($plan->getKey())->where('is_default', true)->update(['is_default' => false]);
            }
        });

        static::deleted(function (Plan $plan) {
            if (! $plan->is_default) {
                return;
            }
            if (! $plan->isForceDeleting()) { // a permanently deleted row is gone; saving would re-insert it
                $plan->is_default = false;
                $plan->saveQuietly();
            }
            static::whereKeyNot($plan->getKey())->orderBy('position')->first()?->update(['is_default' => true]);
        });
    }

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
