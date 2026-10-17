<?php

namespace App\Console\Commands;

use App\Services\SpaSessionService;
use Illuminate\Console\Command;

class CompleteExpiredSessions extends Command
{
    protected $signature = 'appointments:complete-expired-sessions';

    protected $description = 'Complete elapsed fully paid sessions without requiring browser polling';

    public function handle(SpaSessionService $sessions): int
    {
        $this->info('Completed '.$sessions->autoCompleteAllExpired()->count().' expired session(s).');

        return self::SUCCESS;
    }
}
