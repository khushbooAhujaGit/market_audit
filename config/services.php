<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'msgraph' => [
        'client_id' => env('MS_GRAPH_CLIENT_ID'),
        'client_secret' => env('MS_GRAPH_CLIENT_SECRET'),
        'tenant_id' => env('MS_GRAPH_TENANT_ID'),
        'sender_email' => env('MS_GRAPH_SENDER_EMAIL'),
        // Whose OneDrive the Infiltration Report's "Upload to OneDrive" button uploads
        // into (app-only auth, single fixed account — see OneDriveService). Must be an
        // account with an actual OneDrive license, which a mail-only sender address may
        // not have. Falls back to MS_GRAPH_SENDER_EMAIL if not set separately.
        'onedrive_target_user' => env('MS_GRAPH_ONEDRIVE_USER', env('MS_GRAPH_SENDER_EMAIL')),
    ],

    'infiltration_tool' => [
        // Path/command to invoke Python 3 on this server. Override in .env if the server's
        // python binary isn't on PATH as "python3" (e.g. cPanel-style hosts often need a
        // full path like /usr/local/bin/python3.11).
        'python_bin' => env('INFILTRATION_TOOL_PYTHON_BIN', 'python3'),
    ],

];
