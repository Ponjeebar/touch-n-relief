<?php

namespace App\Models;

use App\Support\PaymentMethodCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipPurchase extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SCHEDULED = 'scheduled';

    protected $fillable = [
        'user_id',
        'membership_plan_id',
        'plan_name',
        'validity_days',
        'amount',
        'payment_method',
        'payment_status',
        'status',
        'paymongo_checkout_session_id',
        'payment_transaction_id',
        'paid_at',
        'starts_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'validity_days' => 'integer',
            'paid_at' => 'datetime',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function isActive(): bool
    {
        return $this->payment_status === PaymentMethodCatalog::STATUS_PAID
            && $this->starts_at !== null
            && $this->expires_at !== null
            && now()->gte($this->starts_at)
            && now()->lt($this->expires_at);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('payment_status', PaymentMethodCatalog::STATUS_PAID)
            ->where('starts_at', '<=', now())
            ->where('expires_at', '>', now());
    }
}
