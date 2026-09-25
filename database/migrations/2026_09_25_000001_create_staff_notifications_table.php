<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('spa_booking_id')->nullable()->constrained('spa_bookings')->nullOnDelete();
            $table->string('event_key');
            $table->string('type', 40)->default('system');
            $table->string('title');
            $table->text('message');
            $table->string('url')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['staff_user_id', 'event_key']);
            $table->index(['staff_user_id', 'read_at', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_notifications');
    }
};
