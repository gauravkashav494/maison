<?php

namespace App\Models;

use App\Models\Concerns\TracksSyncVersion;
use App\Models\Concerns\UsesPlatformConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * A shopper on one client's storefront.
 *
 * Customers live in the platform database and are scoped by client: the same person
 * shopping with two clients has two accounts, and neither client can see the other's.
 * They have no admin access of any kind.
 *
 * A sales person (is_salesperson, set only in the admin) is a customer account whose orders
 * need no delivery address: see SalesOrder.
 */
class Customer extends Authenticatable
{
    use Concerns\StoresPasswordHint, HasFactory, Notifiable;
    use SoftDeletes;
    use TracksSyncVersion;
    use UsesPlatformConnection;

    /** The profile fields the local system is told about. */
    public const SYNC_TRACKED = ['name', 'email', 'phone', 'is_active'];

    protected $fillable = ['client_id', 'name', 'email', 'phone', 'password', 'is_active', 'is_salesperson'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_salesperson' => 'boolean',
            'wishlist' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest();
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class)->orderByDesc('is_default')->orderBy('id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class)->latest();
    }

    /**
     * A real account: it has a password, or its phone was proven with a WhatsApp code.
     * Anything else is a guest record made by a checkout.
     */
    public function isAccount(): bool
    {
        return $this->password !== null || $this->phone_verified_at !== null;
    }

    /** A sales person: checks out without a delivery address, for themselves or for a customer (SalesOrder). */
    public function isSalesperson(): bool
    {
        return (bool) $this->is_salesperson;
    }

    /** Orders this sales person placed, for themselves or for customers. */
    public function salesOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'salesperson_id')->latest();
    }

    /** Only real accounts (see isAccount()). */
    public function scopeAccounts($query)
    {
        return $query->where(fn ($q) => $q->whereNotNull($q->qualifyColumn('password'))->orWhereNotNull($q->qualifyColumn('phone_verified_at')));
    }

    /** Only guest records made by a checkout (see isAccount()). */
    public function scopeGuests($query)
    {
        return $query->whereNull($query->qualifyColumn('password'))->whereNull($query->qualifyColumn('phone_verified_at'));
    }

    /** Customers who joined within a range of days; either end may be left open. Used by the admin filter. */
    public function scopeJoinedBetween($query, $from = null, $until = null)
    {
        return $query
            ->when(filled($from), fn ($q) => $q->whereDate($q->qualifyColumn('created_at'), '>=', Carbon::parse($from)))
            ->when(filled($until), fn ($q) => $q->whereDate($q->qualifyColumn('created_at'), '<=', Carbon::parse($until)));
    }

    public function firstName(): string
    {
        return explode(' ', trim((string) $this->name))[0] ?? (string) $this->name;
    }

    /** No email (a customer added by phone only): mail notifications are skipped. */
    public function routeNotificationForMail(): ?string
    {
        return $this->email ?: null;
    }
}
