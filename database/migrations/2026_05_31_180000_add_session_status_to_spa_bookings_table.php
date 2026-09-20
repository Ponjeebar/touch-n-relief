<?php

use App\Models\SpaBooking;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->string('session_status', 20)
                ->default(SpaBooking::STATUS_CONFIRMED)
                ->after('session_started_at');
        });

        SpaBooking::query()->orderBy('id')->each(function (SpaBooking $booking): void {
            $status = match (true) {
                $booking->cancelled_at !== null => SpaBooking::STATUS_CANCELLED,
                $booking->completed_at !== null => SpaBooking::STATUS_COMPLETED,
                $booking->session_started_at !== null => SpaBooking::STATUS_IN_SESSION,
                default => SpaBooking::STATUS_CONFIRMED,
            };

            $booking->forceFill(['session_status' => $status])->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->dropColumn('session_status');
        });
    }
};
