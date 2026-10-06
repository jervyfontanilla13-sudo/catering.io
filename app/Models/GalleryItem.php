<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GalleryItem extends Model
{
    protected $fillable = ['title', 'description', 'image_path', 'event_type', 'is_featured'];

    protected function casts(): array
    {
        return ['is_featured' => 'boolean'];
    }
}
