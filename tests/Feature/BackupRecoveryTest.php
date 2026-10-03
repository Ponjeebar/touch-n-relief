<?php

namespace Tests\Feature;

use App\Services\BackupRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BackupRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_backup_uses_the_verified_recovery_format(): void
    {
        $json = $this->app->make(BackupRecoveryService::class)->createJson();
        $path = tempnam(sys_get_temp_dir(), 'tnr-generated-backup-');
        file_put_contents($path, $json);

        try {
            $backup = $this->app->make(BackupRecoveryService::class)->validateFile($path);

            $this->assertTrue($backup['integrity_verified']);
            $this->assertSame(2, $backup['format_version']);
            $this->assertArrayHasKey('users', $backup['tables']);
            $this->assertArrayHasKey('payment_ledger_entries', $backup['tables']);
        } finally {
            @unlink($path);
        }
    }

    public function test_valid_backup_can_be_rehearsed_without_changing_application_data(): void
    {
        $before = $this->app['db']->table('users')->count();
        $path = $this->writeBackup([
            'users' => [[
                'id' => 9001,
                'name' => 'Recovery Test User',
                'email' => 'recovery@example.test',
                'password' => bcrypt('RecoveryPassword9'),
                'role' => 'user',
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]],
        ]);

        try {
            $exitCode = Artisan::call('backup:verify', [
                'file' => $path,
                '--restore-dry-run' => true,
            ]);

            $this->assertSame(0, $exitCode, Artisan::output());
            $this->assertSame($before, $this->app['db']->table('users')->count());
        } finally {
            @unlink($path);
        }
    }

    public function test_modified_backup_fails_integrity_validation(): void
    {
        $path = $this->writeBackup(['users' => []]);
        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $payload['tables']['users'][] = ['id' => 123];
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        try {
            $exitCode = Artisan::call('backup:verify', ['file' => $path]);

            $this->assertSame(1, $exitCode);
            $this->assertStringContainsString('recorded count for users does not match', Artisan::output());
        } finally {
            @unlink($path);
        }
    }

    public function test_legacy_backup_can_be_rehearsed_with_a_checksum_warning(): void
    {
        $path = $this->writeBackup(['users' => []], 1);

        try {
            $exitCode = Artisan::call('backup:verify', [
                'file' => $path,
                '--restore-dry-run' => true,
            ]);

            $this->assertSame(0, $exitCode, Artisan::output());
        } finally {
            @unlink($path);
        }
    }

    /** @param array<string, list<array<string, mixed>>> $tables */
    private function writeBackup(array $tables, int $formatVersion = 2): string
    {
        $encodedTables = json_encode($tables, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $payload = [
            'application' => 'TOUCHnRELIEF',
            'generated_at' => now()->toIso8601String(),
            'format_version' => $formatVersion,
            'contains_sensitive_data' => true,
            'tables' => $tables,
        ];
        if ($formatVersion === 2) {
            $payload['record_counts'] = collect($tables)->map(fn (array $rows): int => count($rows))->all();
            $payload['tables_sha256'] = hash('sha256', $encodedTables);
        }
        $path = tempnam(sys_get_temp_dir(), 'tnr-backup-');
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $path;
    }
}
