<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $now = now();
        $defaults = [
            'footer_contact_phone' => '+63 912 345 6789',
            'footer_contact_email' => 'hello@touchnreliefspa.com',
            'footer_contact_address' => 'Wellness Ave, City Center',
            'footer_hours_weekday' => 'Mon - Fri: 9:00 AM - 9:00 PM',
            'footer_hours_weekend' => 'Sat - Sun: 10:00 AM - 10:00 PM',
            'footer_hours_holidays' => 'Holidays: By Appointment',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('site_settings')->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
