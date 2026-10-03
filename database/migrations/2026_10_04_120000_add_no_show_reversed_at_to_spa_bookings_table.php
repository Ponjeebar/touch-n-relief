<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table): void {
            $table->timestamp('no_show_reversed_at')->nullable()->after('session_status');
        });

        DB::table('activity_logs')
            ->where('action', 'appointment.no_show_reversed')
            ->whereNotNull('subject_id')
            ->whereNotNull('created_at')
            ->orderBy('created_at')
            ->get(['subject_id', 'created_at'])
            ->each(function (object $reversal): void {
                DB::table('spa_bookings')
                    ->where('id', (int) $reversal->subject_id)
                    ->where('session_status', 'confirmed')
                    ->where(function ($query) use ($reversal): void {
                        $query->whereNull('rescheduled_at')
                            ->orWhere('rescheduled_at', '<=', $reversal->created_at);
                    })
                    ->update(['no_show_reversed_at' => $reversal->created_at]);
            });
    }

    public function down(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table): void {
            $table->dropColumn('no_show_reversed_at');
        });
    }
};
