<?php

use App\Models\SpaBooking;
use App\Services\SpaSessionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('spa_booking_id')
                ->nullable()
                ->after('id')
                ->constrained('spa_bookings')
                ->nullOnDelete();
        });

        $sessions = app(SpaSessionService::class);

        SpaBooking::query()
            ->whereNull('cancelled_at')
            ->whereNotNull('completed_at')
            ->orderBy('id')
            ->each(function (SpaBooking $booking) use ($sessions): void {
                $sessions->syncTransaction($booking);
            });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('spa_booking_id');
        });
    }
};
