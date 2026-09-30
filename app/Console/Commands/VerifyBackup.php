<?php

namespace App\Console\Commands;

use App\Services\BackupRecoveryService;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

class VerifyBackup extends Command
{
    protected $signature = 'backup:verify {file : Path to a TouchNRelief JSON backup} {--restore-dry-run : Rehearse restoration in a disposable SQLite database}';

    protected $description = 'Validate a TouchNRelief backup without changing application data';

    public function handle(BackupRecoveryService $recovery): int
    {
        $path = (string) $this->argument('file');
        $isAbsolute = preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2}|\/)/', $path) === 1;
        if (! $isAbsolute) {
            $path = $this->laravel->basePath($path);
        }

        try {
            $backup = $recovery->validateFile($path);
            if ($backup['integrity_verified']) {
                $this->info('Backup integrity verified.');
            } else {
                $this->warn('Legacy version 1 backup: structure is valid, but the original file has no integrity checksum.');
            }
            $this->line('Generated: '.$backup['generated_at']);
            $this->line('Tables: '.count($backup['tables']));
            $this->line('Records: '.array_sum($backup['record_counts']));

            if ($this->option('restore-dry-run')) {
                $recovery->rehearseRestore($backup['tables']);
                $this->info('Isolated restore rehearsal passed. No application data was changed.');
            }

            return self::SUCCESS;
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
