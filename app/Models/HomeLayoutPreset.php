<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeLayoutPreset extends Model
{
    protected $fillable = [
        'name',
        'sections',
    ];

    protected $casts = [
        'sections' => 'array',
    ];
}
