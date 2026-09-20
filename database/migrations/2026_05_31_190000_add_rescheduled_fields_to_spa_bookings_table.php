<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->timestamp('rescheduled_at')->nullable()->after('cancellation_reason');
            $table->date('rescheduled_from_date')->nullable()->after('rescheduled_at');
            $table->string('rescheduled_from_time_slot', 30)->nullable()->after('rescheduled_from_date');
        });
    }

    public function down(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->dropColumn([
                'rescheduled_at',
                'rescheduled_from_date',
                'rescheduled_from_time_slot',
            ]);
        });
    }
};
