<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\BookingRefund;
use App\Models\GatewayPaymentReconciliation;
use App\Models\SpaBooking;
use App\Models\StaffNotification;
use App\Models\User;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class OperationalMonitoringService
{
    private const PROCESSORS = ['no_show' => 'Automatic No Show', 'completion' => 'Automatic session completion'];

    public function run(string $processor, Closure $operation): int
    {
        try {
            $count = $operation();
        } catch (Throwable $exception) {
            $previous = $this->heartbeat($processor);
            $this->record($processor, ['status' => 'failed', 'lastAttemptAt' => now()->toIso8601String(), 'lastSuccessAt' => $previous['lastSuccessAt'] ?? null]);
            throw $exception;
        }
        $this->record($processor, ['status' => 'healthy', 'lastAttemptAt' => now()->toIso8601String(), 'lastSuccessAt' => now()->toIso8601String(), 'processed' => $count]);

        return $count;
    }

    public function snapshot(): array
    {
        $now = now();
        $delay = max(0, (int) config('touchnrelief.monitoring.processing_delay_minutes', 5));
        $stale = max(1, (int) config('touchnrelief.monitoring.processor_stale_minutes', 10));
        $hours = max(1, (int) config('touchnrelief.monitoring.gateway_review_hours', 24));
        $cutoff = $now->copy()->subMinutes($delay);
        $gatewayCutoff = $now->copy()->subHours($hours);
        $issues = [];
        $processors = [];
        foreach (self::PROCESSORS as $key => $label) {
            $heartbeat = $this->heartbeat($key);
            $lastSuccess = isset($heartbeat['lastSuccessAt']) ? Carbon::parse($heartbeat['lastSuccessAt']) : null;
            $status = $heartbeat['status'] ?? 'unknown';
            if ($status !== 'failed' && $lastSuccess !== null && $lastSuccess->lt($now->copy()->subMinutes($stale))) {
                $status = 'stale';
            }
            $processors[] = ['key' => $key, 'label' => $label, 'status' => $status, 'lastSuccessAt' => $heartbeat['lastSuccessAt'] ?? null, 'lastAttemptAt' => $heartbeat['lastAttemptAt'] ?? null];
            if (in_array($status, ['failed', 'stale'], true)) {
                $issues[] = ['key' => 'processor-'.$key, 'count' => 1, 'message' => $label.($status === 'failed' ? ' failed on its last attempt. Check application logs.' : ' has no successful run within '.$stale.' minutes. Check the scheduler.'), 'url' => route('reporting.index').'#operational-checks'];
            }
        }
        $counts = ['overdue_no_shows' => 0, 'overdue_sessions' => 0, 'aged_gateway_payments' => 0, 'aged_gateway_refunds' => 0];
        $samples = ['overdue_no_shows' => [], 'overdue_sessions' => []];
        $noShows = app(NoShowService::class);
        $sessions = app(SpaSessionService::class);
        $automaticNoShowMinutes = $noShows->automaticNoShowMinutes();
        SpaBooking::query()->visibleToStaff()->whereNull('cancelled_at')->whereNull('completed_at')
            ->whereDate('booking_date', '<=', $cutoff->toDateString())
            ->where(fn ($query) => $query->whereNull('session_status')->orWhereIn('session_status', [SpaBooking::STATUS_CONFIRMED, SpaBooking::STATUS_IN_SESSION]))
            ->orderBy('id')->chunkById(100, function ($bookings) use (&$counts, &$samples, $noShows, $sessions, $cutoff, $automaticNoShowMinutes): void {
                foreach ($bookings as $booking) {
                    $key = $noShows->canRecord($booking, $automaticNoShowMinutes, $cutoff) ? 'overdue_no_shows' : null;
                    if ($booking->isFullyPaid() && $sessions->hasExpired($booking, $cutoff)) {
                        $key = 'overdue_sessions';
                    }
                    if ($key !== null) {
                        $counts[$key]++;
                        if (count($samples[$key]) < 5) {
                            $samples[$key][] = 'RCP-'.str_pad((string) $booking->id, 5, '0', STR_PAD_LEFT).' ('.$booking->booking_date->toDateString().')';
                        }
                    }
                }
            });
        foreach (['overdue_no_shows' => 'appointments remain eligible for automatic No Show', 'overdue_sessions' => 'fully paid sessions remain unfinished after their actual end'] as $key => $label) {
            if ($counts[$key] > 0) {
                $issues[] = ['key' => $key, 'count' => $counts[$key], 'message' => $counts[$key].' '.$label.' beyond the '.$delay.' minute processing allowance. '.implode(', ', $samples[$key]), 'url' => route('appointments.index')];
            }
        }
        $payments = GatewayPaymentReconciliation::where('status', 'open')->where('received_at', '<=', $gatewayCutoff);
        $counts['aged_gateway_payments'] = (clone $payments)->count();
        if ($counts['aged_gateway_payments'] > 0) {
            $oldest = Carbon::parse((clone $payments)->min('received_at'))->toDateString();
            $issues[] = ['key' => 'aged_gateway_payments', 'count' => $counts['aged_gateway_payments'], 'message' => $counts['aged_gateway_payments'].' gateway payment case(s) have remained open for at least '.$hours.' hours. Verify the gateway records before any payout.', 'url' => route('reporting.index', ['period' => 'custom', 'date_from' => $oldest, 'date_to' => $now->toDateString()]).'#gateway-reconciliation'];
        }
        $refunds = BookingRefund::where('processing_channel', BookingRefund::CHANNEL_PAYMONGO)->where('status', BookingRefundService::STATUS_PENDING)
            ->where(fn ($query) => $query->where('requested_at', '<=', $gatewayCutoff)->orWhere(fn ($query) => $query->whereNull('requested_at')->where('created_at', '<=', $gatewayCutoff)));
        $counts['aged_gateway_refunds'] = (clone $refunds)->count();
        if ($counts['aged_gateway_refunds'] > 0) {
            $references = (clone $refunds)->orderBy('id')->limit(5)->get()->map(fn ($refund) => $refund->reference.' (RCP-'.str_pad((string) $refund->spa_booking_id, 5, '0', STR_PAD_LEFT).')')->implode(', ');
            $issues[] = ['key' => 'aged_gateway_refunds', 'count' => $counts['aged_gateway_refunds'], 'message' => $counts['aged_gateway_refunds'].' gateway refund(s) have awaited confirmation for at least '.$hours.' hours. Do not issue a second payout without reconciliation. '.$references, 'url' => route('appointments.index')];
        }

        return ['checkedAt' => $now->toIso8601String(), 'status' => $issues !== [] ? 'attention' : (collect($processors)->contains('status', 'unknown') ? 'unverified' : 'healthy'), 'processors' => $processors, 'counts' => $counts, 'issues' => $issues];
    }

    public function notify(array $snapshot): int
    {
        return DB::transaction(function () use ($snapshot): int {
            $created = 0;
            $admins = User::where('role', User::ROLE_ADMIN)->get(['id']);
            foreach ($snapshot['issues'] as $issue) {
                foreach ($admins as $admin) {
                    $notification = StaffNotification::firstOrCreate(['staff_user_id' => $admin->id, 'event_key' => 'operations:'.now()->toDateString().':'.$issue['key']], [
                        'type' => 'system', 'title' => 'Operational check needs attention',
                        'message' => $issue['message'].' Observed '.$snapshot['checkedAt'].'. Open Operational checks for current results.',
                        'url' => $issue['url'], 'occurred_at' => now(),
                    ]);
                    $created += (int) $notification->wasRecentlyCreated;
                }
            }
            if ($created > 0) {
                ActivityLog::create(['user_role' => 'system', 'user_name' => 'System', 'action' => 'operations.attention', 'description' => 'Operational checks identified work requiring administrator review.', 'properties' => ['checked_at' => $snapshot['checkedAt'], 'counts' => $snapshot['counts'], 'issue_keys' => array_column($snapshot['issues'], 'key')], 'created_at' => now()]);
                Log::warning('Operational checks require attention.', ['counts' => $snapshot['counts'], 'issue_keys' => array_column($snapshot['issues'], 'key')]);
            }

            return $created;
        });
    }

    private function heartbeat(string $processor): ?array
    {
        try {
            return Cache::store('database')->get('operations:processor:'.$processor);
        } catch (Throwable $exception) {
            Log::warning('Processor history could not be read.', ['processor' => $processor, 'exception' => $exception::class]);

            return null;
        }
    }

    private function record(string $processor, array $heartbeat): void
    {
        try {
            Cache::store('database')->forever('operations:processor:'.$processor, $heartbeat);
        } catch (Throwable $exception) {
            Log::warning('Processor history could not be recorded.', ['processor' => $processor, 'exception' => $exception::class]);
        }
    }
}
