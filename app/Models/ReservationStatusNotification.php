<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationStatusNotification extends Model
{
    protected $fillable = [
        'reservation_id',
        'recipient_email',
        'notification_type',
        'status',
        'sent_at',
        'error_message',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
