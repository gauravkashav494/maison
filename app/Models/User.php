<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    /** Staff roles. Customers (storefront accounts) have no role. */
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_STORE_OWNER = 'store_owner';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN => 'Super Admin',
        self::ROLE_STORE_OWNER => 'Store Owner',
    ];

    protected $fillable = ['name', 'email', 'phone', 'password', 'is_admin', 'role', 'storefront_id', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Only active staff may open the Filament admin; customers use /account.
     * A store owner also needs an active, registered store.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_admin || ! $this->is_active || ! $this->role) {
            return false;
        }
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->isStoreOwner() && $this->storefront?->is_active && $this->storefront->templateObject() !== null;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isStoreOwner(): bool
    {
        return $this->role === self::ROLE_STORE_OWNER;
    }

    public function storefront(): BelongsTo
    {
        return $this->belongsTo(Storefront::class);
    }

    /** Template id this account is confined to (null = unrestricted super admin). */
    public function managedTemplateId(): ?string
    {
        return $this->isSuperAdmin() ? null : $this->storefront?->template;
    }

    public function getRoleLabelAttribute(): ?string
    {
        return self::ROLES[$this->role] ?? null;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest();
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class)->orderByDesc('is_default')->orderBy('id');
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0] ?? $this->name;
    }
}
