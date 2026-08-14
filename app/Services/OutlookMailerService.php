<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OutlookMailerService
{
    protected $clientId;
    protected $clientSecret;
    protected $tenantId;

    protected $authority;
    protected $tokenUrl;

    protected $scope;

    public function __construct()
    {
        $this->clientId = config('services.msgraph.client_id');
        $this->clientSecret = config('services.msgraph.client_secret');
        $this->tenantId = config('services.msgraph.tenant_id');
        $this->authority = "https://login.microsoftonline.com/{$this->tenantId}";
        $this->tokenUrl = "{$this->authority}/oauth2/v2.0/token";
        $this->scope = "https://graph.microsoft.com/.default";
    }

    public function getAccessToken()
    {
        $response = Http::asForm()->post($this->tokenUrl, [
            'client_id' => $this->clientId,
            'scope' => $this->scope,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'client_credentials',
        ]);
        if ($response->successful()) {
            return $response->json()['access_token'];
        }
        throw new \Exception("Failed to acquire token: " . $response->body());
    }

    public function sendMail($recipientEmail, $subject, $bodyText, array $attachmentPaths = [])
    {
        $accessToken = $this->getAccessToken();
        $senderEmail = config('services.msgraph.sender_email');
        $baseUrl     = "https://graph.microsoft.com/v1.0/users/{$senderEmail}";

        // Separate small (<= 3 MB) and large attachments
        $inlineAttachments = [];
        $largeAttachments  = [];
        $totalBytes        = 0;
        foreach ($attachmentPaths as $path) {
            if (!file_exists($path)) {
                Log::warning('OutlookMailer: attachment path does not exist, skipping', ['path' => $path]);
                continue;
            }
            $size = filesize($path);
            $totalBytes += $size;
            if ($size <= 3 * 1024 * 1024) {
                $inlineAttachments[] = $path;
            } else {
                $largeAttachments[] = $path;
            }
        }

        \Illuminate\Support\Facades\Log::info('OutlookMailer: sending to ' . $recipientEmail
            . ' via ' . $senderEmail
            . ', inline: ' . count($inlineAttachments)
            . ', large: ' . count($largeAttachments)
            . ', total: ' . round($totalBytes / 1024, 1) . ' KB');

        // Build the draft message body
        $messageData = [
            "subject" => $subject,
            "body"    => ["contentType" => "html", "content" => $bodyText],
            "toRecipients" => [["emailAddress" => ["address" => $recipientEmail]]],
        ];

        // Attach small files inline
        foreach ($inlineAttachments as $path) {
            $messageData['attachments'][] = [
                "@odata.type"  => "#microsoft.graph.fileAttachment",
                "name"         => basename($path),
                "contentBytes" => base64_encode(file_get_contents($path)),
                "contentType"  => mime_content_type($path),
            ];
        }

        if (empty($largeAttachments)) {
            // No large files — send directly
            $response = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(60)
                ->post("{$baseUrl}/sendMail", ['message' => $messageData]);

            if ($response->status() !== 202) {
                \Illuminate\Support\Facades\Log::error('OutlookMailer error: ' . $response->body());
                throw new \Exception("Graph API sendMail failed [{$response->status()}]: " . $response->body());
            }
            return "Email sent successfully!";
        }

        // Has large attachments — create a draft first, then upload, then send
        $draftResponse = Http::withToken($accessToken)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->timeout(30)
            ->post("{$baseUrl}/messages", $messageData);

        if (!in_array($draftResponse->status(), [200, 201])) {
            \Illuminate\Support\Facades\Log::error('OutlookMailer draft error: ' . $draftResponse->body());
            throw new \Exception("Graph API create draft failed [{$draftResponse->status()}]: " . $draftResponse->body());
        }

        $messageId = $draftResponse->json('id');

        // Upload each large attachment via upload session
        foreach ($largeAttachments as $path) {
            $fileName    = basename($path);
            $fileSize    = filesize($path);
            $contentType = mime_content_type($path);

            // Create upload session
            $sessionResponse = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post("{$baseUrl}/messages/{$messageId}/attachments/createUploadSession", [
                    'AttachmentItem' => [
                        'attachmentType' => 'file',
                        'name'           => $fileName,
                        'size'           => $fileSize,
                        'contentType'    => $contentType,
                    ],
                ]);

            if (!in_array($sessionResponse->status(), [200, 201])) {
                \Illuminate\Support\Facades\Log::error('OutlookMailer upload session error: ' . $sessionResponse->body());
                throw new \Exception("Graph API upload session failed: " . $sessionResponse->body());
            }

            $uploadUrl = $sessionResponse->json('uploadUrl');
            $chunkSize = 4 * 1024 * 1024; // 4 MB chunks
            $handle    = fopen($path, 'rb');
            $offset    = 0;

            while (!feof($handle)) {
                $chunk     = fread($handle, $chunkSize);
                $chunkLen  = strlen($chunk);
                $rangeEnd  = $offset + $chunkLen - 1;

                $uploadResponse = Http::withHeaders([
                    'Content-Range'  => "bytes {$offset}-{$rangeEnd}/{$fileSize}",
                    'Content-Length' => $chunkLen,
                ])->timeout(120)->withBody($chunk, $contentType)->put($uploadUrl);

                if (!in_array($uploadResponse->status(), [200, 201, 202])) {
                    fclose($handle);
                    \Illuminate\Support\Facades\Log::error('OutlookMailer chunk upload error: ' . $uploadResponse->body());
                    throw new \Exception("Graph API chunk upload failed: " . $uploadResponse->body());
                }
                $offset += $chunkLen;
            }
            fclose($handle);
        }

        // Send the draft
        $sendResponse = Http::withToken($accessToken)
            ->timeout(30)
            ->post("{$baseUrl}/messages/{$messageId}/send");

        if ($sendResponse->status() !== 202) {
            \Illuminate\Support\Facades\Log::error('OutlookMailer send draft error: ' . $sendResponse->body());
            throw new \Exception("Graph API send draft failed [{$sendResponse->status()}]: " . $sendResponse->body());
        }

        \Illuminate\Support\Facades\Log::info('OutlookMailer: mail sent successfully via upload session');
        return "Email sent successfully!";
    }
}
