<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\SpaBooking;
use App\Models\User;
use App\Services\BookingCancellationService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserActivityService
{
    public function __construct(
        private readonly BookingCancellationService $cancellations,
        private readonly BookingRescheduleService $reschedules,
        private readonly SpaSessionService $sessions,
    ) {}
    /**
     * All activity for the transactions modal: confirmed bookings + completed sessions.
     *
     * @return list<array{
     *     transaction_id: string,
     *     service: string,
     *     therapist: string,
     *     date: string,
     *     time: string,
     *     duration: string,
     *     amount: string,
     *     amount_raw: float,
     *     status: string,
     *     sort_ts: int
     * }>
     */
    public function transactionsForUser(User $user, string $sort = 'newest'): array
    {
        $rows = [];

        foreach ($this->spaBookingsFor($user) as $booking) {
            $row = $this->sessions->toUserTransactionRow($booking);
            $row['can_cancel'] = $this->cancellations->canCancel($booking);
            $row['can_reschedule'] = $this->reschedules->canReschedule($booking);
            $rows[] = $row;
        }

        $linkedBookingIds = collect($rows)
            ->pluck('booking_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id);

        foreach ($this->completedTransactionRowsFor($user) as $row) {
            if (! empty($row->spa_booking_id) && $linkedBookingIds->contains((int) $row->spa_booking_id)) {
                continue;
            }

            $duration = (int) ($row->duration ?? 0);
            $date = (string) $row->date;
            $amountRaw = (float) $row->amount;

            $rows[] = [
                'booking_id' => null,
                'transaction_id' => (string) $row->transaction_id,
                'service' => (string) $row->service_name,
                'therapist' => '—',
                'date' => $date,
                'time' => Carbon::parse($row->time)->format('g:i A'),
                'duration' => $duration > 0 ? $duration.' min' : '—',
                'amount' => '₱'.number_format($amountRaw, 2),
                'amount_raw' => $amountRaw,
                'status' => 'Completed session',
                'session_status' => SpaBooking::STATUS_COMPLETED,
                'sort_ts' => $this->sortTimestamp($date, (string) $row->time),
                'activity_ts' => $this->sortTimestamp($date, (string) $row->time),
                'can_cancel' => false,
                'is_booking' => false,
                'cancellation_reason' => null,
            ];
        }

        return $this->sortTransactionRows($rows, $sort);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sortTransactionRows(array $rows, string $sort): array
    {
        $sorted = $rows;

        usort($sorted, function (array $a, array $b) use ($sort): int {
            $activityA = (int) ($a['activity_ts'] ?? $a['sort_ts'] ?? 0);
            $activityB = (int) ($b['activity_ts'] ?? $b['sort_ts'] ?? 0);

            return match ($sort) {
                'date_oldest', 'oldest' => ($a['sort_ts'] <=> $b['sort_ts']),
                'amount_high' => ($b['amount_raw'] <=> $a['amount_raw']) ?: ($activityB <=> $activityA),
                'amount_low' => ($a['amount_raw'] <=> $b['amount_raw']) ?: ($activityB <=> $activityA),
                'date_newest', 'newest' => ($activityB <=> $activityA) ?: ($b['sort_ts'] <=> $a['sort_ts']),
                default => ($activityB <=> $activityA) ?: ($b['sort_ts'] <=> $a['sort_ts']),
            };
        });

        return $sorted;
    }

    private function sortTimestamp(string $date, string $time): int
    {
        try {
            $timeValue = trim($time) !== '' ? $time : '12:00 AM';

            return Carbon::parse($date.' '.$timeValue)->timestamp;
        } catch (\Throwable) {
            return Carbon::parse($date)->startOfDay()->timestamp;
        }
    }

    /**
     * @return array{amount: string, amount_raw: float, duration: string}
     */
    private function serviceMetaFor(string $serviceName, ?SpaBooking $booking = null): array
    {
        if ($booking !== null) {
            $amountRaw = (float) ($booking->amount ?? 0);
            $durationMinutes = (int) ($booking->duration_minutes ?? 0);

            if ($amountRaw > 0 || $durationMinutes > 0) {
                return [
                    'amount' => $amountRaw > 0 ? '₱'.number_format($amountRaw, 2) : '—',
                    'amount_raw' => $amountRaw,
                    'duration' => $durationMinutes > 0 ? $durationMinutes.' min' : '—',
                ];
            }
        }

        $catalog = $this->serviceCatalog();

        if (! isset($catalog[$serviceName])) {
            return [
                'amount' => '—',
                'amount_raw' => 0.0,
                'duration' => '—',
            ];
        }

        $amountRaw = (float) $catalog[$serviceName]['price'];

        return [
            'amount' => '₱'.number_format($amountRaw, 2),
            'amount_raw' => $amountRaw,
            'duration' => $catalog[$serviceName]['duration'],
        ];
    }

    /**
     * @return array<string, array{price: float, duration: string}>
     */
    private function serviceCatalog(): array
    {
        return [
            'Swedish Massage' => ['price' => 85.00, 'duration' => '60 min'],
            'Deep Tissue' => ['price' => 140.00, 'duration' => '90 min'],
            'Hot Stone' => ['price' => 145.00, 'duration' => '90 min'],
            'Aromatherapy' => ['price' => 95.00, 'duration' => '60 min'],
            'Prenatal Massage' => ['price' => 110.00, 'duration' => '60 min'],
            'Thai Massage' => ['price' => 110.00, 'duration' => '75 min'],
            'Sports Massage' => ['price' => 105.00, 'duration' => '60 min'],
            'Foot Reflexology' => ['price' => 70.00, 'duration' => '45 min'],
        ];
    }

    public function hasCompletedVisit(User $user): bool
    {
        return $this->completedVisitsFor($user)->isNotEmpty();
    }

    /**
     * Visits used for "Suggested for You" (completed sessions + past appointments).
     */
    public function completedVisitsFor(User $user): Collection
    {
        $visits = $this->completedTransactionRowsFor($user);

        SpaBooking::query()
            ->active()
            ->where('user_id', $user->id)
            ->where(function ($query): void {
                $query->whereNotNull('completed_at')
                    ->orWhereDate('booking_date', '<=', now()->toDateString());
            })
            ->orderByDesc('booking_date')
            ->get()
            ->each(function (SpaBooking $booking) use ($visits): void {
                if ($booking->completed_at === null && ! $this->sessions->isCompleted($booking)) {
                    return;
                }

                $visits->push((object) [
                    'service_name' => $booking->service_name,
                    'date' => $booking->booking_date->format('Y-m-d'),
                    'time' => $booking->time_slot,
                    'therapist_name' => $booking->therapist_name,
                ]);
            });

        return $visits->sortByDesc(fn ($row) => $row->date.' '.$row->time)->values();
    }

    /**
     * @return Collection<int, object>
     */
    private function completedTransactionRowsFor(User $user): Collection
    {
        $clientNames = $this->clientNamesFor($user);

        if ($clientNames->isEmpty()) {
            return collect();
        }

        return DB::table('transactions')
            ->where(function ($query) use ($user, $clientNames) {
                $query->where('user_id', $user->id);

                foreach ($clientNames as $name) {
                    $query->orWhere(function ($inner) use ($name) {
                        $inner->whereNull('user_id')
                            ->whereRaw('LOWER(client_name) = ?', [strtolower(trim($name))]);
                    });
                }
            })
            ->orderByDesc('date')
            ->orderByDesc('time')
            ->get();
    }

    /**
     * @return Collection<int, SpaBooking>
     */
    private function spaBookingsFor(User $user): Collection
    {
        return SpaBooking::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return Collection<int, string>
     */
    private function clientNamesFor(User $user): Collection
    {
        return collect([$user->name])
            ->merge(
                Customer::query()
                    ->whereRaw('LOWER(email) = ?', [strtolower($user->email)])
                    ->pluck('full_name')
            )
            ->filter()
            ->unique(fn (string $name) => strtolower(trim($name)))
            ->values();
    }
}
