<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A shopper on one client's storefront.
 *
 * Customers live in the platform database and are scoped by client: the same person
 * shopping with two clients has two accounts, and neither client can see the other's.
 * They have no admin access of any kind.
 */
class Customer extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['client_id', 'name', 'email', 'phone', 'password', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
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

    public function firstName(): string
    {
        return explode(' ', trim((string) $this->name))[0] ?? (string) $this->name;
    }

    /**
     * Reset tokens are stored per client, not per address: two clients may each have a
     * customer with the same email, and a token issued by one must not be usable on the
     * other. The notification itself still goes to the plain address.
     */
    public function getEmailForPasswordReset(): string
    {
        return $this->client_id.':'.$this->email;
    }

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }
}
