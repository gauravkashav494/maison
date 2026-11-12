<?php

namespace App\Models;

use App\Models\Concerns\UsesClientConnection;
use App\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use SoftDeletes;
    use UsesClientConnection;

    protected $guarded = [];

    protected $casts = ['options' => 'array'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    /**
     * What the customer chose, as one line ("Colour: Camel / Size: M"). Falls back to the
     * older size and colour columns for orders placed before variants existed.
     */
    public function optionsLabel(): ?string
    {
        $options = (array) $this->options;
        if (! $options) {
            $options = array_filter(['Size' => $this->size, 'Colour' => $this->color], fn ($value) => filled($value));
        }
        if (! $options) {
            return null;
        }

        return implode(' / ', array_map(
            fn ($name, $value) => $name.': '.$value,
            array_keys($options),
            array_values($options),
        ));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function getImageUrlAttribute(): ?string
    {
        return Media::url($this->image);
    }
}
