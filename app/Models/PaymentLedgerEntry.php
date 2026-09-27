<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentLedgerEntry extends Model
{
    public const TYPE_INITIAL_PAYMENT = 'initial_payment';
    public const TYPE_BALANCE_PAYMENT = 'balance_payment';
    public const TYPE_REFUND = 'refund';

    protected $fillable = [
        'spa_booking_id',
        'entry_type',
        'amount',
        'payment_method',
        'reference',
        'occurred_at',
        'is_estimated',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
            'is_estimated' => 'boolean',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(SpaBooking::class, 'spa_booking_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
