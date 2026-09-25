<?php

namespace App\Models;

use App\Models\Concerns\UsesStoreConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A booking, emergency call-back or quote request submitted from the Plumbing Services template. */
class ServiceRequest extends Model
{
    use UsesStoreConnection;

    protected $guarded = [];

    protected $casts = ['preferred_date' => 'date'];

    public const TYPES = ['booking' => 'Booking', 'emergency' => 'Emergency', 'quote' => 'Quote request'];

    public const STATUSES = ['new' => 'New', 'contacted' => 'Contacted', 'scheduled' => 'Scheduled', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

    public const SLOTS = ['asap' => 'As soon as possible', 'morning' => 'Morning (8 am – 12 pm)', 'afternoon' => 'Afternoon (12 – 4 pm)', 'evening' => 'Evening (4 – 8 pm)'];

    protected static function booted(): void
    {
        static::creating(function (self $r) {
            $r->reference ??= self::generateReference();
        });
    }

    public static function generateReference(): string
    {
        do {
            $ref = 'PS-'.now()->format('ymd').'-'.Str::upper(Str::random(4));
        } while (self::where('reference', $ref)->exists());

        return $ref;
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getSlotLabelAttribute(): ?string
    {
        return self::SLOTS[$this->time_slot] ?? $this->time_slot;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    public function getUrlAttribute(): string
    {
        return route('booking.confirmation', $this->reference);
    }
}
