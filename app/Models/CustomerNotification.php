<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerNotification extends Model
{
    public const TYPE_CANCELLED = 'cancelled';

    public const TYPE_RESCHEDULED = 'rescheduled';

    public const TYPE_REMINDER = 'reminder';

    public const TYPE_NO_SHOW = 'no_show';

    public const TYPE_NO_SHOW_REVERSED = 'no_show_reversed';

    protected $fillable = [
        'user_id',
        'spa_booking_id',
        'type',
        'title',
        'message',
        'details',
        'dedup_key',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, CustomerNotification>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<SpaBooking, CustomerNotification>
     */
    public function spaBooking(): BelongsTo
    {
        return $this->belongsTo(SpaBooking::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
