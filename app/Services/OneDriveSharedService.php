<?php

namespace App\Services;

use App\Traits\LogsDebugSteps;
use Illuminate\Support\Facades\Http;

/**
 * App-only (client_credentials) Microsoft OneDrive access via the Graph API — same
 * auth model as OutlookMailerService, reusing the same Azure app registration and
 * credentials. There's no per-user login: every upload lands in an "Infiltration
 * Report" folder (created automatically if missing) in one fixed account's OneDrive,
 * configured via services.msgraph.onedrive_target_user.
 *
 * This account must actually have an active OneDrive (i.e. be licensed for it) —
 * a mailbox-only account (e.g. a no-reply address with no OneDrive provisioned)
 * won't work. Requires the Microsoft Graph Application permission
 * Files.ReadWrite.All, admin-consented once in Azure — no redirect URI needed,
 * since this never sends anyone through a login screen.
 */
class OneDriveSharedService
{
    use LogsDebugSteps;

    protected $clientId;
    protected $clientSecret;
    protected $tenantId;
    protected $tokenUrl;
    protected $targetUser;
    protected $logFile;

    public function __construct()
    {
        $this->clientId     = config('services.msgraph.client_id');
        $this->clientSecret = config('services.msgraph.client_secret');
        $this->tenantId     = config('services.msgraph.tenant_id');
        $this->tokenUrl     = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";
        $this->targetUser   = config('services.msgraph.onedrive_target_user');
        $this->logFile      = public_path('infiltration_runs/onedrive_debug.log');
    }

    public function getAccessToken(): string
    {
        $response = Http::asForm()->post($this->tokenUrl, [
            'client_id'     => $this->clientId,
            'scope'         => 'https://graph.microsoft.com/.default',
            'client_secret' => $this->clientSecret,
            'grant_type'    => 'client_credentials',
        ]);

        if ($response->successful()) {
            $this->step($this->logFile, 'OneDriveSharedService', 'access token acquired');
            return $response->json('access_token');
        }

        $this->step($this->logFile, 'OneDriveSharedService', 'access token FAILED', ['body' => $response->body()]);
        throw new \RuntimeException('Failed to acquire OneDrive access token: ' . $response->body());
    }

    /**
     * Finds the folder named $folderName in the target account's OneDrive root,
     * creating it there if it doesn't exist yet.
     */
    private function findOrCreateFolder(string $accessToken, string $folderName): array
    {
        $base        = "https://graph.microsoft.com/v1.0/users/{$this->targetUser}/drive/root";
        $encodedName = rawurlencode($folderName);

        $getResponse = Http::withToken($accessToken)->get("{$base}:/{$encodedName}");
        if ($getResponse->successful()) {
            $this->step($this->logFile, 'OneDriveSharedService', 'folder found', [
                'targetUser' => $this->targetUser,
                'itemId'     => $getResponse->json('id'),
                'webUrl'     => $getResponse->json('webUrl'),
            ]);
            return [
                'driveId' => $getResponse->json('parentReference.driveId'),
                'itemId'  => $getResponse->json('id'),
            ];
        }

        if ($getResponse->status() !== 404) {
            $this->step($this->logFile, 'OneDriveSharedService', 'folder check FAILED', ['body' => $getResponse->body()]);
            throw new \RuntimeException('Failed to check for OneDrive folder: ' . $getResponse->body());
        }

        $createResponse = Http::withToken($accessToken)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("{$base}/children", [
                'name'                               => $folderName,
                'folder'                             => new \stdClass(),
                '@microsoft.graph.conflictBehavior'  => 'fail',
            ]);

        if (!$createResponse->successful()) {
            $this->step($this->logFile, 'OneDriveSharedService', 'folder create FAILED', ['body' => $createResponse->body()]);
            throw new \RuntimeException('Failed to create OneDrive folder: ' . $createResponse->body());
        }

        $this->step($this->logFile, 'OneDriveSharedService', 'folder created', [
            'targetUser' => $this->targetUser,
            'itemId'     => $createResponse->json('id'),
            'webUrl'     => $createResponse->json('webUrl'),
        ]);

        return [
            'driveId' => $createResponse->json('parentReference.driveId'),
            'itemId'  => $createResponse->json('id'),
        ];
    }

    /**
     * Uploads $localPath into the "Infiltration Report" folder as $fileName. Uses a
     * resumable upload session (same mechanics as the large-attachment path in
     * OutlookMailerService) since report zips can exceed the ~4MB simple-upload limit.
     *
     * @return array{id: ?string, webUrl: ?string, size: int} webUrl is a direct link
     *         to the uploaded file in OneDrive — the definitive way to confirm it
     *         actually landed, rather than just trusting a "success" log line.
     */
    public function uploadZip(string $localPath, string $fileName): array
    {
        if (!$this->targetUser) {
            $this->step($this->logFile, 'OneDriveSharedService', 'upload FAILED - no target user configured');
            throw new \RuntimeException(
                'services.msgraph.onedrive_target_user is not configured — set MS_GRAPH_ONEDRIVE_USER '
                . 'in .env to an account email that has an active OneDrive.'
            );
        }

        $this->step($this->logFile, 'OneDriveSharedService', 'upload starting', [
            'targetUser' => $this->targetUser,
            'fileName'   => $fileName,
            'localPath'  => $localPath,
            'size'       => file_exists($localPath) ? filesize($localPath) : null,
        ]);

        $accessToken = $this->getAccessToken();
        $folder      = $this->findOrCreateFolder($accessToken, 'Infiltration Report');

        $fileSize   = filesize($localPath);
        $sessionUrl = "https://graph.microsoft.com/v1.0/drives/{$folder['driveId']}/items/{$folder['itemId']}:/{$fileName}:/createUploadSession";

        $sessionResponse = Http::withToken($accessToken)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($sessionUrl, ['item' => ['@microsoft.graph.conflictBehavior' => 'rename']]);

        if (!$sessionResponse->successful()) {
            $this->step($this->logFile, 'OneDriveSharedService', 'upload session create FAILED', ['body' => $sessionResponse->body()]);
            throw new \RuntimeException('Failed to create OneDrive upload session: ' . $sessionResponse->body());
        }
        $this->step($this->logFile, 'OneDriveSharedService', 'upload session created');

        $uploadUrl = $sessionResponse->json('uploadUrl');
        $chunkSize = 4 * 1024 * 1024;
        $handle    = fopen($localPath, 'rb');
        $offset    = 0;
        $lastBody  = null;

        while (!feof($handle)) {
            $chunk    = fread($handle, $chunkSize);
            $chunkLen = strlen($chunk);
            $rangeEnd = $offset + $chunkLen - 1;

            $uploadResponse = Http::withHeaders([
                'Content-Range'  => "bytes {$offset}-{$rangeEnd}/{$fileSize}",
                'Content-Length' => $chunkLen,
            ])->timeout(120)->withBody($chunk, 'application/octet-stream')->put($uploadUrl);

            if (!in_array($uploadResponse->status(), [200, 201, 202])) {
                fclose($handle);
                $this->step($this->logFile, 'OneDriveSharedService', 'chunk upload FAILED', [
                    'range' => "{$offset}-{$rangeEnd}/{$fileSize}", 'body' => $uploadResponse->body(),
                ]);
                throw new \RuntimeException('OneDrive chunk upload failed: ' . $uploadResponse->body());
            }
            $lastBody = $uploadResponse->json();
            $offset  += $chunkLen;
        }
        fclose($handle);

        $result = [
            'id'     => $lastBody['id'] ?? null,
            'webUrl' => $lastBody['webUrl'] ?? null,
            'size'   => $fileSize,
        ];

        $this->step($this->logFile, 'OneDriveSharedService', 'upload complete', ['fileName' => $fileName] + $result);

        return $result;
    }
}
