<?php

namespace App\Services;

use App\Models\SpaBooking;
use App\Models\Therapist;
use App\Models\Transaction;
use App\Support\PaymentMethodCatalog;
use App\Services\BookingRefundService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SpaSessionService
{
    public function resolveStatus(SpaBooking $booking, ?Carbon $now = null): string
    {
        if ($booking->cancelled_at !== null || $booking->session_status === SpaBooking::STATUS_CANCELLED) {
            return SpaBooking::STATUS_CANCELLED;
        }

        if ($booking->completed_at !== null || $booking->session_status === SpaBooking::STATUS_COMPLETED) {
            return SpaBooking::STATUS_COMPLETED;
        }

        if ($booking->session_started_at !== null || $booking->session_status === SpaBooking::STATUS_IN_SESSION) {
            if ($this->hasExpired($booking, $now)) {
                return SpaBooking::STATUS_COMPLETED;
            }

            return SpaBooking::STATUS_IN_SESSION;
        }

        if ($this->hasExpired($booking, $now)) {
            return SpaBooking::STATUS_COMPLETED;
        }

        return SpaBooking::STATUS_CONFIRMED;
    }

    public function refreshStatus(SpaBooking $booking, ?Carbon $now = null): string
    {
        $status = $this->resolveStatus($booking, $now);

        if ($status === SpaBooking::STATUS_COMPLETED && $booking->completed_at === null) {
            $completedAt = $this->sessionEndAt($booking) ?? ($now ?? now());
            $booking->forceFill([
                'session_status' => SpaBooking::STATUS_COMPLETED,
                'completed_at' => $completedAt,
            ])->save();
            $this->syncTransaction($booking->fresh());

            return SpaBooking::STATUS_COMPLETED;
        }

        if ($booking->session_status !== $status) {
            $booking->forceFill(['session_status' => $status])->save();
        }

        return $status;
    }

    public function userTransactionLabel(string $status): string
    {
        return match ($status) {
            SpaBooking::STATUS_CANCELLED => 'Cancelled booking',
            SpaBooking::STATUS_COMPLETED => 'Completed session',
            SpaBooking::STATUS_IN_SESSION => 'In session',
            default => 'Confirmed booking',
        };
    }

    public function adminAppointmentLabel(string $status): string
    {
        return match ($status) {
            SpaBooking::STATUS_CANCELLED => 'Cancelled',
            SpaBooking::STATUS_COMPLETED => 'Completed',
            SpaBooking::STATUS_IN_SESSION => 'In Session',
            default => 'Confirmed',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toUserTransactionRow(SpaBooking $booking): array
    {
        $status = $this->refreshStatus($booking);
        $booking->loadMissing('transaction');
        $date = $booking->booking_date->format('Y-m-d');
        $amountRaw = (float) ($booking->amount ?? 0);
        $paymentAmountRaw = (float) ($booking->payment_amount ?? 0);
        $displayAmountRaw = $paymentAmountRaw > 0 ? $paymentAmountRaw : $amountRaw;
        $durationMinutes = (int) ($booking->duration_minutes ?? 0);

        return [
            'booking_id' => $booking->id,
            'transaction_id' => (string) ($booking->transaction?->transaction_id
                ?: 'BKG-'.str_pad((string) $booking->id, 5, '0', STR_PAD_LEFT)),
            'service' => (string) $booking->service_name,
            'therapist' => (string) ($booking->therapist_name ?: '—'),
            'date' => $date,
            'time' => (string) $booking->time_slot,
            'duration' => $durationMinutes > 0 ? $durationMinutes.' min' : '—',
            'amount' => $displayAmountRaw > 0 ? '₱'.number_format($displayAmountRaw, 2) : '—',
            'amount_raw' => $displayAmountRaw,
            ...$this->paymentMeta($booking),
            'status' => $this->userTransactionLabel($status),
            'session_status' => $status,
            'sort_ts' => $this->sortTimestamp($date, (string) $booking->time_slot),
            'activity_ts' => $this->activityTimestamp($booking),
            'can_cancel' => false,
            'is_booking' => true,
            'cancellation_reason' => $booking->cancellation_reason,
        ];
    }

    public function activityTimestamp(SpaBooking $booking): int
    {
        if ($booking->completed_at !== null) {
            return $booking->completed_at->timestamp;
        }

        if ($booking->cancelled_at !== null) {
            return $booking->cancelled_at->timestamp;
        }

        if ($booking->rescheduled_at !== null) {
            return $booking->rescheduled_at->timestamp;
        }

        if ($booking->session_started_at !== null) {
            return $booking->session_started_at->timestamp;
        }

        if ($booking->updated_at !== null) {
            return $booking->updated_at->timestamp;
        }

        if ($booking->created_at !== null) {
            return $booking->created_at->timestamp;
        }

        return $this->sortTimestamp(
            $booking->booking_date->format('Y-m-d'),
            (string) $booking->time_slot,
        );
    }

    public function sortTimestamp(string $date, string $time): int
    {
        try {
            $timeValue = trim($time) !== '' ? $time : '12:00 AM';

            return Carbon::parse($date.' '.$timeValue)->timestamp;
        } catch (\Throwable) {
            return Carbon::parse($date)->startOfDay()->timestamp;
        }
    }

    /**
     * @return array{start: Carbon, end: Carbon, duration: int}|null
     */
    public function window(SpaBooking $booking): ?array
    {
        if ($booking->booking_date === null || trim((string) $booking->time_slot) === '') {
            return null;
        }

        try {
            $start = Carbon::parse($booking->booking_date->format('Y-m-d').' '.(string) $booking->time_slot);
        } catch (\Throwable) {
            return null;
        }

        $duration = max((int) ($booking->duration_minutes ?? 60), 1);

        return [
            'start' => $start,
            'end' => $start->copy()->addMinutes($duration),
            'duration' => $duration,
        ];
    }

    public function sessionEndAt(SpaBooking $booking): ?Carbon
    {
        if ($booking->session_started_at !== null) {
            $duration = max((int) ($booking->duration_minutes ?? 60), 1);

            return $booking->session_started_at->copy()->addMinutes($duration);
        }

        return $this->window($booking)['end'] ?? null;
    }

    public function hasExpired(SpaBooking $booking, ?Carbon $now = null): bool
    {
        if ($booking->isCancelled() || $booking->completed_at !== null) {
            return false;
        }

        $end = $this->sessionEndAt($booking);

        if ($end === null) {
            return false;
        }

        return ($now ?? now())->gte($end);
    }

    public function autoCompleteIfExpired(SpaBooking $booking, ?Carbon $now = null): bool
    {
        if (! $this->hasExpired($booking, $now)) {
            return false;
        }

        if ($booking->completed_at !== null) {
            return false;
        }

        $completedAt = $this->sessionEndAt($booking) ?? ($now ?? now());
        $booking->forceFill([
            'completed_at' => $completedAt,
            'session_status' => SpaBooking::STATUS_COMPLETED,
        ])->save();
        $this->syncTransaction($booking->fresh());

        return true;
    }

    /**
     * @return Collection<int, SpaBooking>
     */
    public function autoCompleteAllExpired(?Carbon $now = null): Collection
    {
        $now = $now ?? now();
        $completed = collect();

        SpaBooking::query()
            ->with('user')
            ->whereNull('cancelled_at')
            ->whereNull('completed_at')
            ->where(function (Builder $query): void {
                $query->whereNotNull('session_started_at')
                    ->orWhereNotNull('booking_date');
            })
            ->orderBy('id')
            ->each(function (SpaBooking $booking) use ($now, $completed): void {
                if ($this->autoCompleteIfExpired($booking, $now)) {
                    $completed->push($booking->fresh(['user']));
                }
            });

        return $completed->values();
    }

    /**
     * Persist completion for finished sessions so they no longer block booking availability.
     */
    public function releaseAvailabilityBlocks(?Carbon $now = null): void
    {
        $now = $now ?? now();

        $this->autoCompleteAllExpired($now);

        SpaBooking::query()
            ->whereNull('cancelled_at')
            ->whereNull('completed_at')
            ->where(function (Builder $query): void {
                $query->where('session_status', SpaBooking::STATUS_COMPLETED)
                    ->orWhereNotNull('session_started_at')
                    ->orWhereNotNull('booking_date');
            })
            ->orderBy('id')
            ->each(function (SpaBooking $booking) use ($now): void {
                if ($this->resolveStatus($booking, $now) !== SpaBooking::STATUS_COMPLETED) {
                    return;
                }

                $this->refreshStatus($booking, $now);
            });
    }

    public function canComplete(SpaBooking $booking): bool
    {
        return ! $booking->isCancelled()
            && $booking->completed_at === null
            && ($booking->session_started_at !== null || $this->isOngoing($booking));
    }

    public function isOngoing(SpaBooking $booking, ?Carbon $now = null): bool
    {
        return $this->resolveStatus($booking, $now) === SpaBooking::STATUS_IN_SESSION;
    }

    public function appointmentStatus(SpaBooking $booking, ?Carbon $now = null): string
    {
        $status = $this->refreshStatus($booking, $now);

        return match ($status) {
            SpaBooking::STATUS_CANCELLED => 'Cancelled',
            SpaBooking::STATUS_COMPLETED => 'Completed',
            SpaBooking::STATUS_IN_SESSION => 'In Session',
            default => $this->resolveActiveAppointmentDisplayStatus($booking),
        };
    }

    private function resolveActiveAppointmentDisplayStatus(SpaBooking $booking): string
    {
        if ($booking->rescheduled_at !== null) {
            return SpaBooking::DISPLAY_RESCHEDULED;
        }

        if ($booking->payment_type === PaymentMethodCatalog::TYPE_DOWNPAYMENT) {
            return SpaBooking::DISPLAY_PENDING;
        }

        return 'Confirmed';
    }

    public function canStart(SpaBooking $booking): bool
    {
        return $this->resolveStatus($booking) === SpaBooking::STATUS_CONFIRMED;
    }

    public function start(SpaBooking $booking): void
    {
        if (! $this->canStart($booking)) {
            throw new \InvalidArgumentException('This appointment cannot be started.');
        }

        $booking->forceFill([
            'session_started_at' => now(),
            'session_status' => SpaBooking::STATUS_IN_SESSION,
        ])->save();
    }

    public function isCompleted(SpaBooking $booking, ?Carbon $now = null): bool
    {
        return $this->resolveStatus($booking, $now) === SpaBooking::STATUS_COMPLETED;
    }

    /**
     * @return Builder<SpaBooking>
     */
    public function ongoingQuery(): Builder
    {
        return SpaBooking::query()
            ->with('user')
            ->where('session_status', SpaBooking::STATUS_IN_SESSION)
            ->whereNull('cancelled_at')
            ->whereNull('completed_at')
            ->orderBy('booking_date')
            ->orderBy('time_slot');
    }

    /**
     * @return Collection<int, SpaBooking>
     */
    public function ongoingSessions(?Carbon $now = null): Collection
    {
        $now = $now ?? now();
        $this->autoCompleteAllExpired($now);

        return $this->ongoingQuery()
            ->get()
            ->filter(fn (SpaBooking $booking): bool => $this->isOngoing($booking, $now))
            ->values();
    }

    /**
     * @return Builder<SpaBooking>
     */
    public function completedQuery(): Builder
    {
        return SpaBooking::query()
            ->with('user')
            ->where('session_status', SpaBooking::STATUS_COMPLETED)
            ->whereNull('cancelled_at')
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at');
    }

    public function complete(SpaBooking $booking): void
    {
        if ($booking->isCancelled()) {
            throw new \InvalidArgumentException('Cancelled bookings cannot be completed.');
        }

        if ($booking->completed_at !== null) {
            return;
        }

        $booking->forceFill([
            'completed_at' => now(),
            'session_status' => SpaBooking::STATUS_COMPLETED,
        ])->save();

        $this->syncTransaction($booking->fresh());
    }

    public function syncTransaction(SpaBooking $booking): Transaction
    {
        $window = $this->window($booking);
        $completedAt = $booking->completed_at ?? now();
        $client = (string) ($booking->user?->name ?: ($booking->client_name ?: 'Client #'.$booking->user_id));
        $therapistId = $this->resolveTherapistId((string) ($booking->therapist_name ?? ''));

        return Transaction::query()->updateOrCreate(
            ['spa_booking_id' => $booking->id],
            [
                'transaction_id' => $this->transactionIdFor($booking),
                'client_name' => $client,
                'user_id' => $booking->user_id,
                'therapist_id' => $therapistId,
                'service_name' => (string) $booking->service_name,
                'date' => $window['start']->toDateString(),
                'time' => $window['start']->format('H:i:s'),
                'duration' => max((int) ($booking->duration_minutes ?? 60), 1),
                'amount' => (float) ($booking->amount ?? 0),
                'notes' => trim((string) ($booking->notes ?? '')) !== ''
                    ? trim((string) $booking->notes)
                    : 'Session completed successfully.',
            ],
        );
    }

    public function transactionIdFor(SpaBooking $booking): string
    {
        return 'T'.str_pad((string) $booking->id, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, mixed>
     */
    public function toOngoingCard(SpaBooking $booking, ?Carbon $now = null): array
    {
        $window = $this->window($booking);
        $now = $now ?? now();
        $durationMinutes = max((int) ($booking->duration_minutes ?? 60), 1);
        $durationSeconds = $durationMinutes * 60;
        $sessionStart = $booking->session_started_at ?? $window['start'] ?? $now;
        $sessionEnd = $sessionStart->copy()->addMinutes($durationMinutes);
        $elapsedSeconds = $now->gte($sessionStart)
            ? min($sessionStart->diffInSeconds($now), $durationSeconds)
            : 0;
        $remainingSeconds = max($durationSeconds - $elapsedSeconds, 0);
        $progressPct = (int) round(($elapsedSeconds / max($durationSeconds, 1)) * 100);
        $amount = (float) ($booking->amount ?? 0);

        return [
            'id' => $booking->id,
            'client' => (string) ($booking->user?->name ?: ($booking->client_name ?: 'Client #'.$booking->user_id)),
            ...$this->clientSourceMeta($booking),
            'therapist' => (string) ($booking->therapist_name ?: '—'),
            'service' => (string) $booking->service_name,
            'duration_minutes' => $durationMinutes,
            'duration_seconds' => $durationSeconds,
            'elapsed_minutes' => (int) floor($elapsedSeconds / 60),
            'remaining_minutes' => (int) ceil($remainingSeconds / 60),
            'elapsed_seconds' => $elapsedSeconds,
            'remaining_seconds' => $remainingSeconds,
            'progress_pct' => min($progressPct, 100),
            'session_start_iso' => $sessionStart->toIso8601String(),
            'session_end_iso' => $sessionEnd->toIso8601String(),
            'price' => fmod($amount, 1.0) === 0.0 ? number_format($amount, 0) : number_format($amount, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toOngoingMonitorPayload(SpaBooking $booking, ?Carbon $now = null): array
    {
        $card = $this->toOngoingCard($booking, $now);

        return [
            'id' => $card['id'],
            'elapsed_seconds' => $card['elapsed_seconds'],
            'remaining_seconds' => $card['remaining_seconds'],
            'elapsed_minutes' => $card['elapsed_minutes'],
            'remaining_minutes' => $card['remaining_minutes'],
            'progress_pct' => $card['progress_pct'],
            'session_end_iso' => $card['session_end_iso'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAppointmentRow(SpaBooking $booking, ?Carbon $now = null): array
    {
        $window = $this->window($booking);
        $start = $window['start'] ?? Carbon::parse($booking->booking_date->format('Y-m-d').' 09:00 AM');
        $end = $window['end'] ?? $start->copy()->addMinutes(max((int) ($booking->duration_minutes ?? 60), 1));

        return [
            'booking_id' => (string) $booking->id,
            'client' => (string) ($booking->client_name ?: ($booking->user?->name ?: 'Client #'.$booking->user_id)),
            ...$this->clientSourceMeta($booking),
            'service' => (string) $booking->service_name,
            'therapist_name' => trim((string) $booking->therapist_name),
            'therapist' => trim((string) $booking->therapist_name) !== '' ? trim((string) $booking->therapist_name) : '—',
            'date' => $booking->booking_date->format('M d, Y'),
            'booking_date_iso' => $booking->booking_date->format('Y-m-d'),
            'time_slot' => (string) $booking->time_slot,
            'parsed_date' => $booking->booking_date->copy()->startOfDay(),
            'time' => $start->format('h:i A').' - '.$end->format('h:i A'),
            'starts_at' => $start,
            'status' => $this->appointmentStatus($booking, $now),
            'notes' => trim((string) ($booking->notes ?? '')) !== '' ? trim((string) $booking->notes) : 'No notes provided.',
            'can_start' => $this->canStart($booking),
            'can_reschedule' => app(BookingRescheduleService::class)->canStaffReschedule($booking),
            'can_cancel' => app(BookingCancellationService::class)->canStaffCancel($booking),
            'client_user_id' => (int) $booking->user_id,
            ...$this->paymentMeta($booking),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toCompletedRow(SpaBooking $booking): array
    {
        $window = $this->window($booking);
        $completedAt = $booking->completed_at ?? $window['end'] ?? now();
        $amount = (float) ($booking->amount ?? 0);

        return [
            'transaction_id' => $this->transactionIdFor($booking),
            'client' => (string) ($booking->user?->name ?: ($booking->client_name ?: 'Client #'.$booking->user_id)),
            ...$this->clientSourceMeta($booking),
            'therapist' => (string) ($booking->therapist_name ?: '—'),
            'service' => (string) $booking->service_name,
            'date_time' => $completedAt->format('M d, Y, h:i A'),
            'parsed_date' => $completedAt->copy(),
            'duration_minutes' => max((int) ($booking->duration_minutes ?? 60), 1),
            'amount' => fmod($amount, 1.0) === 0.0 ? number_format($amount, 0) : number_format($amount, 2),
            'notes' => trim((string) ($booking->notes ?? '')) !== ''
                ? trim((string) $booking->notes)
                : 'Session completed successfully.',
        ];
    }

    private function resolveTherapistId(string $therapistName): int
    {
        $name = trim($therapistName);

        if ($name === '') {
            return (int) (Therapist::query()->value('id') ?? 1);
        }

        $therapist = Therapist::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();

        return (int) ($therapist?->id ?? Therapist::query()->value('id') ?? 1);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function toReceptionistSessionRow(SpaBooking $booking, ?Carbon $now = null): ?array
    {
        if (! $this->isOngoing($booking, $now)) {
            return null;
        }

        $card = $this->toOngoingCard($booking, $now);
        $elapsedMinutes = (int) floor(((int) $card['elapsed_seconds']) / 60);
        $phase = ((int) $card['elapsed_seconds']) < 300 ? 'Warm up' : 'In session';

        return [
            'client' => $card['client'],
            'service' => $card['service'],
            'therapist' => $card['therapist'],
            'progress' => $elapsedMinutes.'/'.$card['duration_minutes'].' min',
            'phase' => $phase,
            'phase_class' => ((int) $card['elapsed_seconds']) < 300 ? 'waiting' : 'active',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toReceptionistAppointmentRow(SpaBooking $booking, ?Carbon $now = null): array
    {
        $window = $this->window($booking);
        $start = $window['start'] ?? Carbon::parse($booking->booking_date->format('Y-m-d').' 09:00 AM');
        $status = $this->appointmentStatus($booking, $now);
        $statusClass = match ($status) {
            'Cancelled' => 'cancelled',
            'Completed' => 'completed',
            'In Session' => 'active',
            SpaBooking::DISPLAY_PENDING => 'pending',
            SpaBooking::DISPLAY_RESCHEDULED => 'rescheduled',
            default => 'confirmed',
        };

        return [
            'client' => (string) ($booking->user?->name ?: ($booking->client_name ?: 'Client #'.$booking->user_id)),
            ...$this->clientSourceMeta($booking),
            'service' => (string) $booking->service_name,
            'therapist' => (string) ($booking->therapist_name ?: '—'),
            'time' => $start->format('h:i A'),
            'status' => $status,
            'status_class' => $statusClass,
            ...$this->paymentMeta($booking),
        ];
    }

    /**
     * @return array{client_source: string, client_source_label: string}
     */
    private function clientSourceMeta(SpaBooking $booking): array
    {
        $source = $this->resolveBookingSource($booking);

        return [
            'client_source' => $source,
            'client_source_label' => $source === SpaBooking::SOURCE_WALK_IN ? 'Walk-in' : 'Online Appointment',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentMeta(SpaBooking $booking): array
    {
        $paymentAmount = (float) ($booking->payment_amount ?? 0);
        $serviceAmount = (float) ($booking->amount ?? 0);
        $hasPayment = filled($booking->payment_method);

        return [
            'payment_method' => (string) ($booking->payment_method ?? ''),
            'payment_method_label' => PaymentMethodCatalog::labelFor($booking->payment_method),
            'payment_type' => (string) ($booking->payment_type ?? ''),
            'payment_type_label' => PaymentMethodCatalog::typeLabelFor($booking->payment_type),
            'payment_amount' => $paymentAmount > 0 ? '₱'.number_format($paymentAmount, 2) : '—',
            'payment_amount_raw' => $paymentAmount,
            'service_amount' => $serviceAmount > 0 ? '₱'.number_format($serviceAmount, 2) : '—',
            'payment_status' => (string) ($booking->payment_status ?? ''),
            'can_retry_paymongo' => $booking->booking_source === SpaBooking::SOURCE_WALK_IN
                && $booking->payment_method === PaymentMethodCatalog::METHOD_PAYMONGO
                && $booking->payment_status === PaymentMethodCatalog::STATUS_PENDING,
            'payment_proof_url' => $booking->payment_proof_path ? public_storage_url($booking->payment_proof_path) : '',
            'payment_transaction_id' => (string) ($booking->payment_transaction_id ?? ''),
            'payment_status_label' => PaymentMethodCatalog::statusLabelFor($booking->payment_status),
            'refund_status' => (string) ($booking->refund_status ?? ''),
            'refund_status_label' => app(BookingRefundService::class)->labelFor($booking->refund_status),
            'refund_amount' => (float) ($booking->refund_amount ?? 0) > 0
                ? '₱'.number_format((float) $booking->refund_amount, 2)
                : '',
            'refund_reference' => (string) ($booking->refund_reference ?? ''),
            'refund_note' => (string) ($booking->refund_note ?? ''),
            'refunded_at' => $booking->refunded_at?->format('M j, Y g:i A') ?? '',
            'can_complete_refund' => app(BookingRefundService::class)->canCompleteManualRefund($booking),
            'is_refundable' => app(BookingRefundService::class)->shouldRefund($booking),
            'payment_summary' => $hasPayment
                ? PaymentMethodCatalog::labelFor($booking->payment_method)
                    .' · '.PaymentMethodCatalog::typeLabelFor($booking->payment_type)
                    .' · ₱'.number_format($paymentAmount, 2)
                    .(filled($booking->payment_transaction_id) ? ' · Ref: '.$booking->payment_transaction_id : '')
                    .($booking->payment_status ? ' · '.PaymentMethodCatalog::statusLabelFor($booking->payment_status) : '')
                : '—',
        ];
    }

    private function resolveBookingSource(SpaBooking $booking): string
    {
        if (Schema::hasColumn('spa_bookings', 'booking_source')) {
            $stored = (string) ($booking->booking_source ?? '');

            if ($stored === SpaBooking::SOURCE_WALK_IN || $stored === SpaBooking::SOURCE_ONLINE) {
                return $stored;
            }
        }

        return ($booking->user?->isWalkIn() ?? false)
            ? SpaBooking::SOURCE_WALK_IN
            : SpaBooking::SOURCE_ONLINE;
    }

    /**
     * @return array{
     *     ongoing_sessions: int,
     *     current_sessions: array<int, array<string, mixed>>,
     *     today_appointments: array<int, array<string, mixed>>,
     *     appointments_today: int,
     *     today_sales: string,
     *     today_transactions: int,
     *     active_therapists: int,
     *     therapist_count: int,
     *     server_now_iso: string,
     *     timezone: string
     * }
     */
    public function dashboardSnapshot(?Carbon $now = null): array
    {
        $now = $now ?? now();
        $today = $now->copy()->startOfDay();

        $bookingsToday = SpaBooking::query()
            ->with('user')
            ->whereDate('booking_date', $today)
            ->orderBy('time_slot')
            ->get();

        $ongoing = $this->ongoingSessions($now);

        $currentSessions = $ongoing
            ->map(fn (SpaBooking $booking): ?array => $this->toReceptionistSessionRow($booking, $now))
            ->filter()
            ->values()
            ->all();

        $todayAppointments = $bookingsToday
            ->map(fn (SpaBooking $booking): array => $this->toReceptionistAppointmentRow($booking, $now))
            ->values()
            ->all();

        $todaySalesTotal = (float) SpaBooking::query()
            ->whereDate('booking_date', $today)
            ->whereNotNull('completed_at')
            ->sum('amount');

        $todayTransactionCount = SpaBooking::query()
            ->whereDate('booking_date', $today)
            ->whereNotNull('completed_at')
            ->count();

        $appointmentsTodayCount = $bookingsToday
            ->filter(fn (SpaBooking $booking): bool => ! $booking->isCancelled() && ! $this->isCompleted($booking, $now))
            ->count();

        $therapistCount = Therapist::query()->count();
        $activeTherapistCount = Therapist::query()->where('status', 'available')->count();

        return [
            'ongoing_sessions' => count($currentSessions),
            'current_sessions' => $currentSessions,
            'today_appointments' => $todayAppointments,
            'appointments_today' => $appointmentsTodayCount,
            'today_sales' => $todaySalesTotal > 0 ? number_format($todaySalesTotal, 0) : '0',
            'today_transactions' => $todayTransactionCount,
            'active_therapists' => $activeTherapistCount > 0 ? $activeTherapistCount : $therapistCount,
            'therapist_count' => $therapistCount > 0 ? $therapistCount : 0,
            'server_now_iso' => $now->toIso8601String(),
            'timezone' => (string) config('app.timezone', 'Asia/Manila'),
        ];
    }
}
