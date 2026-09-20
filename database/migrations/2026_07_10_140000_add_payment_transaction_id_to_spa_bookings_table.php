<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('spa_bookings', 'payment_transaction_id')) {
                $table->string('payment_transaction_id', 100)->nullable()->after('payment_proof_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            if (Schema::hasColumn('spa_bookings', 'payment_transaction_id')) {
                $table->dropColumn('payment_transaction_id');
            }
        });
    }
};
