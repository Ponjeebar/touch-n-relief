<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RefundNormalizationMigrationTest extends TestCase
{
    private ?string $isolatedMysqlDatabase = null;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.connections.sqlite');
        $connection['database'] = ':memory:';
        $connection['url'] = null;
        if (getenv('RUN_MYSQL_MIGRATION_TESTS') === '1') {
            $connection = config('database.connections.mysql');
            $connection['url'] = null;
            $connection['database'] = null;
            config()->set('database.connections.migration_admin', $connection);
            $this->isolatedMysqlDatabase = 'tnr_migration_test_'.bin2hex(random_bytes(6));
            DB::connection('migration_admin')->statement('CREATE DATABASE `'.$this->isolatedMysqlDatabase.'`');
            $connection['database'] = $this->isolatedMysqlDatabase;
        }
        config()->set('database.connections.migration_fixture', $connection);
        config()->set('database.default', 'migration_fixture');
        DB::purge('migration_fixture');

        Schema::create('users', fn (Blueprint $table) => $table->id());
        Schema::create('spa_bookings', function (Blueprint $table): void {
            $table->id();
            $table->string('refund_status')->nullable();
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->string('refund_reference')->nullable();
            $table->string('refund_note')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_transaction_id')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });
        Schema::create('payment_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spa_booking_id')->constrained()->cascadeOnDelete();
            $table->string('entry_type', 30);
            $table->decimal('amount', 10, 2);
            $table->unique(['spa_booking_id', 'entry_type']);
        });
        foreach ([1, 2] as $id) {
            DB::table('spa_bookings')->insert([
                'id' => $id, 'refund_status' => 'processed', 'refund_amount' => 25,
                'refund_reference' => 'ref_shared_legacy', 'payment_method' => 'paymongo',
                'payment_transaction_id' => 'pay_'.$id, 'refunded_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('payment_ledger_entries')->insert([
                'spa_booking_id' => $id, 'entry_type' => 'refund', 'amount' => 25,
            ]);
        }
    }

    protected function tearDown(): void
    {
        DB::purge('migration_fixture');
        if ($this->isolatedMysqlDatabase !== null) {
            // Only the randomly named database allocated by this test is removed.
            DB::connection('migration_admin')->statement('DROP DATABASE `'.$this->isolatedMysqlDatabase.'`');
            DB::purge('migration_admin');
        }
        parent::tearDown();
    }

    private function migrateRefunds(): void
    {
        $migration = require database_path('migrations/2026_10_05_000000_normalize_booking_refunds_and_ledger_idempotency.php');
        $migration->up();
    }

    public function test_fresh_migration_preserves_refunds_and_supports_multiple_refund_entries(): void
    {
        $this->migrateRefunds();

        $this->assertSame(2, DB::table('booking_refunds')->count());
        $this->assertEquals(50, DB::table('booking_refunds')->sum('amount'));
        $this->assertFalse(Schema::hasIndex('payment_ledger_entries', 'payment_ledger_entries_spa_booking_id_entry_type_unique'));
        $this->assertTrue(Schema::hasIndex('payment_ledger_entries', 'payment_ledger_entries_spa_booking_id_index'));
        $this->assertSame(['ref_shared_legacy', 'ref_shared_legacy-B2'], DB::table('booking_refunds')->orderBy('id')->pluck('reference')->all());
        DB::table('payment_ledger_entries')->insert([
            'spa_booking_id' => 1, 'entry_type' => 'refund', 'amount' => 10,
            'idempotency_key' => 'booking:1:refund:new',
        ]);
        $this->assertSame(3, DB::table('payment_ledger_entries')->count());
        DB::table('spa_bookings')->where('id', 1)->delete();
        $this->assertSame(1, DB::table('payment_ledger_entries')->count());
        $this->assertSame(1, DB::table('booking_refunds')->count());
    }

    public function test_retry_after_old_index_drop_failure_does_not_duplicate_backfill(): void
    {
        $this->migrateRefunds();
        // Reconstruct the schema left by the reported failure: backfill done, old unique retained.
        Schema::table('payment_ledger_entries', function (Blueprint $table): void {
            $table->unique(['spa_booking_id', 'entry_type']);
            $table->index('booking_refund_id', 'migration_fixture_refund_index');
        });
        Schema::table('payment_ledger_entries', function (Blueprint $table): void {
            $table->dropIndex('payment_ledger_entries_spa_booking_id_index');
            $table->dropUnique('payment_ledger_entries_idempotency_key_unique');
            $table->dropUnique('payment_ledger_entries_booking_refund_id_unique');
        });
        $refunds = DB::table('booking_refunds')->orderBy('id')->get()->toJson();
        $ledger = DB::table('payment_ledger_entries')->orderBy('id')->get()->toJson();

        $this->migrateRefunds();
        $this->migrateRefunds();

        $this->assertSame($refunds, DB::table('booking_refunds')->orderBy('id')->get()->toJson());
        $this->assertSame($ledger, DB::table('payment_ledger_entries')->orderBy('id')->get()->toJson());
        $this->assertFalse(Schema::hasIndex('payment_ledger_entries', 'payment_ledger_entries_spa_booking_id_entry_type_unique'));
        $this->assertTrue(Schema::hasIndex('payment_ledger_entries', 'payment_ledger_entries_idempotency_key_unique'));
        $this->assertTrue(Schema::hasIndex('payment_ledger_entries', 'payment_ledger_entries_booking_refund_id_unique'));
    }

    public function test_mysql_reproduces_the_original_foreign_key_index_error(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requires RUN_MYSQL_MIGRATION_TESTS=1 for an isolated MySQL database.');
        }

        try {
            Schema::table('payment_ledger_entries', function (Blueprint $table): void {
                $table->dropUnique('payment_ledger_entries_spa_booking_id_entry_type_unique');
            });
            $this->fail('Expected MySQL to reject removal of the foreign key supporting index.');
        } catch (QueryException $exception) {
            $this->assertSame(1553, (int) $exception->errorInfo[1]);
        }

        $this->migrateRefunds();
        $this->assertSame(2, DB::table('booking_refunds')->count());
    }
}
