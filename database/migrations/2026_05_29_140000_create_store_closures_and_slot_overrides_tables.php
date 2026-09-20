<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_closures')) {
            Schema::create('store_closures', function (Blueprint $table): void {
                $table->id();
                $table->date('closure_date')->unique();
                $table->string('note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('service_slot_date_overrides')) {
            Schema::create('service_slot_date_overrides', function (Blueprint $table): void {
                $table->id();
                $table->string('service_name');
                $table->date('slot_date');
                $table->foreignId('time_slot_id')->constrained('time_slots')->cascadeOnDelete();
                $table->boolean('is_enabled')->default(false);
                $table->timestamps();

                $table->unique(
                    ['service_name', 'slot_date', 'time_slot_id'],
                    'service_slot_date_overrides_unique',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_slot_date_overrides');
        Schema::dropIfExists('store_closures');
    }
};
