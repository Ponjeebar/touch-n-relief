<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->string('refund_status', 30)->nullable()->after('payment_status');
            $table->decimal('refund_amount', 10, 2)->nullable()->after('refund_status');
            $table->timestamp('refunded_at')->nullable()->after('refund_amount');
            $table->string('refund_reference', 100)->nullable()->after('refunded_at');
        });
    }

    public function down(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->dropColumn([
                'refund_status',
                'refund_amount',
                'refunded_at',
                'refund_reference',
            ]);
        });
    }
};
