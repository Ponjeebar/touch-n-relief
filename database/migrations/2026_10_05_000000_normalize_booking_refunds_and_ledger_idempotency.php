<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spa_booking_id')->constrained()->cascadeOnDelete();
            $table->string('payment_component', 30);
            $table->string('processing_channel', 20);
            $table->string('payment_method', 30)->nullable();
            $table->string('gateway_payment_id', 100)->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('status', 30);
            $table->string('reference', 100)->unique();
            $table->string('note', 500)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['spa_booking_id', 'payment_component', 'status'], 'booking_refunds_component_status_index');
            $table->index(['gateway_payment_id', 'status']);
        });

        Schema::table('payment_ledger_entries', function (Blueprint $table): void {
            $table->string('idempotency_key')->nullable()->after('spa_booking_id');
            $table->foreignId('booking_refund_id')->nullable()->after('idempotency_key')
                ->constrained('booking_refunds')->nullOnDelete();
        });

        $now = now();
        DB::table('spa_bookings')
            ->whereNotNull('refund_status')
            ->where('refund_status', '!=', 'not_applicable')
            ->where('refund_amount', '>', 0)
            ->orderBy('id')
            ->chunkById(200, function ($bookings) use ($now): void {
                foreach ($bookings as $booking) {
                    $reference = trim((string) ($booking->refund_reference ?? ''));
                    if ($reference === '') {
                        $reference = 'RF-LEGACY-'.$booking->id;
                    }
                    if (DB::table('booking_refunds')->where('reference', $reference)->exists()) {
                        $reference = mb_substr($reference, 0, 88).'-B'.$booking->id;
                    }

                    $processedAt = (string) ($booking->refund_status ?? '') === 'processed'
                        ? ($booking->refunded_at ?? $booking->updated_at ?? $now)
                        : null;
                    $refundId = DB::table('booking_refunds')->insertGetId([
                        'spa_booking_id' => $booking->id,
                        'payment_component' => 'initial_payment',
                        'processing_channel' => str_starts_with(strtolower($reference), 'ref_') ? 'paymongo' : 'manual',
                        'payment_method' => $booking->payment_method,
                        'gateway_payment_id' => $booking->payment_transaction_id,
                        'amount' => $booking->refund_amount,
                        'status' => $booking->refund_status,
                        'reference' => $reference,
                        'note' => $booking->refund_note,
                        'requested_by' => null,
                        'processed_by' => null,
                        'requested_at' => $booking->created_at ?? $now,
                        'processed_at' => $processedAt,
                        'created_at' => $booking->created_at ?? $now,
                        'updated_at' => $booking->updated_at ?? $now,
                    ]);

                    DB::table('payment_ledger_entries')
                        ->where('spa_booking_id', $booking->id)
                        ->where('entry_type', 'refund')
                        ->update(['booking_refund_id' => $refundId]);
                }
            });

        DB::table('payment_ledger_entries')
            ->orderBy('id')
            ->chunkById(200, function ($entries): void {
                foreach ($entries as $entry) {
                    $suffix = $entry->entry_type === 'refund'
                        ? 'refund:'.($entry->booking_refund_id ?: $entry->id)
                        : $entry->entry_type;
                    DB::table('payment_ledger_entries')->where('id', $entry->id)->update([
                        'idempotency_key' => 'booking:'.$entry->spa_booking_id.':'.$suffix,
                    ]);
                }
            });

        Schema::table('payment_ledger_entries', function (Blueprint $table): void {
            $table->dropUnique('payment_ledger_entries_spa_booking_id_entry_type_unique');
            $table->unique('idempotency_key');
            $table->unique('booking_refund_id');
        });
    }

    public function down(): void
    {
        DB::table('booking_refunds')
            ->select('spa_booking_id')
            ->distinct()
            ->orderBy('spa_booking_id')
            ->each(function ($row): void {
                $refunds = DB::table('booking_refunds')
                    ->where('spa_booking_id', $row->spa_booking_id)
                    ->orderBy('id')
                    ->get();
                $processed = $refunds->where('status', 'processed');
                $pending = $refunds->where('status', 'pending');
                $latest = ($pending->last() ?? $processed->last() ?? $refunds->last());
                $status = $pending->isNotEmpty()
                    ? 'pending'
                    : ($processed->isNotEmpty() ? 'processed' : 'failed');

                DB::table('spa_bookings')->where('id', $row->spa_booking_id)->update([
                    'refund_status' => $status,
                    'refund_amount' => $status === 'pending'
                        ? $pending->sum('amount') + $processed->sum('amount')
                        : $processed->sum('amount'),
                    'refunded_at' => $processed->max('processed_at'),
                    'refund_reference' => $latest?->reference,
                    'refund_note' => $latest?->note,
                ]);

                $ledger = DB::table('payment_ledger_entries')
                    ->where('spa_booking_id', $row->spa_booking_id)
                    ->where('entry_type', 'refund')
                    ->orderBy('id')
                    ->get();
                if ($ledger->isNotEmpty()) {
                    $keep = $ledger->first();
                    DB::table('payment_ledger_entries')->where('id', $keep->id)->update([
                        'amount' => $ledger->sum('amount'),
                        'reference' => $latest?->reference,
                        'occurred_at' => $ledger->max('occurred_at'),
                    ]);
                    DB::table('payment_ledger_entries')
                        ->where('spa_booking_id', $row->spa_booking_id)
                        ->where('entry_type', 'refund')
                        ->where('id', '!=', $keep->id)
                        ->delete();
                }
            });

        Schema::table('payment_ledger_entries', function (Blueprint $table): void {
            $table->dropUnique('payment_ledger_entries_idempotency_key_unique');
            $table->dropUnique('payment_ledger_entries_booking_refund_id_unique');
            $table->dropConstrainedForeignId('booking_refund_id');
            $table->dropColumn('idempotency_key');
            $table->unique(['spa_booking_id', 'entry_type']);
        });

        Schema::dropIfExists('booking_refunds');
    }
};
