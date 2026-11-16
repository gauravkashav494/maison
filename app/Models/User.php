<?php

namespace App\Models;

use App\Admin\PlatformModules;
use App\Contracts\AdminIdentity;
use App\Models\Concerns\UsesPlatformConnection;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A platform account on /admin: a Super Admin (`is_super_admin`, the whole platform) or
 * platform staff, who reach only the /admin modules ticked in `admin_modules`
 * (App\Admin\PlatformModules).
 *
 * This table holds nothing else. A client's staff are `client_users` (they sign in at
 * /client) and shoppers are `customers` (they sign in on the storefront), so a client
 * account can never gain platform access by having a row flipped here, and platform
 * accounts are not part of any client's data.
 */
class User extends Authenticatable implements AdminIdentity, FilamentUser
{
    use Concerns\LogsPlatformActivity, Concerns\StoresPasswordHint, HasFactory, Notifiable;
    use SoftDeletes;
    use UsesPlatformConnection;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_PLATFORM_STAFF = 'platform_staff';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN => 'Super Admin',
        self::ROLE_PLATFORM_STAFF => 'Platform Staff',
    ];

    protected $fillable = ['name', 'email', 'phone', 'password', 'is_admin', 'role', 'is_super_admin', 'admin_modules', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_super_admin' => 'boolean',
            'admin_modules' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * `is_super_admin` is the source of truth; `role` and `is_admin` are kept in step with it.
     * Code that only sets `role` (older seeders and tests) still gets the matching flag.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if (! $user->isDirty('is_super_admin') && $user->isDirty('role')) {
                $user->is_super_admin = $user->role === self::ROLE_SUPER_ADMIN;
            }
            $user->is_super_admin = (bool) $user->is_super_admin;
            $user->role = $user->is_super_admin ? self::ROLE_SUPER_ADMIN : self::ROLE_PLATFORM_STAFF;
            $user->is_admin = $user->is_super_admin;
            // A Super Admin has every module; the list only means something for platform staff.
            $user->admin_modules = $user->is_super_admin ? null : PlatformModules::clean($user->admin_modules);
        });
    }

    /** The platform panel only, and only while the account is active. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->isActiveAccount();
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    /** Every row in this table is a platform account; only some of them are Super Admins. */
    public function isPlatformAccount(): bool
    {
        return true;
    }

    public function hasPlatformModule(string $module): bool
    {
        return PlatformModules::allows($this, $module);
    }

    /** A platform account belongs to no client. */
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
