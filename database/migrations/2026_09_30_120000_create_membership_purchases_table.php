<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_plans', function (Blueprint $table): void {
            $table->unsignedSmallInteger('validity_days')->default(365)->after('price_amount');
        });

        Schema::create('membership_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan_name');
            $table->unsignedSmallInteger('validity_days');
            $table->decimal('amount', 10, 2);
            $table->string('payment_method', 30)->default('paymongo');
            $table->string('payment_status', 30)->default('pending');
            $table->string('status', 30)->default('pending');
            $table->string('paymongo_checkout_session_id')->nullable()->unique();
            $table->string('payment_transaction_id')->nullable()->unique();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'status', 'expires_at']);
            $table->index(['payment_status', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_purchases');

        Schema::table('membership_plans', function (Blueprint $table): void {
            $table->dropColumn('validity_days');
        });
    }
};
