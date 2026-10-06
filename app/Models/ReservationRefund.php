<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationRefund extends Model
{
    protected $fillable = [
        'reservation_id',
        'payment_id',
        'refund_date',
        'amount',
        'refund_method',
        'reason',
        'status',
        'request_key',
        'recorded_by_user_id',
        'recorded_by_name',
    ];

    protected $casts = [
        'refund_date' => 'date',
        'amount' => 'float',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function payment()
    {
        return $this->belongsTo(ReservationPayment::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
