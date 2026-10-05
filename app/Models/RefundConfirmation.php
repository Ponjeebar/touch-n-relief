<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundConfirmation extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['evidence'];

    public function disputeVersion(): string
    {
        // Audit IDs distinguish repeated complaints even within the same second.
        return (string) (ActivityLog::query()
            ->where('subject_type', $this->getMorphClass())
            ->where('subject_id', $this->id)
            ->whereIn('action', ['refund.dispute.report', 'refund.dispute.resolve'])
            ->max('id') ?? 0);
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'confirmed_at' => 'datetime', 'evidence' => 'encrypted'];
    }
}
