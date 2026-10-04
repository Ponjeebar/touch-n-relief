<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingRefund extends Model
{
    public const COMPONENT_INITIAL = PaymentLedgerEntry::TYPE_INITIAL_PAYMENT;

    public const COMPONENT_BALANCE = PaymentLedgerEntry::TYPE_BALANCE_PAYMENT;

    public const CHANNEL_PAYMONGO = 'paymongo';

    public const CHANNEL_MANUAL = 'manual';

    protected $fillable = [
        'spa_booking_id',
        'payment_component',
        'processing_channel',
        'payment_method',
        'gateway_payment_id',
        'amount',
        'status',
        'reference',
        'note',
        'requested_by',
        'processed_by',
        'requested_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(SpaBooking::class, 'spa_booking_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
