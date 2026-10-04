<?php

namespace App\Models;

use App\Services\SiteSettingsService;
use App\Services\SpaServiceCatalog;
use App\Services\SpaSessionService;
use App\Support\PaymentMethodCatalog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SpaBooking extends Model
{
    public const PAYMENT_HOLD_MINUTES = 15;

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_IN_SESSION = 'in_session';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_NO_SHOW = 'no_show';

    public const DISPLAY_PAYMENT_PENDING = 'Payment Pending';

    public const DISPLAY_BALANCE_DUE = 'Confirmed – Balance Due';

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
        'balance_amount',
        'balance_payment_method',
        'balance_payment_reference',
        'balance_collected_by',
        'balance_paid_at',
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
        'no_show_reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'duration_minutes' => 'integer',
            'amount' => 'decimal:2',
            'payment_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
            'balance_paid_at' => 'datetime',
            'refund_amount' => 'decimal:2',
            'refunded_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'rescheduled_at' => 'datetime',
            'rescheduled_from_date' => 'date',
            'completed_at' => 'datetime',
            'session_started_at' => 'datetime',
            'no_show_reversed_at' => 'datetime',
        ];
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function isCompleted(): bool
    {
        return app(SpaSessionService::class)->isCompleted($this);
    }

    public function isOngoing(): bool
    {
        return app(SpaSessionService::class)->isOngoing($this);
    }

    public function initialPaidAmount(): float
    {
        return in_array($this->payment_status, [
            PaymentMethodCatalog::STATUS_PAID,
            PaymentMethodCatalog::STATUS_REFUNDED,
        ], true)
            ? max((float) ($this->payment_amount ?? 0), 0)
            : 0.0;
    }

    public function totalPaidAmount(): float
    {
        $balance = $this->balance_paid_at !== null ? max((float) ($this->balance_amount ?? 0), 0) : 0.0;

        return round($this->initialPaidAmount() + $balance, 2);
    }

    public function remainingBalance(): float
    {
        return round(max((float) ($this->amount ?? 0) - $this->totalPaidAmount(), 0), 2);
    }

    public function isFullyPaid(): bool
    {
        return (float) ($this->amount ?? 0) > 0 && $this->remainingBalance() < 0.01;
    }

    public function isAwaitingOnlinePayment(): bool
    {
        return $this->booking_source === self::SOURCE_ONLINE
            && $this->payment_method === PaymentMethodCatalog::METHOD_PAYMONGO
            && in_array($this->payment_status, [
                PaymentMethodCatalog::STATUS_PENDING,
                PaymentMethodCatalog::STATUS_FAILED,
            ], true);
    }

    public function paymentHoldExpiresAt(): ?CarbonInterface
    {
        if (! $this->isAwaitingOnlinePayment() || $this->created_at === null) {
            return null;
        }

        return $this->created_at->copy()->addMinutes(self::paymentHoldMinutes());
    }

    public function hasActivePaymentHold(): bool
    {
        $expiresAt = $this->paymentHoldExpiresAt();

        return $this->payment_status === PaymentMethodCatalog::STATUS_PENDING
            && $expiresAt !== null
            && now()->lt($expiresAt);
    }

    public function isPaymentHoldExpired(): bool
    {
        return $this->isAwaitingOnlinePayment() && ! $this->hasActivePaymentHold();
    }

    public function scopeActive($query)
    {
        return $query->whereNull('cancelled_at');
    }

    /**
     * Exclude online checkout records that have not produced a confirmed payment.
     */
    public function scopeVisibleToStaff($query)
    {
        return $query->where(function ($query): void {
            $query->where('booking_source', '!=', self::SOURCE_ONLINE)
                ->orWhereNull('booking_source')
                ->orWhere('payment_method', '!=', PaymentMethodCatalog::METHOD_PAYMONGO)
                ->orWhereNull('payment_method')
                ->orWhereNotIn('payment_status', [
                    PaymentMethodCatalog::STATUS_PENDING,
                    PaymentMethodCatalog::STATUS_FAILED,
                ]);
        });
    }

    /**
     * Bookings that still occupy a therapist or user time slot.
     */
    public function scopeBlocksAvailability($query)
    {
        $holdCutoff = now()->subMinutes(self::paymentHoldMinutes());

        return $query
            ->whereNull('cancelled_at')
            ->whereNull('completed_at')
            ->where(function ($query): void {
                $query->whereNull('session_status')
                    ->orWhereIn('session_status', [
                        self::STATUS_CONFIRMED,
                        self::STATUS_IN_SESSION,
                    ]);
            })
            ->where(function ($query) use ($holdCutoff): void {
                $query->where(function ($query): void {
                    $query->where('booking_source', '!=', self::SOURCE_ONLINE)
                        ->orWhereNull('booking_source')
                        ->orWhere('payment_method', '!=', PaymentMethodCatalog::METHOD_PAYMONGO)
                        ->orWhereNull('payment_method')
                        ->orWhereNotIn('payment_status', [
                            PaymentMethodCatalog::STATUS_PENDING,
                            PaymentMethodCatalog::STATUS_FAILED,
                        ]);
                })->orWhere(function ($query) use ($holdCutoff): void {
                    $query->where('booking_source', self::SOURCE_ONLINE)
                        ->where('payment_method', PaymentMethodCatalog::METHOD_PAYMONGO)
                        ->where('payment_status', PaymentMethodCatalog::STATUS_PENDING)
                        ->where('created_at', '>', $holdCutoff);
                });
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

    public function paymentLedgerEntries(): HasMany
    {
        return $this->hasMany(PaymentLedgerEntry::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(BookingRefund::class);
    }

    public static function paymentHoldMinutes(): int
    {
        return app(SiteSettingsService::class)->paymentHoldMinutes();
    }
}
