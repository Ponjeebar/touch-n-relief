<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('spa_bookings', 'client_name')) {
                $table->string('client_name')->nullable()->after('user_id');
                $table->index('client_name');
            }
        });

        if (
            Schema::hasTable('users')
            && Schema::hasColumn('spa_bookings', 'client_name')
            && Schema::hasColumn('spa_bookings', 'user_id')
            && Schema::hasColumn('users', 'id')
            && Schema::hasColumn('users', 'name')
        ) {
            $rows = DB::table('spa_bookings')
                ->join('users', 'spa_bookings.user_id', '=', 'users.id')
                ->whereNull('spa_bookings.client_name')
                ->select(['spa_bookings.id as booking_id', 'users.name as user_name'])
                ->get();

            foreach ($rows as $row) {
                $name = trim((string) ($row->user_name ?? ''));
                if ($name === '') {
                    continue;
                }

                DB::table('spa_bookings')
                    ->where('id', $row->booking_id)
                    ->update(['client_name' => $name]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('spa_bookings', 'client_name')) {
            Schema::table('spa_bookings', function (Blueprint $table) {
                $table->dropIndex(['client_name']);
                $table->dropColumn('client_name');
            });
        }
    }
};

