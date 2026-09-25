<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffNotification extends Model
{
    protected $fillable = ['staff_user_id', 'spa_booking_id', 'event_key', 'type', 'title', 'message', 'url', 'occurred_at', 'read_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'read_at' => 'datetime'];
    }
}
