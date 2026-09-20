<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'transaction_id',
        'client_name',
        'user_id',
        'therapist_id',
        'service_name',
        'date',
        'time',
        'duration',
        'amount',
        'notes',
        'spa_booking_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'duration' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(Therapist::class);
    }

    public function spaBooking(): BelongsTo
    {
        return $this->belongsTo(SpaBooking::class, 'spa_booking_id');
    }
}
