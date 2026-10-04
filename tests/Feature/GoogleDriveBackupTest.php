<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Services\SiteSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleDriveBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_command_uploads_verified_backup_and_removes_only_expired_automatic_backups(): void
    {
        CarbonImmutable::setTestNow('2026-10-03 02:30:00 Asia/Manila');
        config([
            'services.google_drive_backup.enabled' => true,
            'services.google_drive_backup.client_id' => 'backup-client-id',
            'services.google_drive_backup.client_secret' => 'backup-client-secret',
            'services.google_drive_backup.refresh_token' => 'backup-refresh-token',
            'services.google_drive_backup.folder_id' => 'drive-folder-123',
            'services.google_drive_backup.retention_days' => 14,
        ]);
        SiteSetting::put(SiteSettingsService::KEY_BACKUP_RETENTION_DAYS, '2');

        Http::fake(function (Request $request) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                $this->assertSame('POST', $request->method());
                $this->assertSame('backup-refresh-token', $request['refresh_token']);

                return Http::response(['access_token' => 'temporary-access-token']);
            }

            if (str_starts_with($request->url(), 'https://www.googleapis.com/upload/drive/v3/files')) {
                $this->assertSame('Bearer temporary-access-token', $request->header('Authorization')[0] ?? null);
                $this->assertStringContainsString('touchnrelief-data-backup-2026-10-03-023000.json', $request->body());
                $this->assertStringContainsString('"tables_sha256"', $request->body());
                $this->assertStringContainsString('drive-folder-123', $request->body());

                return Http::response([
                    'id' => 'new-backup-id',
                    'name' => 'touchnrelief-data-backup-2026-10-03-023000.json',
                ]);
            }

            if ($request->method() === 'GET' && str_starts_with($request->url(), 'https://www.googleapis.com/drive/v3/files?')) {
                return Http::response(['files' => [
                    ['id' => 'expired-backup-id', 'createdTime' => '2026-09-30T00:00:00Z'],
                    ['id' => 'recent-backup-id', 'createdTime' => '2026-10-02T00:00:00Z'],
                ]]);
            }

            if ($request->method() === 'DELETE' && $request->url() === 'https://www.googleapis.com/drive/v3/files/expired-backup-id') {
                return Http::response(null, 204);
            }

            return Http::response(['error' => 'Unexpected test request'], 500);
        });

        $exitCode = Artisan::call('backup:google-drive');

        $this->assertSame(0, $exitCode, Artisan::output());
        Http::assertSentCount(4);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://www.googleapis.com/drive/v3/files/expired-backup-id');
        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://www.googleapis.com/drive/v3/files/recent-backup-id');
    }

    public function test_disabled_backup_command_exits_without_contacting_google(): void
    {
        config(['services.google_drive_backup.enabled' => false]);
        Http::preventStrayRequests();

        $exitCode = Artisan::call('backup:google-drive');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Google Drive backups are disabled', Artisan::output());
        Http::assertNothingSent();
    }

    public function test_google_error_fails_safely_without_exposing_credentials(): void
    {
        config([
            'services.google_drive_backup.enabled' => true,
            'services.google_drive_backup.client_id' => 'backup-client-id',
            'services.google_drive_backup.client_secret' => 'do-not-display-this-secret',
            'services.google_drive_backup.refresh_token' => 'do-not-display-this-token',
            'services.google_drive_backup.folder_id' => 'drive-folder-123',
        ]);
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $exitCode = Artisan::call('backup:google-drive');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Google Drive backup failed', $output);
        $this->assertStringNotContainsString('do-not-display-this-secret', $output);
        $this->assertStringNotContainsString('do-not-display-this-token', $output);
    }
}
