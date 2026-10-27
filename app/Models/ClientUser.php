<?php

namespace App\Models;

use App\Contracts\AdminIdentity;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A staff account inside one client: Client Admin (full access to the client's own data,
 * including its staff accounts) or Admin Staff (day-to-day work, no account management).
 *
 * These accounts sign in at /client with the `client` guard. They are never SuperAdmins and
 * the client they belong to comes from this row only — never from the request.
 */
class ClientUser extends Authenticatable implements AdminIdentity, FilamentUser
{
    use HasFactory, Notifiable;

    public const ROLE_CLIENT_ADMIN = 'client_admin';

    public const ROLE_ADMIN_STAFF = 'admin_staff';

    public const ROLES = [
        self::ROLE_CLIENT_ADMIN => 'Client Admin',
        self::ROLE_ADMIN_STAFF => 'Admin Staff',
    ];

    protected $fillable = ['client_id', 'name', 'email', 'phone', 'password', 'role', 'is_active'];

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

    /** Only the client panel, and only while both the account and its client are active. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'client' && $this->isActiveAccount() && $this->client?->templateObject() !== null;
    }

    public function isSuperAdmin(): bool
    {
        return false;
    }

    public function clientId(): ?int
    {
        return $this->client_id;
    }

    public function managedTemplateId(): ?string
    {
        return $this->client?->templateId();
    }

    public function isActiveAccount(): bool
    {
        return (bool) $this->is_active && (bool) $this->client?->is_active;
    }

    public function isClientAdmin(): bool
    {
        return $this->role === self::ROLE_CLIENT_ADMIN;
    }

    public function isAdminStaff(): bool
    {
        return $this->role === self::ROLE_ADMIN_STAFF;
    }

    /** May manage this client's own staff accounts. */
    public function manageStaff(): bool
    {
        return $this->isClientAdmin();
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
