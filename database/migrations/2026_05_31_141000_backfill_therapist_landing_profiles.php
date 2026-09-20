<?php

use App\Services\TherapistCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('therapists')) {
            return;
        }

        app(TherapistCatalog::class)->ensureSeeded();
    }

    public function down(): void
    {
        // Data backfill is not reversed.
    }
};
