<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table): void {
            $table->string('rescheduled_from_therapist_name')->nullable();
            $table->timestamp('cancellation_deadline_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table): void {
            $table->dropColumn(['rescheduled_from_therapist_name', 'cancellation_deadline_at']);
        });
    }
};
