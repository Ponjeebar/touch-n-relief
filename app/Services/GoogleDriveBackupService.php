<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class GoogleDriveBackupService
{
    /** @return array{id: string, name: string|null, removed: int} */
    public function upload(string $filename, string $contents): array
    {
        $folderId = $this->requiredConfig('folder_id');
        if (preg_match('/^[A-Za-z0-9_-]+$/', $folderId) !== 1) {
            throw new InvalidArgumentException('The Google Drive backup folder ID is invalid.');
        }

        $accessToken = $this->accessToken();
        $boundary = 'touchnrelief_'.bin2hex(random_bytes(12));
        $metadata = json_encode([
            'name' => $filename,
            'parents' => [$folderId],
            'appProperties' => ['touchnrelief_backup' => 'true'],
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $body = '--'.$boundary."\r\n"
            ."Content-Type: application/json; charset=UTF-8\r\n\r\n"
            .$metadata."\r\n"
            .'--'.$boundary."\r\n"
            ."Content-Type: application/json; charset=UTF-8\r\n\r\n"
            .$contents."\r\n"
            .'--'.$boundary."--\r\n";

        $response = $this->driveRequest($accessToken)
            ->withBody($body, 'multipart/related; boundary='.$boundary)
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name');
        $response->throw();

        $fileId = $response->json('id');
        if (! is_string($fileId) || $fileId === '') {
            throw new RuntimeException('Google Drive did not return an uploaded file ID.');
        }

        return [
            'id' => $fileId,
            'name' => is_string($response->json('name')) ? $response->json('name') : null,
            'removed' => $this->removeExpiredBackups($accessToken, $folderId),
        ];
    }

    private function accessToken(): string
    {
        $response = Http::asForm()
            ->acceptJson()
            ->timeout(30)
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => $this->requiredConfig('client_id'),
                'client_secret' => $this->requiredConfig('client_secret'),
                'refresh_token' => $this->requiredConfig('refresh_token'),
                'grant_type' => 'refresh_token',
            ]);
        $response->throw();

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Google OAuth did not return an access token.');
        }

        return $token;
    }

    private function removeExpiredBackups(string $accessToken, string $folderId): int
    {
        $retentionDays = app(SiteSettingsService::class)->backupRetentionDays();
        $cutoff = CarbonImmutable::now()->subDays($retentionDays);
        $query = sprintf(
            "'%s' in parents and trashed = false and appProperties has { key='touchnrelief_backup' and value='true' }",
            $folderId,
        );
        $pageToken = null;
        $removed = 0;

        do {
            $parameters = [
                'q' => $query,
                'fields' => 'nextPageToken,files(id,name,createdTime)',
                'pageSize' => 1000,
            ];
            if ($pageToken !== null) {
                $parameters['pageToken'] = $pageToken;
            }

            $response = $this->driveRequest($accessToken)
                ->get('https://www.googleapis.com/drive/v3/files', $parameters);
            $response->throw();

            foreach ($response->json('files', []) as $file) {
                $id = $file['id'] ?? null;
                $createdAt = $file['createdTime'] ?? null;
                if (! is_string($id) || ! is_string($createdAt)) {
                    continue;
                }

                try {
                    $isExpired = CarbonImmutable::parse($createdAt)->lt($cutoff);
                } catch (\Throwable) {
                    continue;
                }

                if ($isExpired) {
                    $delete = $this->driveRequest($accessToken)
                        ->delete('https://www.googleapis.com/drive/v3/files/'.rawurlencode($id));
                    $delete->throw();
                    $removed++;
                }
            }

            $nextPageToken = $response->json('nextPageToken');
            $pageToken = is_string($nextPageToken) && $nextPageToken !== '' ? $nextPageToken : null;
        } while ($pageToken !== null);

        return $removed;
    }

    private function driveRequest(string $accessToken): PendingRequest
    {
        return Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(60);
    }

    private function requiredConfig(string $key): string
    {
        $value = trim((string) config('services.google_drive_backup.'.$key));
        if ($value === '') {
            throw new InvalidArgumentException('Google Drive backup configuration is incomplete: '.$key.'.');
        }

        return $value;
    }
}
