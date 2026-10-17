<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_payment_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway_payment_id')->unique();
            $table->foreignId('spa_booking_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('membership_purchase_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('amount', 10, 2)->nullable();
            $table->char('currency', 3)->default('PHP');
            $table->string('payment_method', 30)->nullable();
            $table->string('allocated_payment_id')->nullable();
            $table->string('status', 30)->default('open')->index();
            $table->string('reason');
            $table->timestamp('received_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_payment_reconciliations');
    }
};
