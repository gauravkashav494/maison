<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTemplate;
use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    use BelongsToTemplate;

    protected $guarded = [];
}
