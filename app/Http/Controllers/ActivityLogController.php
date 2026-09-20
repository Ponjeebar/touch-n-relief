<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\ActivityLogger;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activityLogs,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->activityLogs->filtersFromRequest($request);
        $logs = $this->activityLogs->paginate($filters);
        $stats = $this->activityLogs->stats();
        $actions = $this->activityLogs->actionOptions();

        return view('activity-logs.index', [
            'logs' => $logs,
            'stats' => $stats,
            'actions' => $actions,
            'role' => $filters['role'],
            'action' => $filters['action'],
            'search' => $filters['search'],
            'dateFrom' => $filters['date_from'] ?? '',
            'dateTo' => $filters['date_to'] ?? '',
            'dateSort' => $filters['date_sort'],
        ]);
    }

    public function poll(Request $request): JsonResponse
    {
        $filters = $this->activityLogs->filtersFromRequest($request);
        $logs = $this->activityLogs->paginate($filters);
        $stats = $this->activityLogs->stats();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'stats' => $stats,
            'rows' => $logs->getCollection()
                ->map(fn (ActivityLog $log): array => $this->activityLogs->toRow($log))
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
            ],
            'latest_id' => ActivityLog::query()->max('id'),
        ]);
    }

    public function click(Request $request): Response
    {
        $user = $request->user();
        if ($user === null || (! $user->isAdmin() && ! $user->isReceptionist())) {
            return response()->noContent();
        }

        $label = trim((string) $request->query('label', 'Clicked UI element'));
        $target = trim((string) $request->query('target', ''));
        $context = trim((string) $request->query('context', 'panel'));
        $url = trim((string) $request->query('url', ''));

        ActivityLogger::log(
            'ui.click',
            $label !== '' ? $label : 'Clicked UI element',
            [
                'target' => $target !== '' ? mb_substr($target, 0, 120) : null,
                'context' => $context !== '' ? mb_substr($context, 0, 60) : null,
                'url' => $url !== '' ? mb_substr($url, 0, 255) : null,
            ],
            request: $request,
        );

        return response()->noContent();
    }
}
