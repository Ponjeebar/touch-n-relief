<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->string('payment_method', 30)->nullable()->after('amount');
            $table->string('payment_type', 20)->nullable()->after('payment_method');
            $table->decimal('payment_amount', 10, 2)->nullable()->after('payment_type');
            $table->string('payment_proof_path')->nullable()->after('payment_amount');
            $table->string('payment_status', 30)->default('pending')->after('payment_proof_path');
        });
    }

    public function down(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method',
                'payment_type',
                'payment_amount',
                'payment_proof_path',
                'payment_status',
            ]);
        });
    }
};
