<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_services', function (Blueprint $table): void {
            $table->string('offering_type', 20)->default('service')->index();
            $table->decimal('member_price_amount', 10, 2)->nullable();
            $table->text('inclusions')->nullable();
        });

        Schema::create('membership_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('price_amount', 10, 2);
            $table->text('description');
            $table->json('benefits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $packages = [
            [
                'name' => 'THERA #1', 'price_amount' => 399, 'member_price_amount' => null,
                'duration_minutes' => 60, 'best_for' => 'Relaxation starter',
                'description' => 'A compact therapeutic package for foot relief and whole-body relaxation.',
                'inclusions' => 'Foot soak reflexology + Relaxation massage', 'image' => null,
                'offering_type' => 'package', 'prenatal_only' => false, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'THERA #2', 'price_amount' => 599, 'member_price_amount' => 549,
                'duration_minutes' => 90, 'best_for' => 'Focused relief',
                'description' => 'An extended therapeutic package with focused massage care.',
                'inclusions' => 'Foot soak reflexology + Thera massage + Focus treatment', 'image' => null,
                'offering_type' => 'package', 'prenatal_only' => false, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'THERA #3', 'price_amount' => 799, 'member_price_amount' => 699,
                'duration_minutes' => 120, 'best_for' => 'Full relaxation',
                'description' => 'A longer therapeutic package for deeper rest and recovery.',
                'inclusions' => 'Foot soak reflexology + Relaxation massage', 'image' => null,
                'offering_type' => 'package', 'prenatal_only' => false, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
        ];
        foreach ($packages as $package) {
            DB::table('spa_services')->updateOrInsert(['name' => $package['name']], $package);
        }

        if (Schema::hasTable('time_slots') && Schema::hasTable('service_time_slots')) {
            $slotIds = DB::table('time_slots')->where('is_active', true)->pluck('id');
            $packageNames = ['THERA #1', 'THERA #2', 'THERA #3'];
            $rows = [];
            foreach ($packageNames as $packageName) {
                foreach ($slotIds as $slotId) {
                    $rows[] = [
                        'service_name' => $packageName,
                        'time_slot_id' => $slotId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            if ($rows !== []) {
                DB::table('service_time_slots')->insertOrIgnore($rows);
            }
        }

        DB::table('membership_plans')->updateOrInsert(['name' => 'Buenos Touché Membership'], [
            'name' => 'Buenos Touché Membership',
            'price_amount' => 499,
            'description' => 'Unlock member pricing on eligible THERA packages.',
            'benefits' => json_encode(['Member rates on THERA #2 and THERA #3', '90-minute whole-body promo for PHP 799.00']),
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_plans');
        if (Schema::hasTable('service_time_slots')) {
            DB::table('service_time_slots')->whereIn('service_name', ['THERA #1', 'THERA #2', 'THERA #3'])->delete();
        }
        DB::table('spa_services')
            ->where('offering_type', 'package')
            ->whereIn('name', ['THERA #1', 'THERA #2', 'THERA #3'])
            ->delete();
        Schema::table('spa_services', function (Blueprint $table): void {
            $table->dropIndex(['offering_type']);
            $table->dropColumn(['offering_type', 'member_price_amount', 'inclusions']);
        });
    }
};
