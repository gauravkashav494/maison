<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTemplate;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    use BelongsToTemplate;

    protected $guarded = [];

    protected $casts = ['is_read' => 'boolean'];
}
