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
        $mailData = [
            "message" => [
                "subject" => $subject,
                "body" => [
                    "contentType" => "html",
                    "content" => $bodyText,
                ],
                "toRecipients" => [
                    [
                        "emailAddress" => [
                            "address" => $recipientEmail
                        ]
                    ]
                ],
            ]
        ];

        // Add attachment if provided
        foreach ($attachmentPaths as $path) {

            if (file_exists($path)) {
                $fileName = basename($path);
                $fileContent = base64_encode(file_get_contents($path));

                $mailData['message']['attachments'][] = [
                    "@odata.type" => "#microsoft.graph.fileAttachment",
                    "name" => $fileName,
                    "contentBytes" => $fileContent,
                    "contentType" => mime_content_type($path),
                ];
            }
        }


        $url = "https://graph.microsoft.com/v1.0/users/{$senderEmail}/sendMail";
        $response = Http::withToken($accessToken)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $mailData);
        if ($response->status() === 202) {
            return "Email sent successfully!";
        }
        return "Failed to send email: " . $response->body();
    }
}
