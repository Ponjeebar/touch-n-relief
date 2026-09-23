<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table): void {
            $table->decimal('balance_amount', 10, 2)->nullable()->after('payment_status');
            $table->string('balance_payment_method', 30)->nullable()->after('balance_amount');
            $table->string('balance_payment_reference')->nullable()->after('balance_payment_method');
            $table->foreignId('balance_collected_by')->nullable()->after('balance_payment_reference')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('balance_paid_at')->nullable()->after('balance_collected_by');
        });
    }

    public function down(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('balance_collected_by');
            $table->dropColumn(['balance_amount', 'balance_payment_method', 'balance_payment_reference', 'balance_paid_at']);
        });
    }
};
