<?php

namespace Tests\Feature;

use App\Models\BookingRefund;
use App\Models\PaymentLedgerEntry;
use App\Models\RefundConfirmation;
use App\Models\SpaBooking;
use App\Models\User;
use App\Services\BackupRecoveryService;
use App\Services\BookingRefundService;
use App\Services\PaymentLedgerService;
use App\Support\PaymentMethodCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ManualRefundEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private function booking(): SpaBooking
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $booking = SpaBooking::create([
            'user_id' => $customer->id, 'client_name' => $customer->name,
            'service_name' => 'Massage', 'booking_date' => now()->addDay()->toDateString(),
            'time_slot' => '10:00 AM', 'amount' => 100,
            'payment_method' => PaymentMethodCatalog::METHOD_CASH_COUNTER,
            'payment_status' => PaymentMethodCatalog::STATUS_PAID,
            'payment_amount' => 100, 'cancelled_at' => now(),
        ]);
        app(BookingRefundService::class)->processRefund($booking);

        return $booking->fresh();
    }

    private function payload(string $method = 'cash'): array
    {
        return ['method' => $method, 'recipient' => 'Customer account', 'transfer_reference' => 'EXTERNAL-123',
            'expected_amount' => 100, 'confirmed' => true, 'evidence' => UploadedFile::fake()->createWithContent('acknowledgment.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF")];
    }

    public function test_staff_confirmation_is_encrypted_private_authoritative_and_idempotent(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_RECEPTIONIST] as $role) {
            $booking = $this->booking();
            $staff = User::factory()->create(['role' => $role]);
            $payload = $this->payload('gcash');
            $payload['amount'] = 9999;
            $this->actingAs($staff)->patchJson(route('appointments.refund.complete', $booking), $payload)->assertOk();
            $record = RefundConfirmation::where('spa_booking_id', $booking->id)->firstOrFail();
            $this->assertSame(100.0, (float) $record->amount);
            $this->assertSame($staff->id, $record->confirmed_by);
            $this->assertSame('EXTERNAL-123', $record->transfer_reference);
            $this->assertNotSame($record->evidence, DB::table('refund_confirmations')->where('id', $record->id)->value('evidence'));
            $this->assertArrayNotHasKey('evidence', $record->toArray());
            $this->actingAs($staff)->get(route('refund.evidence', $record))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->actingAs($staff)->patchJson(route('appointments.refund.complete', $booking), $payload)->assertUnprocessable();
            $this->assertSame(1, RefundConfirmation::where('spa_booking_id', $booking->id)->count());
            $this->assertSame(100.0, (float) PaymentLedgerEntry::where('spa_booking_id', $booking->id)->where('entry_type', PaymentLedgerEntry::TYPE_REFUND)->sum('amount'));
            $customer = User::findOrFail($booking->user_id);
            $this->actingAs($customer)->get(route('refund.evidence', $record))->assertForbidden();
            $this->actingAs($customer)->getJson(route('refund.confirmations', $booking))->assertForbidden();
            $this->actingAs($customer)->patchJson(route('refund.dispute', $record), ['action' => 'report', 'note' => 'Claim'])->assertForbidden();
        }
    }

    public function test_missing_invalid_and_stale_evidence_cannot_change_accounting(): void
    {
        $booking = $this->booking();
        $staff = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $cases = [[], ['confirmed' => false], ['expected_amount' => 99], ['method' => 'other'],
            ['method' => 'gcash', 'transfer_reference' => ''], ['evidence' => UploadedFile::fake()->create('bad.txt', 1, 'text/plain')],
            ['evidence' => UploadedFile::fake()->create('large.pdf', 2049, 'application/pdf')]];
        foreach ($cases as $index => $changes) {
            $payload = $index === 0 ? [] : array_replace($this->payload(), $changes);
            $this->actingAs($staff)->patchJson(route('appointments.refund.complete', $booking), $payload)->assertUnprocessable();
            $this->assertDatabaseCount('refund_confirmations', 0);
            $this->assertSame(0, PaymentLedgerEntry::where('entry_type', PaymentLedgerEntry::TYPE_REFUND)->count());
            $this->assertSame(BookingRefundService::STATUS_PENDING, $booking->fresh()->refund_status);
        }
        $this->actingAs(User::findOrFail($booking->user_id))->patchJson(route('appointments.refund.complete', $booking), $this->payload())->assertForbidden();
    }

    public function test_dispute_blocks_further_refunds_until_reconciled_without_changing_sales(): void
    {
        $booking = $this->booking();
        $booking->refunds()->update(['amount' => 25]);
        $staff = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $this->actingAs($staff)->patchJson(route('appointments.refund.complete', $booking), array_replace($this->payload(), ['expected_amount' => 25]))->assertOk();
        $record = RefundConfirmation::firstOrFail();
        $this->assertNull($record->transfer_reference);
        $this->patchJson(route('refund.dispute', $record), ['action' => 'report', 'note' => 'Customer reports no receipt.', 'dispute_version' => $record->disputeVersion()])->assertOk();
        $this->patchJson(route('refund.dispute', $record), ['action' => 'report', 'note' => 'Duplicate'])->assertUnprocessable();
        try {
            app(BookingRefundService::class)->processRefund($booking->fresh());
            $this->fail('Open dispute must block another refund.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('refund', $exception->errors());
        }
        $this->assertSame(1, BookingRefund::count());
        $this->assertSame(25.0, (float) PaymentLedgerEntry::where('entry_type', PaymentLedgerEntry::TYPE_REFUND)->sum('amount'));
        $this->patchJson(route('refund.dispute', $record), ['action' => 'resolve', 'note' => 'Matched signed acknowledgment to cash register.', 'dispute_version' => $record->disputeVersion()])->assertOk();
        app(BookingRefundService::class)->processRefund($booking->fresh());
        $this->assertSame(2, BookingRefund::count());
        $this->assertSame(75.0, (float) BookingRefund::where('status', BookingRefundService::STATUS_PENDING)->sum('amount'));
        $this->assertNotNull($record->fresh()->resolved_at);
        $this->getJson(route('refund.confirmations', $booking))->assertOk()->assertJsonPath('0.disputed', false)->assertJsonMissingPath('0.evidence');
        $backup = app(BackupRecoveryService::class)->createPayload();
        $this->assertSame($record->getRawOriginal('evidence'), $backup['tables']['refund_confirmations'][0]['evidence']);
        $counts = app(BackupRecoveryService::class)->rehearseRestore($backup['tables']);
        $this->assertSame(1, $counts['refund_confirmations']);
        $this->assertSame(2, $counts['booking_refunds']);
    }

    public function test_migration_rollback_and_reapply_preserve_legacy_refunds(): void
    {
        $booking = $this->booking();
        $migration = require base_path('database/migrations/2026_10_05_120000_create_refund_confirmations_table.php');
        $migration->down();
        $this->assertSame(1, BookingRefund::where('spa_booking_id', $booking->id)->count());
        $migration->up();
        $this->assertDatabaseCount('refund_confirmations', 0);
        $this->assertSame(BookingRefundService::STATUS_PENDING, $booking->fresh()->refund_status);
        $this->assertNull(BookingRefund::firstOrFail()->refund_confirmation_id);
        $this->get(route('refund.confirmations', $booking))->assertRedirect(route('login'));
    }

    public function test_old_forms_cannot_resolve_a_reopened_dispute_even_with_identical_notes_and_time(): void
    {
        $this->freezeTime();
        foreach ([User::ROLE_ADMIN, User::ROLE_RECEPTIONIST] as $role) {
            $booking = $this->booking();
            $booking->refunds()->update(['amount' => 25]);
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->patchJson(route('appointments.refund.complete', $booking), array_replace($this->payload(), ['expected_amount' => 25]))->assertOk();
            $record = RefundConfirmation::where('spa_booking_id', $booking->id)->firstOrFail();
            $url = route('refund.dispute', $record);
            $report = ['action' => 'report', 'note' => 'Money not received.', 'dispute_version' => $record->disputeVersion()];
            $this->patchJson($url, $report)->assertOk();
            $resolution = ['action' => 'resolve', 'note' => 'Checked records.', 'dispute_version' => $record->disputeVersion()];
            $this->patchJson($url, $resolution)->assertOk();
            $this->patchJson($url, $report)->assertUnprocessable()->assertJsonValidationErrors('refund');
            $report['dispute_version'] = $record->disputeVersion();
            $this->patchJson($url, $report)->assertOk();
            $logs = DB::table('activity_logs')->where('subject_type', $record->getMorphClass())->where('subject_id', $record->id)->count();
            $this->patchJson($url, $resolution)->assertUnprocessable()->assertJsonValidationErrors('refund');
            $this->patchJson($url, ['action' => 'resolve', 'note' => 'Missing version'])->assertUnprocessable()->assertJsonValidationErrors('dispute_version');
            $this->assertNull($record->fresh()->resolved_at);
            $this->assertSame($logs, DB::table('activity_logs')->where('subject_type', $record->getMorphClass())->where('subject_id', $record->id)->count());
            $this->assertSame(25.0, (float) PaymentLedgerEntry::where('spa_booking_id', $booking->id)->where('entry_type', PaymentLedgerEntry::TYPE_REFUND)->sum('amount'));
            $this->getJson(route('refund.confirmations', $booking))->assertOk()->assertJsonPath('0.dispute_version', $record->disputeVersion())->assertJsonPath('0.disputed', true);
            try {
                app(BookingRefundService::class)->processRefund($booking->fresh());
                $this->fail('The newer complaint must still block further refunds.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('refund', $exception->errors());
            }
        }
    }

    public function test_ledger_failure_rolls_back_confirmation_and_refund_status(): void
    {
        $booking = $this->booking();
        $staff = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->mock(PaymentLedgerService::class, function ($mock): void {
            $mock->shouldReceive('recordRefund')->once()->andThrow(new \RuntimeException('Simulated ledger failure'));
        });
        try {
            app(BookingRefundService::class)->completeManualRefund($booking, null, $staff->id, $this->payload());
            $this->fail('Ledger failure must abort confirmation.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated ledger failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('refund_confirmations', 0);
        $this->assertSame(BookingRefundService::STATUS_PENDING, $booking->fresh()->refund_status);
        $this->assertSame(BookingRefundService::STATUS_PENDING, BookingRefund::firstOrFail()->status);
        $this->assertSame(0, PaymentLedgerEntry::where('entry_type', PaymentLedgerEntry::TYPE_REFUND)->count());
    }
}
