<?php

namespace App\Models;

use App\Services\SpaServiceCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SpaBooking extends Model
{
    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_IN_SESSION = 'in_session';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const DISPLAY_PENDING = 'Pending';

    public const DISPLAY_RESCHEDULED = 'Rescheduled';

    public const SOURCE_ONLINE = 'online';

    public const SOURCE_WALK_IN = 'walk_in';

    protected static function booted(): void
    {
        static::creating(function (SpaBooking $booking): void {
            if ($booking->duration_minutes === null || (int) $booking->duration_minutes < 1) {
                $booking->duration_minutes = app(SpaServiceCatalog::class)
                    ->durationMinutesFor((string) $booking->service_name);
            }
        });
    }

    protected $table = 'spa_bookings';

    protected $fillable = [
        'user_id',
        'client_name',
        'booking_source',
        'service_name',
        'therapist_name',
        'booking_date',
        'time_slot',
        'duration_minutes',
        'amount',
        'payment_method',
        'payment_type',
        'payment_amount',
        'payment_proof_path',
        'payment_transaction_id',
        'paymongo_checkout_session_id',
        'payment_status',
        'refund_status',
        'refund_amount',
        'refunded_at',
        'refund_reference',
        'refund_note',
        'notes',
        'cancelled_at',
        'cancellation_reason',
        'rescheduled_at',
        'rescheduled_from_date',
        'rescheduled_from_time_slot',
        'completed_at',
        'session_started_at',
        'session_status',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'duration_minutes' => 'integer',
            'amount' => 'decimal:2',
            'payment_amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'refunded_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'rescheduled_at' => 'datetime',
            'rescheduled_from_date' => 'date',
            'completed_at' => 'datetime',
            'session_started_at' => 'datetime',
        ];
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function isCompleted(): bool
    {
        return app(\App\Services\SpaSessionService::class)->isCompleted($this);
    }

    public function isOngoing(): bool
    {
        return app(\App\Services\SpaSessionService::class)->isOngoing($this);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('cancelled_at');
    }

    /**
     * Bookings that still occupy a therapist or user time slot.
     */
    public function scopeBlocksAvailability($query)
    {
        return $query
            ->whereNull('cancelled_at')
            ->whereNull('completed_at')
            ->where(function ($query): void {
                $query->whereNull('session_status')
                    ->orWhereIn('session_status', [
                        self::STATUS_CONFIRMED,
                        self::STATUS_IN_SESSION,
                    ]);
            });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'spa_booking_id');
    }
}
