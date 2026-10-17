<?php

namespace App\Console\Commands;

use App\Services\OperationalMonitoringService;
use App\Services\SpaSessionService;
use Illuminate\Console\Command;

class CompleteExpiredSessions extends Command
{
    protected $signature = 'appointments:complete-expired-sessions';

    protected $description = 'Complete elapsed fully paid sessions without requiring browser polling';

    public function handle(SpaSessionService $sessions, OperationalMonitoringService $monitor): int
    {
        $processed = $monitor->run('completion', fn () => $sessions->autoCompleteAllExpired()->count());
        $this->info('Completed '.$processed.' expired session(s).');

        return self::SUCCESS;
    }
}
