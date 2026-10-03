<?php

namespace App\Console\Commands;

use App\Services\BackupRecoveryService;
use App\Services\GoogleDriveBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackupToGoogleDrive extends Command
{
    protected $signature = 'backup:google-drive';

    protected $description = 'Create a verified TouchNRelief backup and upload it to Google Drive';

    public function handle(BackupRecoveryService $recovery, GoogleDriveBackupService $drive): int
    {
        if (! config('services.google_drive_backup.enabled', false)) {
            $this->components->info('Google Drive backups are disabled.');

            return self::SUCCESS;
        }

        try {
            $filename = $recovery->filename();
            $result = $drive->upload($filename, $recovery->createJson());

            Log::info('TouchNRelief backup uploaded to Google Drive.', [
                'filename' => $filename,
                'google_drive_file_id' => $result['id'],
                'expired_backups_removed' => $result['removed'],
            ]);
            $this->components->info('Backup uploaded to Google Drive: '.$filename);
            if ($result['removed'] > 0) {
                $this->line('Removed '.$result['removed'].' expired automatic backup(s).');
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            Log::error('TouchNRelief Google Drive backup failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            $this->components->error('Google Drive backup failed. Check the application logs for details.');

            return self::FAILURE;
        }
    }
}
