<?php

namespace Database\Seeders;

use App\Services\BookingSlotService;
use Illuminate\Database\Seeder;

class SpaTimeSlotSeeder extends Seeder
{
    public function run(): void
    {
        app(BookingSlotService::class)->seedDefaults();
    }
}
