<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('therapists')) {
            return;
        }

        Schema::create('therapists', function (Blueprint $table) {
            $table->id();
            $table->string('therapist_code')->unique();
            $table->string('name');
            $table->string('avatar_initials', 4)->nullable();
            $table->string('photo_url')->nullable();
            $table->json('specializations')->nullable();
            $table->string('status', 20)->default('available');
            $table->unsignedSmallInteger('total_hours')->default(0);
            $table->decimal('rating', 3, 1)->default(0);
            $table->unsignedTinyInteger('service_hours_pct')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('therapists');
    }
};

