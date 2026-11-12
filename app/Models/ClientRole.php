<?php

namespace App\Models;

use App\Clients\ClientModules;
use App\Models\Concerns\LogsPlatformActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named set of permissions inside one client, built by its Client Admin and given to Admin
 * Staff accounts. Roles belong to the client: another client's roles are never offered.
 */
class ClientRole extends Model
{
    use \App\Models\Concerns\UsesPlatformConnection;

    use LogsPlatformActivity;

    protected $fillable = ['client_id', 'name', 'description', 'permissions'];

    protected $casts = ['permissions' => 'array'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(ClientUser::class);
    }

    /** The ready-made roles a client starts with; existing names are left as the client set them. */
    public static function createDefaultsFor(Client $client): void
    {
        foreach (ClientModules::defaultRoles() as $name => $role) {
            static::firstOrCreate(
                ['client_id' => $client->id, 'name' => $name],
                ['description' => $role['description'], 'permissions' => $role['permissions']],
            );
        }
    }
}
