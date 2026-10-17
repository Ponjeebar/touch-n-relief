<?php

namespace App\Console\Commands;

use App\Services\OperationalMonitoringService;
use Illuminate\Console\Command;

class CheckOperations extends Command
{
    protected $signature = 'operations:check {--json : Output the current check as JSON} {--notify : Notify administrators about detected issues}';

    protected $description = 'Check appointment processors and unresolved gateway outcomes without changing business records';

    public function handle(OperationalMonitoringService $monitor): int
    {
        $snapshot = $monitor->snapshot();
        if ($this->option('notify')) {
            $monitor->notify($snapshot);
        }
        if ($this->option('json')) {
            $this->line(json_encode($snapshot, JSON_THROW_ON_ERROR));
        } else {
            $this->line('Operational checks: '.$snapshot['status']);
            foreach ($snapshot['issues'] as $issue) {
                $this->warn($issue['message']);
            }
        }

        return $snapshot['status'] === 'healthy' ? self::SUCCESS : self::FAILURE;
    }
}
