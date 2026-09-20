<?php

namespace Database\Seeders;

use App\Services\SiteSettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class SiteSettingsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        app(SiteSettingsService::class)->ensureSeeded();
    }
}
