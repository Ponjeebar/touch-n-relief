<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('spa_bookings')) {
            return;
        }

        Schema::table('spa_bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('spa_bookings', 'booking_source')) {
                $table->string('booking_source', 20)->default('online')->after('client_name');
                $table->index('booking_source');
            }
        });

        if (! Schema::hasColumn('spa_bookings', 'booking_source')) {
            return;
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'is_walk_in')) {
            DB::table('spa_bookings')
                ->join('users', 'spa_bookings.user_id', '=', 'users.id')
                ->where('users.is_walk_in', true)
                ->update(['spa_bookings.booking_source' => 'walk_in']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('spa_bookings') || ! Schema::hasColumn('spa_bookings', 'booking_source')) {
            return;
        }

        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->dropIndex(['booking_source']);
            $table->dropColumn('booking_source');
        });
    }
};
