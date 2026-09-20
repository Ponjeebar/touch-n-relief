<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ActivityLogService
{
    /**
     * @return array{
     *     role: string|null,
     *     action: string|null,
     *     search: string,
     *     date_from: string|null,
     *     date_to: string|null,
     *     date_sort: string,
     *     page: int
     * }
     */
    public function filtersFromRequest(Request $request): array
    {
        $dateFrom = $this->normalizeDate($request->query('date_from'));
        $dateTo = $this->normalizeDate($request->query('date_to'));

        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $role = $request->query('role');
        $role = in_array($role, [User::ROLE_ADMIN, User::ROLE_RECEPTIONIST, User::ROLE_USER], true)
            ? $role
            : null;

        $dateSort = strtolower((string) $request->query('date_sort', 'desc'));

        return [
            'role' => $role,
            'action' => trim((string) $request->query('action', '')) ?: null,
            'search' => trim((string) $request->query('q', '')),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'date_sort' => $dateSort === 'asc' ? 'asc' : 'desc',
            'page' => max(1, (int) $request->query('page', 1)),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters): Builder
    {
        $search = (string) ($filters['search'] ?? '');

        return ActivityLog::query()
            ->with('user')
            ->when(
                isset($filters['role']) && $filters['role'] !== null && $filters['role'] !== '',
                fn (Builder $q) => $q->where('user_role', $filters['role']),
            )
            ->when(
                ! empty($filters['action']),
                fn (Builder $q) => $q->where('action', $filters['action']),
            )
            ->when($search !== '', function (Builder $q) use ($search): void {
                $q->where(function (Builder $inner) use ($search): void {
                    $inner->where('user_name', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhere('action', 'like', '%'.$search.'%');
                });
            })
            ->when(
                ! empty($filters['date_from']),
                fn (Builder $q) => $q->whereDate('created_at', '>=', $filters['date_from']),
            )
            ->when(
                ! empty($filters['date_to']),
                fn (Builder $q) => $q->whereDate('created_at', '<=', $filters['date_to']),
            )
            ->orderBy(
                'created_at',
                ($filters['date_sort'] ?? 'desc') === 'asc' ? 'asc' : 'desc',
            );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->query($filters)
            ->paginate($perPage, ['*'], 'page', (int) ($filters['page'] ?? 1))
            ->withQueryString();
    }

    /**
     * @return array{total: int, today: int, receptionist_today: int, admin_today: int, customer_today: int}
     */
    public function stats(): array
    {
        $today = today();

        return [
            'total' => ActivityLog::query()->count(),
            'today' => ActivityLog::query()->whereDate('created_at', $today)->count(),
            'receptionist_today' => ActivityLog::query()
                ->where('user_role', User::ROLE_RECEPTIONIST)
                ->whereDate('created_at', $today)
                ->count(),
            'admin_today' => ActivityLog::query()
                ->where('user_role', User::ROLE_ADMIN)
                ->whereDate('created_at', $today)
                ->count(),
            'customer_today' => ActivityLog::query()
                ->where('user_role', User::ROLE_USER)
                ->whereDate('created_at', $today)
                ->count(),
        ];
    }

    /**
     * @return Collection<int, string>
     */
    public function actionOptions(): Collection
    {
        return ActivityLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->reject(fn (string $item): bool => $item === 'activity_log.viewed')
            ->merge(collect([
                'appointments.view',
                'services.view',
                'service.created',
                'service.updated',
                'service.unavailable',
                'service.available',
                'ongoing_sessions.view',
                'completed_sessions.view',
                'schedule.cancelled',
                'schedule.rescheduled',
            ]))
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function toRow(ActivityLog $log): array
    {
        return [
            'id' => $log->id,
            'when_date' => $log->created_at?->format('M j, Y') ?? '—',
            'when_time' => $log->created_at?->format('g:i A') ?? '',
            'user_name' => (string) $log->user_name,
            'role' => (string) $log->user_role,
            'role_label' => $log->roleLabel(),
            'action_label' => $log->actionLabel(),
            'description' => (string) $log->description,
            'created_at_iso' => $log->created_at?->toIso8601String(),
        ];
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
