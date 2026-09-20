<?php

namespace Database\Seeders;

use App\Services\TherapistCatalog;
use Illuminate\Database\Seeder;

class TherapistSeeder extends Seeder
{
    public function run(): void
    {
        app(TherapistCatalog::class)->ensureSeeded();
    }
}
