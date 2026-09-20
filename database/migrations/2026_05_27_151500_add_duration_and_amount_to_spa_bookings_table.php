<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('time_slot');
            $table->decimal('amount', 10, 2)->nullable()->after('duration_minutes');
        });

        $serviceDefaults = [
            'Swedish Massage' => ['duration' => 60, 'amount' => 85.00],
            'Deep Tissue' => ['duration' => 90, 'amount' => 140.00],
            'Hot Stone' => ['duration' => 90, 'amount' => 145.00],
            'Aromatherapy' => ['duration' => 60, 'amount' => 95.00],
            'Prenatal Massage' => ['duration' => 60, 'amount' => 110.00],
            'Thai Massage' => ['duration' => 75, 'amount' => 110.00],
            'Sports Massage' => ['duration' => 60, 'amount' => 105.00],
            'Foot Reflexology' => ['duration' => 45, 'amount' => 70.00],
        ];

        foreach ($serviceDefaults as $service => $meta) {
            DB::table('spa_bookings')
                ->where('service_name', $service)
                ->where(function ($query): void {
                    $query->whereNull('duration_minutes')
                        ->orWhereNull('amount');
                })
                ->update([
                    'duration_minutes' => $meta['duration'],
                    'amount' => $meta['amount'],
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->dropColumn(['duration_minutes', 'amount']);
        });
    }
};
