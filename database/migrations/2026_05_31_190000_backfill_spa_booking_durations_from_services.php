<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('spa_bookings') || ! Schema::hasTable('spa_services')) {
            return;
        }

        if (! Schema::hasColumn('spa_bookings', 'duration_minutes')) {
            return;
        }

        DB::table('spa_bookings')
            ->whereNull('duration_minutes')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('spa_services')
                    ->whereColumn('spa_services.name', 'spa_bookings.service_name');
            })
            ->update(['duration_minutes' => DB::raw('(SELECT duration_minutes FROM spa_services WHERE spa_services.name = spa_bookings.service_name LIMIT 1)')]);

        if (Schema::hasColumn('spa_bookings', 'amount')) {
            DB::table('spa_bookings')
                ->whereNull('amount')
                ->whereExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('spa_services')
                        ->whereColumn('spa_services.name', 'spa_bookings.service_name');
                })
                ->update(['amount' => DB::raw('(SELECT price_amount FROM spa_services WHERE spa_services.name = spa_bookings.service_name LIMIT 1)')]);
        }

        DB::table('spa_bookings')
            ->whereNull('duration_minutes')
            ->update(['duration_minutes' => 60]);
    }

    public function down(): void
    {
        // Data backfill only; no schema to revert.
    }
};
