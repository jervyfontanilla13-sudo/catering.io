<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'is_featured',
        'is_enabled',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_enabled' => 'boolean',
    ];
}
