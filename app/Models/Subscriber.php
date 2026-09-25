<?php

namespace App\Models;

use App\Models\Concerns\UsesStoreConnection;
use App\Models\Concerns\BelongsToTemplate;
use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    use UsesStoreConnection;

    use BelongsToTemplate;

    protected $guarded = [];
}
