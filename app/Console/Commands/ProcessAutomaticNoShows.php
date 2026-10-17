<?php

namespace App\Console\Commands;

use App\Services\NoShowService;
use App\Services\OperationalMonitoringService;
use Illuminate\Console\Command;

class ProcessAutomaticNoShows extends Command
{
    protected $signature = 'appointments:process-no-shows';

    protected $description = 'Record unattended appointments as no-shows after the staff review window expires';

    public function handle(NoShowService $noShows, OperationalMonitoringService $monitor): int
    {
        $processed = $monitor->run('no_show', fn () => $noShows->processOverdue());

        $this->info("Processed {$processed} automatic no-show appointment(s).");

        return self::SUCCESS;
    }
}
