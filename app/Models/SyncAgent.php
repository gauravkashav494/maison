<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A client's installed agent: where it finds the shop's billing software (set in the panel,
 * SyncAccess::saveConnection(), which logs the change), the version it runs and what its last
 * cycle reported.
 */
class SyncAgent extends Model
{
    /** The address LOCAL-API.md asks the billing software's API to use unless it can't. */
    public const DEFAULT_LOCAL_API_URL = 'http://localhost:8765/ecom/v1';

    protected $guarded = [];

    protected $hidden = ['local_api_key'];

    protected $casts = [
        'local_api_key' => 'encrypted',
        'last_report' => 'array',
        'last_seen_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public static function forClient(Client $client): self
    {
        return static::firstOrCreate(['client_id' => $client->id]);
    }
}
