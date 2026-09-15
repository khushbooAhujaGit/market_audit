<?php

namespace App\Services;

use App\Models\OneDriveToken;
use App\Models\User;
use App\Traits\LogsDebugSteps;
use Illuminate\Support\Facades\Http;

/**
 * Delegated (per-user) Microsoft OneDrive access via the Graph API — separate from
 * OutlookMailerService, which uses app-only (client_credentials) auth for a single
 * fixed mailbox, and separate from OneDriveSharedService, which uses that same
 * app-only model for a single shared OneDrive. This flow is authorization_code +
 * refresh_token: each user signs into Microsoft directly (we never see their
 * password) and grants Files.ReadWrite.All, and we store only the resulting tokens.
 *
 * Uploads land in a folder named "Infiltration Report" — either in the root of the
 * signed-in user's own OneDrive (created automatically if it doesn't exist), or,
 * if a folder with that name has been shared with them by someone else, inside
 * that shared folder instead (see findOrCreateFolder()).
 *
 * Scope is Files.ReadWrite.All rather than the narrower Files.ReadWrite: writing
 * into an item genuinely owned by someone else (a shared folder, not the user's
 * own drive) requires the broader delegated scope in practice — Files.ReadWrite
 * alone reliably covers the user's own drive but was observed returning
 * accessDenied on write attempts into a shared folder even with explicit "can
 * edit" access granted directly (not via a link) by the owner.
 */
class OneDrivePersonalService
{
    use LogsDebugSteps;

    protected $clientId;
    protected $clientSecret;
    protected $tenantId;
    protected $scope = 'Files.ReadWrite.All offline_access';
    protected $logFile;
    protected $sharedFolderUrl;

    public function __construct()
    {
        $this->clientId        = config('services.msgraph.client_id');
        $this->clientSecret    = config('services.msgraph.client_secret');
        $this->tenantId        = config('services.msgraph.tenant_id');
        $this->logFile         = public_path('infiltration_runs/onedrive_debug.log');
        $this->sharedFolderUrl = config('services.msgraph.infiltration_shared_folder_url');
    }

    public function isConnected(User $user): bool
    {
        return OneDriveToken::where('user_id', $user->id)->exists();
    }

    public function disconnect(User $user): void
    {
        OneDriveToken::where('user_id', $user->id)->delete();
    }

    public function getAuthorizeUrl(string $state): string
    {
        $params = [
            'client_id'     => $this->clientId,
            'response_type' => 'code',
            'redirect_uri'  => route('onedrive.callback'),
            'response_mode' => 'query',
            'scope'         => $this->scope,
            'state'         => $state,
        ];
        return "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/authorize?" . http_build_query($params);
    }

    public function handleCallback(string $code, User $user): void
    {
        $response = Http::asForm()->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => route('onedrive.callback'),
            'scope'         => $this->scope,
        ]);

        if (!$response->successful()) {
            $this->step($this->logFile, 'OneDrivePersonalService', 'token exchange FAILED', ['user_id' => $user->id, 'body' => $response->body()]);
            throw new \RuntimeException('Failed to connect OneDrive: ' . $response->body());
        }

        $data = $response->json();
        OneDriveToken::updateOrCreate(
            ['user_id' => $user->id],
            [
                'access_token'  => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_at'    => now()->addSeconds(($data['expires_in'] ?? 3600) - 60),
            ]
        );
        $this->step($this->logFile, 'OneDrivePersonalService', 'connected', [
            'user_id'      => $user->id,
            'grantedScope' => $this->decodeTokenScope($data['access_token']),
        ]);
    }

    /**
     * Returns a valid access token for this user, refreshing it first if expired.
     * Returns null if the user has never connected, or if the refresh token itself
     * has been revoked/expired (they'll need to reconnect via getAuthorizeUrl()).
     */
    public function getValidAccessToken(User $user): ?string
    {
        $token = OneDriveToken::where('user_id', $user->id)->first();
        if (!$token) {
            return null;
        }

        if ($token->expires_at->isFuture()) {
            return $token->access_token;
        }

        $response = Http::asForm()->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type'    => 'refresh_token',
            'refresh_token' => $token->refresh_token,
            'scope'         => $this->scope,
        ]);

        if (!$response->successful()) {
            $this->step($this->logFile, 'OneDrivePersonalService', 'token refresh FAILED - user needs to reconnect', [
                'user_id' => $user->id, 'body' => $response->body(),
            ]);
            return null;
        }

        $data = $response->json();
        $token->update([
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $token->refresh_token,
            'expires_at'    => now()->addSeconds(($data['expires_in'] ?? 3600) - 60),
        ]);

        return $token->access_token;
    }

    /**
     * Decodes the `scp` (scope) claim out of an access token's JWT payload, purely
     * for diagnostic logging — does not verify the signature, since we're only
     * reading a claim from a token we just legitimately received from Microsoft,
     * not trusting it for auth decisions.
     */
    private function decodeTokenScope(string $accessToken): ?string
    {
        $parts = explode('.', $accessToken);
        if (count($parts) < 2) {
            return null;
        }
        $payload = strtr($parts[1], '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $decoded = base64_decode($payload);
        if ($decoded === false) {
            return null;
        }
        $data = json_decode($decoded, true);
        return $data['scp'] ?? null;
    }

    /**
     * Resolves $this->sharedFolderUrl (a plain OneDrive/SharePoint sharing link)
     * directly to its driveId/itemId via Graph's /shares/{id}/driveItem — the same
     * technique used elsewhere in this codebase for app-only OneDrive downloads.
     * This exists because the specific "Infiltration Report" folder shared by
     * Shivam Pandey does NOT reliably appear via /me/drive/root/children or
     * /me/drive/sharedWithMe (confirmed empirically: absent from both listings
     * even though it's visible in the OneDrive web UI under "My files" as a
     * shortcut) — a known Graph API gap for shortcut-added shared items, not a
     * bug in the listing/matching logic. Resolving the sharing link directly
     * sidesteps that gap entirely. Returns null if no URL is configured, or if
     * the link fails to resolve (revoked, expired, or not a folder).
     */
    private function resolveSharedFolderByUrl(string $accessToken): ?array
    {
        if (empty($this->sharedFolderUrl)) {
            return null;
        }

        // Graph's documented scheme for resolving a share link: base64-encode the
        // URL, then convert to unpadded base64url and prefix with "u!".
        $base64  = base64_encode($this->sharedFolderUrl);
        $urlSafe = rtrim(strtr($base64, '+/', '-_'), '=');
        $shareId = 'u!' . $urlSafe;

        $response = Http::withToken($accessToken)
            ->get("https://graph.microsoft.com/v1.0/shares/{$shareId}/driveItem", [
                '$select' => 'id,name,folder,parentReference',
            ]);

        if (!$response->successful()) {
            $this->step($this->logFile, 'OneDrivePersonalService', 'shared folder URL resolve FAILED', [
                'status' => $response->status(), 'body' => $response->body(),
            ]);
            return null;
        }

        $item = $response->json();
        if (!isset($item['folder'])) {
            $this->step($this->logFile, 'OneDrivePersonalService', 'shared folder URL did not resolve to a folder', [
                'name' => $item['name'] ?? null,
            ]);
            return null;
        }

        $result = [
            'driveId' => $item['parentReference']['driveId'] ?? null,
            'itemId'  => $item['id'],
        ];

        $this->step($this->logFile, 'OneDrivePersonalService', 'shared folder resolved via direct URL', [
            'name' => $item['name'] ?? null,
        ] + $result);

        return $result;
    }

    /**
     * Finds a folder whose name contains $matchTerm (case-insensitive, anywhere in
     * the name — start, end, or middle, not just an exact match), checking two
     * separate places: the root of the signed-in user's own OneDrive, AND items
     * shared with them by others. This two-source check matters because OneDrive's
     * "My files" web UI merges both into one view for convenience, but they're
     * genuinely different Graph API sources — a folder can show up in "My files"
     * on-screen (e.g. "Jane's files - Infiltration Report") purely via sharing,
     * without ever actually being a child of /me/drive/root, so checking root alone
     * misses it entirely. If both a shared item and a folder in our own drive
     * match, the shared one wins — a same-named match in our own drive is almost
     * always an earlier auto-created fallback shadowing the real shared folder the
     * user actually wants. Falls back to an exact-name lookup in our own root, then
     * creates $folderName fresh there if nothing matches at all.
     */
    private function findOrCreateFolder(string $accessToken, string $folderName, string $matchTerm = 'infiltration'): array
    {
        $ownMatch = null;
        $sharedMatch = null;
        $allItems = [];

        $sources = [
            'root'         => 'https://graph.microsoft.com/v1.0/me/drive/root/children',
            'sharedWithMe' => 'https://graph.microsoft.com/v1.0/me/drive/sharedWithMe',
        ];
        $anySourceSucceeded = false;

        foreach ($sources as $source => $url) {
            $listResponse = Http::withToken($accessToken)->get($url, $source === 'root' ? ['$top' => 200] : []);
            if (!$listResponse->successful()) {
                $this->step($this->logFile, 'OneDrivePersonalService', "{$source} listing FAILED", ['body' => $listResponse->body()]);
                continue;
            }
            $anySourceSucceeded = true;

            foreach ($listResponse->json('value', []) as $item) {
                // sharedWithMe items are ALWAYS remote (they live in someone else's
                // drive by definition); root/children items are remote only when
                // they're a shortcut (carry a remoteItem facet).
                $isShortcut = $source === 'sharedWithMe' || isset($item['remoteItem']);
                $isFolder   = isset($item['folder']) || isset($item['remoteItem']['folder']);
                $name       = $item['name'] ?? '';

                // Log every item from both sources, regardless of match — ground
                // truth for what's actually visible to this token, instead of only
                // seeing the final yes/no decision.
                $allItems[] = [
                    'source'      => $source,
                    'name'        => $name,
                    'isFolder'    => $isFolder,
                    'isShortcut'  => $isShortcut,
                    'matchesTerm' => stripos($name, $matchTerm) !== false,
                ];

                if (!$isFolder || stripos($name, $matchTerm) === false) {
                    continue;
                }

                // A shortcut/shared item's real location is under remoteItem — its
                // own id/driveId point at the shortcut object, not the real folder,
                // so resolve through remoteItem when present.
                $resolved = [
                    'driveId' => $item['remoteItem']['parentReference']['driveId'] ?? $item['parentReference']['driveId'] ?? null,
                    'itemId'  => $item['remoteItem']['id'] ?? $item['id'] ?? null,
                    'name'    => $name,
                ];

                if ($isShortcut && $sharedMatch === null) {
                    $sharedMatch = $resolved;
                } elseif (!$isShortcut && $ownMatch === null) {
                    $ownMatch = $resolved;
                }
            }
        }

        $this->step($this->logFile, 'OneDrivePersonalService', 'items listed', [
            'matchTerm' => $matchTerm,
            'count'     => count($allItems),
            'items'     => $allItems,
        ]);

        $match = $sharedMatch ?? $ownMatch;
        if ($match) {
            $this->step($this->logFile, 'OneDrivePersonalService', 'folder matched by name', [
                'matchedName'       => $match['name'],
                'isShortcut'        => $sharedMatch !== null,
                'itemId'            => $match['itemId'],
                'alsoFoundOwnMatch' => $sharedMatch !== null && $ownMatch !== null,
            ]);
            return ['driveId' => $match['driveId'], 'itemId' => $match['itemId']];
        }

        if (!$anySourceSucceeded) {
            $this->step($this->logFile, 'OneDrivePersonalService', 'all listings FAILED, falling back to exact lookup');
        }

        $encodedName = rawurlencode($folderName);
        $getResponse = Http::withToken($accessToken)
            ->get("https://graph.microsoft.com/v1.0/me/drive/root:/{$encodedName}");

        if ($getResponse->successful()) {
            $this->step($this->logFile, 'OneDrivePersonalService', 'folder found', [
                'itemId' => $getResponse->json('id'),
                'webUrl' => $getResponse->json('webUrl'),
            ]);
            return [
                'driveId' => $getResponse->json('parentReference.driveId'),
                'itemId'  => $getResponse->json('id'),
            ];
        }

        if ($getResponse->status() !== 404) {
            $this->step($this->logFile, 'OneDrivePersonalService', 'folder check FAILED', ['body' => $getResponse->body()]);
            throw new \RuntimeException('Failed to check for OneDrive folder: ' . $getResponse->body());
        }

        $createResponse = Http::withToken($accessToken)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('https://graph.microsoft.com/v1.0/me/drive/root/children', [
                'name'                                => $folderName,
                'folder'                              => new \stdClass(),
                '@microsoft.graph.conflictBehavior'   => 'fail',
            ]);

        if (!$createResponse->successful()) {
            $this->step($this->logFile, 'OneDrivePersonalService', 'folder create FAILED', ['body' => $createResponse->body()]);
            throw new \RuntimeException('Failed to create OneDrive folder: ' . $createResponse->body());
        }

        $this->step($this->logFile, 'OneDrivePersonalService', 'folder created', [
            'itemId' => $createResponse->json('id'),
            'webUrl' => $createResponse->json('webUrl'),
        ]);

        return [
            'driveId' => $createResponse->json('parentReference.driveId'),
            'itemId'  => $createResponse->json('id'),
        ];
    }

    /**
     * Uploads $localPath into the "Infiltration Report" folder in $user's own
     * OneDrive (created automatically if missing), as $fileName. Uses a resumable
     * upload session (same mechanics as the large-attachment path in
     * OutlookMailerService) since report zips can exceed the ~4MB simple-upload limit.
     *
     * @return array{id: ?string, webUrl: ?string, size: int} webUrl is a direct link
     *         to the uploaded file in OneDrive — the definitive way to confirm it
     *         actually landed, rather than just trusting a "success" log line.
     */
    public function uploadZip(User $user, string $localPath, string $fileName): array
    {
        $this->step($this->logFile, 'OneDrivePersonalService', 'upload starting', [
            'user_id'   => $user->id,
            'fileName'  => $fileName,
            'localPath' => $localPath,
            'size'      => file_exists($localPath) ? filesize($localPath) : null,
        ]);

        $accessToken = $this->getValidAccessToken($user);
        if (!$accessToken) {
            $this->step($this->logFile, 'OneDrivePersonalService', 'upload FAILED - not connected', ['user_id' => $user->id]);
            throw new \RuntimeException('OneDrive is not connected for this account.');
        }

        // Ground truth on what this token can actually do — if the stored token was
        // never actually reissued after a scope change (e.g. disconnect/reconnect
        // didn't fully happen, or Microsoft silently reused an old grant), this will
        // show the old, narrower scope even though our code now requests the new one.
        $this->step($this->logFile, 'OneDrivePersonalService', 'token scope check', [
            'scope' => $this->decodeTokenScope($accessToken),
        ]);

        // Try the direct sharing-link resolution first (deterministic, no
        // discovery ambiguity); fall back to name-based root/sharedWithMe
        // matching if no URL is configured or it fails to resolve.
        $folder = $this->resolveSharedFolderByUrl($accessToken)
            ?? $this->findOrCreateFolder($accessToken, 'Infiltration Report');

        $fileSize = filesize($localPath);
        $sessionUrl = "https://graph.microsoft.com/v1.0/drives/{$folder['driveId']}/items/{$folder['itemId']}:/{$fileName}:/createUploadSession";

        $sessionResponse = Http::withToken($accessToken)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($sessionUrl, ['item' => ['@microsoft.graph.conflictBehavior' => 'rename']]);

        if (!$sessionResponse->successful()) {
            $this->step($this->logFile, 'OneDrivePersonalService', 'upload session create FAILED', ['body' => $sessionResponse->body()]);
            throw new \RuntimeException('Failed to create OneDrive upload session: ' . $sessionResponse->body());
        }
        $this->step($this->logFile, 'OneDrivePersonalService', 'upload session created');

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
                $this->step($this->logFile, 'OneDrivePersonalService', 'chunk upload FAILED', [
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

        $this->step($this->logFile, 'OneDrivePersonalService', 'upload complete', ['user_id' => $user->id, 'fileName' => $fileName] + $result);

        return $result;
    }
}
