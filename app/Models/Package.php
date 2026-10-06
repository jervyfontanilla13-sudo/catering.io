<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'min_guests',
        'max_guests',
        'menu',
        'freebies',
        'addons',
        'event_type',
        'is_featured',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_featured' => 'boolean',
        ];
    }

    public function estimatedTotalFor(int $guestCount): float
    {
        return round((float) $this->price * $guestCount, 2);
    }
}
