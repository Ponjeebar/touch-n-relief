<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;

class BackupRecoveryService
{
    /** @var list<string> */
    public const TABLES = [
        'users',
        'customers',
        'receptionists',
        'therapists',
        'spa_services',
        'membership_plans',
        'membership_purchases',
        'time_slots',
        'service_time_slots',
        'store_closures',
        'service_slot_date_overrides',
        'spa_bookings',
        'payment_ledger_entries',
        'transactions',
        'registrations',
        'social_accounts',
        'user_password_histories',
        'customer_notifications',
        'staff_notifications',
        'activity_logs',
        'site_settings',
    ];

    /**
     * @return array{format_version: int, generated_at: string, tables_sha256: string, integrity_verified: bool, tables: array<string, list<array<string, mixed>>>, record_counts: array<string, int>}
     */
    public function validateFile(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException('The backup file cannot be read.');
        }

        try {
            $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidArgumentException('The backup is not valid JSON.', previous: $exception);
        }

        if (! is_array($payload)
            || ($payload['application'] ?? null) !== 'TOUCHnRELIEF'
            || ! in_array($payload['format_version'] ?? null, [1, 2], true)
            || ! is_string($payload['generated_at'] ?? null)
            || ! is_array($payload['tables'] ?? null)) {
            throw new InvalidArgumentException('The file is not a supported TouchNRelief backup.');
        }

        $formatVersion = (int) $payload['format_version'];
        $tables = $payload['tables'];
        $unknownTables = array_diff(array_keys($tables), self::TABLES);
        if ($unknownTables !== []) {
            throw new InvalidArgumentException('The backup contains unsupported tables: '.implode(', ', $unknownTables).'.');
        }

        foreach ($tables as $table => $rows) {
            if (! is_array($rows) || ! array_is_list($rows)) {
                throw new InvalidArgumentException("The {$table} table data is malformed.");
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    throw new InvalidArgumentException("The {$table} table contains a malformed record.");
                }
            }

            if ($formatVersion === 2 && ($payload['record_counts'][$table] ?? null) !== count($rows)) {
                throw new InvalidArgumentException("The recorded count for {$table} does not match its data.");
            }
        }

        $encodedTables = json_encode($tables, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $actualHash = hash('sha256', $encodedTables);
        $recordCounts = collect($tables)->map(fn (array $rows): int => count($rows))->all();
        if ($formatVersion === 2) {
            if (! is_array($payload['record_counts'] ?? null) || ! is_string($payload['tables_sha256'] ?? null)) {
                throw new InvalidArgumentException('The version 2 backup is missing integrity metadata.');
            }
            if (array_diff(array_keys($payload['record_counts']), array_keys($tables)) !== []) {
                throw new InvalidArgumentException('The backup record counts contain tables that are not present.');
            }
            if (! hash_equals($payload['tables_sha256'], $actualHash)) {
                throw new InvalidArgumentException('The backup integrity check failed. The file may be incomplete or modified.');
            }
        }

        return [
            'format_version' => $formatVersion,
            'generated_at' => $payload['generated_at'],
            'tables_sha256' => $actualHash,
            'integrity_verified' => $formatVersion === 2,
            'tables' => $tables,
            'record_counts' => $recordCounts,
        ];
    }

    /**
     * Restore into a disposable SQLite database and return verified row counts.
     *
     * @param  array<string, list<array<string, mixed>>>  $tables
     * @return array<string, int>
     */
    public function rehearseRestore(array $tables): array
    {
        $directory = storage_path('app/recovery-tests');
        File::ensureDirectoryExists($directory);
        $databasePath = $directory.'/restore-'.bin2hex(random_bytes(8)).'.sqlite';
        touch($databasePath);

        $connection = 'recovery_rehearsal';
        config(["database.connections.{$connection}" => [
            'driver' => 'sqlite',
            'database' => $databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
        ]]);

        try {
            $exitCode = Artisan::call('migrate', [
                '--database' => $connection,
                '--force' => true,
            ]);
            if ($exitCode !== 0) {
                throw new RuntimeException('The isolated recovery database could not be prepared.');
            }

            $database = DB::connection($connection);
            $database->statement('PRAGMA foreign_keys = OFF');
            foreach (array_reverse(self::TABLES) as $table) {
                $database->table($table)->delete();
            }
            $database->statement('PRAGMA foreign_keys = ON');
            $database->transaction(function () use ($database, $tables): void {
                foreach (self::TABLES as $table) {
                    $rows = $tables[$table] ?? [];
                    foreach (array_chunk($rows, 250) as $chunk) {
                        $database->table($table)->insert($chunk);
                    }
                }
            });

            $restoredCounts = [];
            foreach ($tables as $table => $rows) {
                $restoredCounts[$table] = $database->table($table)->count();
                if ($restoredCounts[$table] !== count($rows)) {
                    throw new RuntimeException("The restore count for {$table} does not match the backup.");
                }
            }

            return $restoredCounts;
        } finally {
            DB::purge($connection);
            config()->offsetUnset("database.connections.{$connection}");
            File::delete($databasePath);
        }
    }
}
