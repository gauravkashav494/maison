<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $guarded = [];

    protected $casts = ['is_default' => 'boolean'];

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
