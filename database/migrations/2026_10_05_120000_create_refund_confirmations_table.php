<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spa_booking_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('method', 30);
            $table->string('recipient', 150);
            $table->string('transfer_reference', 100)->nullable();
            $table->string('evidence_mime', 50);
            $table->longText('evidence');
            $table->timestamp('confirmed_at');
            $table->timestamp('disputed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('dispute_note')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
        });
        Schema::table('booking_refunds', function (Blueprint $table) {
            $table->foreignId('refund_confirmation_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('booking_refunds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refund_confirmation_id');
        });
        Schema::dropIfExists('refund_confirmations');
    }
};
