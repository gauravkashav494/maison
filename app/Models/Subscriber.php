<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use App\Models\Concerns\UsesClientConnection;
use App\Models\Concerns\BelongsToTemplate;
use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    use UsesClientConnection;

    use BelongsToClient;

    use BelongsToTemplate;

    protected $guarded = [];
}
