<?php

namespace App\Services;

use App\Models\SpaBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BookingSuggestionService
{
    public function __construct(
        private readonly UserActivityService $activity,
    ) {}

    /**
     * @param  array<int, array{name: string, price: string, duration: string, best_for: string, times: array<int, string>}>  $services
     * @param  array<int, array{name: string, photo: string, role: string, specialties: array<int, string>}>  $therapists
     * @return array{
     *     has_completed_transaction: bool,
     *     total_visits: int,
     *     last_visit: ?string,
     *     last_visit_ago: ?string,
     *     top_service: ?string,
     *     top_therapist: ?string,
     *     most_frequent: array<int, array{name: string, count: int}>,
     *     recommended: array<int, string>
     * }
     */
    public function historyFor(User $user, array $services, array $therapists): array
    {
        $empty = [
            'has_completed_transaction' => false,
            'total_visits' => 0,
            'last_visit' => null,
            'last_visit_ago' => null,
            'top_service' => null,
            'top_therapist' => null,
            'most_frequent' => [],
            'recommended' => [],
        ];

        $visits = $this->activity->completedVisitsFor($user);

        if ($visits->isEmpty()) {
            return $empty;
        }

        $catalogNames = collect($services)->pluck('name')->all();
        $serviceCounts = $visits
            ->countBy('service_name')
            ->sortDesc();

        $mostFrequent = $serviceCounts
            ->take(3)
            ->map(fn (int $count, string $name) => ['name' => $name, 'count' => $count])
            ->values()
            ->all();

        $topService = $serviceCounts->keys()->first();
        $bookedServiceNames = $serviceCounts->keys()->all();

        $recommended = collect($catalogNames)
            ->diff($bookedServiceNames)
            ->values()
            ->take(3)
            ->all();

        if ($recommended === []) {
            $recommended = collect($catalogNames)
                ->reject(fn (string $name) => $name === $topService)
                ->take(3)
                ->values()
                ->all();
        }

        $topTherapist = $visits
            ->pluck('therapist_name')
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        if (! $topTherapist) {
            $topTherapist = SpaBooking::query()
                ->where('user_id', $user->id)
                ->pluck('therapist_name')
                ->filter()
                ->countBy()
                ->sortDesc()
                ->keys()
                ->first();
        }

        $therapistNames = collect($therapists)->pluck('name')->all();
        if ($topTherapist && ! in_array($topTherapist, $therapistNames, true)) {
            $topTherapist = null;
        }

        $lastRow = $visits->first();
        $lastVisitDate = $lastRow ? Carbon::parse($lastRow->date) : null;
        $lastVisit = $lastVisitDate?->format('M j, Y');
        $lastVisitAgo = $lastVisitDate?->diffForHumans();

        return [
            'has_completed_transaction' => true,
            'total_visits' => $visits->count(),
            'last_visit' => $lastVisit,
            'last_visit_ago' => $lastVisitAgo,
            'top_service' => $topService && in_array($topService, $catalogNames, true) ? $topService : null,
            'top_therapist' => $topTherapist,
            'most_frequent' => $mostFrequent,
            'recommended' => $recommended,
        ];
    }
}
