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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | WhatsApp messages (sendmsgutil.php gateway). Templates are approved WhatsApp templates;
    | their {{n}} placeholders are filled from the values passed in order.
    | With WHATSAPP_ENABLED=false nothing is sent and the message is written to the log instead.
    */
    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', false),
        'url' => env('WHATSAPP_URL'),
        'user' => env('WHATSAPP_USER'),
        'password' => env('WHATSAPP_PASSWORD'),
        'sender' => env('WHATSAPP_SENDER'),
        'public_url' => env('WHATSAPP_PUBLIC_URL'),
        // Prefixed to the 10-digit numbers customers type (e.g. "91"). Empty sends the 10 digits as they are.
        'country_code' => env('WHATSAPP_COUNTRY_CODE', ''),
        'timeout' => 10,

        // Query parameters the gateway expects. "params" carries the template's {{1}}, {{2}}… comma-separated.
        'fields' => [
            'user' => 'user',
            'password' => 'pass',
            'sender' => 'sender',
            'phone' => 'phone',
            'template' => 'text',
            'params' => 'Params',
        ],
        // Sent with every message; "stype" is overridden per template type below.
        'extra' => ['priority' => 'wa', 'stype' => 'normal'],

        'templates' => [
            // Authentication; {{1}} is the code.
            'otp' => 'visionotp',
        ],
        // Extra query values for a template (an authentication template needs stype=auth).
        'template_extra' => [
            'otp' => ['stype' => 'auth'],
        ],
    ],

    // One-time codes customers receive on WhatsApp (sign-up, checkout, sign-in).
    'otp' => [
        // Off (follows WHATSAPP_ENABLED unless OTP_ENABLED is set): no code is asked. Sign-up makes
        // the account at once, a guest checks out as a guest, "Sign in with WhatsApp" is hidden
        // and Forgot password emails a reset link. See App\Support\CustomerOtp::enabled().
        'enabled' => (bool) env('OTP_ENABLED', env('WHATSAPP_ENABLED', false)),
        'length' => 6,
        'ttl' => 300,            // valid for 5 minutes
        'max_attempts' => 5,     // wrong entries before the code is void
        'resend_after' => 60,    // seconds before "Resend code" works
        'max_sends' => 5,        // codes per phone number…
        'send_window' => 3600,   // …in this many seconds
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
