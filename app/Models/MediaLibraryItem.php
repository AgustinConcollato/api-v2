<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaLibraryItem extends Model
{
    protected $table = 'media_library';

    protected $fillable = ['path', 'name'];
}
