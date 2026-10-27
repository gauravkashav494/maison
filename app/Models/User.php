<?php

namespace App\Models;

use App\Contracts\AdminIdentity;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A SuperAdmin: the platform operator.
 *
 * This table holds nothing else. A client's staff are `client_users` (they sign in at
 * /client) and shoppers are `customers` (they sign in on the storefront), so a client
 * account can never gain platform access by having a row flipped here, and platform
 * accounts are not part of any client's data.
 */
class User extends Authenticatable implements AdminIdentity, FilamentUser
{
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN => 'Super Admin',
    ];

    protected $fillable = ['name', 'email', 'phone', 'password', 'is_admin', 'role', 'is_active'];

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

    /** The SuperAdmin panel only, and only while the account is active. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->isSuperAdmin() && $this->isActiveAccount();
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /** A SuperAdmin belongs to no client; it may work inside any of them. */
    public function clientId(): ?int
    {
        return null;
    }

    public function managedTemplateId(): ?string
    {
        return null;
    }

    public function isActiveAccount(): bool
    {
        return (bool) $this->is_active;
    }

    public function getRoleLabelAttribute(): ?string
    {
        return self::ROLES[$this->role] ?? null;
    }

    public function firstName(): string
    {
        return explode(' ', trim((string) $this->name))[0] ?? (string) $this->name;
    }
}
