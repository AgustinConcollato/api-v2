<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeLayout extends Model
{
    protected $fillable = [
        'draft',
        'published',
        'published_at',
        'published_preset_id',
    ];

    protected $casts = [
        'draft' => 'array',
        'published' => 'array',
        'published_at' => 'datetime',
        'published_preset_id' => 'integer',
    ];
}
