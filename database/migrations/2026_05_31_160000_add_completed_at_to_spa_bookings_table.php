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
        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('cancelled_at');
        });

        $sessions = app(SpaSessionService::class);

        SpaBooking::query()
            ->whereNull('cancelled_at')
            ->whereNull('completed_at')
            ->orderBy('id')
            ->each(function (SpaBooking $booking) use ($sessions): void {
                $window = $sessions->window($booking);

                if ($window === null) {
                    return;
                }

                if (now()->gte($window['end'])) {
                    $booking->forceFill(['completed_at' => $window['end']])->save();
                }
            });
    }

    public function down(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
