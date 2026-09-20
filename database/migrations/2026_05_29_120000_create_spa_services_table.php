<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spa_services', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('price_amount', 10, 2);
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('best_for');
            $table->text('description');
            $table->string('image')->nullable();
            $table->boolean('prenatal_only')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spa_services');
    }
};
