<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = ['user_id', 'actor_name', 'actor_email', 'actor_role', 'action', 'method', 'ip_address', 'activity_date', 'activity_time', 'description'];
}
