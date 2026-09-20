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
        if (! Schema::hasTable('time_slots')) {
            Schema::create('time_slots', function (Blueprint $table) {
                $table->id();
                $table->string('label', 30)->unique();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('service_time_slots')) {
            Schema::create('service_time_slots', function (Blueprint $table) {
                $table->id();
                $table->string('service_name');
                $table->foreignId('time_slot_id')->constrained('time_slots')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['service_name', 'time_slot_id'], 'service_time_slots_service_slot_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_time_slots');
        Schema::dropIfExists('time_slots');
    }
};
