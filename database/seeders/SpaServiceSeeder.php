<?php

namespace Database\Seeders;

use App\Services\SpaServiceCatalog;
use Illuminate\Database\Seeder;

class SpaServiceSeeder extends Seeder
{
    public function run(): void
    {
        app(SpaServiceCatalog::class)->ensureSeeded();
    }
}
