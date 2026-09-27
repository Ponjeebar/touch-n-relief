<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spa_booking_id')->constrained()->cascadeOnDelete();
            $table->string('entry_type', 30);
            $table->decimal('amount', 10, 2);
            $table->string('payment_method', 30)->nullable();
            $table->string('reference')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->boolean('is_estimated')->default(false);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['spa_booking_id', 'entry_type']);
            $table->index(['entry_type', 'occurred_at']);
        });

        $now = now();
        DB::table('spa_bookings')
            ->orderBy('id')
            ->chunkById(200, function ($bookings) use ($now): void {
                $rows = [];

                foreach ($bookings as $booking) {
                    $paymentAmount = round((float) ($booking->payment_amount ?? 0), 2);
                    if (in_array((string) ($booking->payment_status ?? ''), ['paid', 'refunded'], true) && $paymentAmount > 0) {
                        $rows[] = [
                            'spa_booking_id' => $booking->id,
                            'entry_type' => 'initial_payment',
                            'amount' => $paymentAmount,
                            'payment_method' => $booking->payment_method,
                            'reference' => $booking->payment_transaction_id,
                            'occurred_at' => $booking->created_at ?? $now,
                            'is_estimated' => true,
                            'recorded_by' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    $balanceAmount = round((float) ($booking->balance_amount ?? 0), 2);
                    if ($booking->balance_paid_at !== null && $balanceAmount > 0) {
                        $rows[] = [
                            'spa_booking_id' => $booking->id,
                            'entry_type' => 'balance_payment',
                            'amount' => $balanceAmount,
                            'payment_method' => $booking->balance_payment_method,
                            'reference' => $booking->balance_payment_reference,
                            'occurred_at' => $booking->balance_paid_at,
                            'is_estimated' => false,
                            'recorded_by' => $booking->balance_collected_by,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    $refundAmount = round((float) ($booking->refund_amount ?? 0), 2);
                    if ((string) ($booking->refund_status ?? '') === 'processed' && $booking->refunded_at !== null && $refundAmount > 0) {
                        $rows[] = [
                            'spa_booking_id' => $booking->id,
                            'entry_type' => 'refund',
                            'amount' => $refundAmount,
                            'payment_method' => $booking->payment_method,
                            'reference' => $booking->refund_reference,
                            'occurred_at' => $booking->refunded_at,
                            'is_estimated' => false,
                            'recorded_by' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if ($rows !== []) {
                    DB::table('payment_ledger_entries')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_ledger_entries');
    }
};
