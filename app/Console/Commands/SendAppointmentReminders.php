<?php

namespace App\Console\Commands;

use App\Services\CustomerNotificationService;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Send upcoming appointment reminders to registered customers';

    public function handle(CustomerNotificationService $notifications): int
    {
        $sent = $notifications->sendDueReminders();

        $this->info('Processed appointment reminders. Created or matched '.count($sent).' notification(s).');

        return self::SUCCESS;
    }
}
