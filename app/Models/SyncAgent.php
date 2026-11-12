<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A client's installed agent. Its mapping lives here, so a table change needs no reinstall. */
class SyncAgent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'mapping' => 'array',
        'schema_snapshot' => 'array',
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
