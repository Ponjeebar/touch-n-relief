<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('spa_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('service_name');
            $table->date('booking_date');
            $table->string('time_slot', 30);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['service_name', 'booking_date', 'time_slot'], 'spa_bookings_service_date_slot_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spa_bookings');
    }
};
