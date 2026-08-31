<?php

return [
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'sms_gateway' => env('SMS_GATEWAY'),
    'yo' => [
        'base_url' => env('YO_SMS_GATEWAY'),
        'account' => env('YO_SMS_ACCOUNT'),
        'password' => env('YO_SMS_PASSWORD'),
    ],

    'whatsapp' => [
        'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com/v23.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    ],

    'cpay' => [
        // CPay v2 is the canonical and only production OpFin money-movement boundary.
        'base_url' => env('CPAY_BASE_URL'),
        'merchant_number' => env('CPAY_MERCHANT_NUMBER'),
        'merchant_id' => env('CPAY_MERCHANT_ID'),
        'private_key' => env('CPAY_PRIVATE_KEY'),
        'callback_url' => env('CPAY_CALLBACK_URL'),
        'callback_secret' => env('CPAY_CALLBACK_SECRET'),
        'callback_replay_window_seconds' => (int) env('CPAY_CALLBACK_REPLAY_WINDOW_SECONDS', 300),
        'environment' => env('CPAY_ENVIRONMENT', 'sandbox'),
        'country' => env('CPAY_COUNTRY', 'UG'),
        'currency' => env('CPAY_CURRENCY', 'UGX'),
        'channel' => env('CPAY_CHANNEL'),
        'minor_unit_exponent' => (int) env('CPAY_MINOR_UNIT_EXPONENT', 0),
        'timeout_seconds' => (int) env('CPAY_TIMEOUT_SECONDS', 30),
        'connect_retries' => (int) env('CPAY_CONNECT_RETRIES', 1),
        'retry_delay_ms' => (int) env('CPAY_RETRY_DELAY_MS', 250),
    ],

    'crb' => [
        'base_url' => env('CRB_URL'),
        'account' => env('CRB_CLIENT_ID'),
        'password' => env('CRB_CLIENT_SECRET'),
    ],

    // Airtel integration is retained only for KYC lookup. It must not initiate or inspect money movement.
    'airtel' => [
        'client_id' => env('AIRTEL_CLIENT_ID'),
        'client_secret' => env('AIRTEL_CLIENT_SECRET'),
        'base_url' => env('AIRTEL_BASE_URL'),
        'country' => env('AIRTEL_COUNTRY', 'UG'),
        'currency' => env('AIRTEL_CURRENCY', 'UGX'),
    ],

    'mobile_money' => [
        'default_provider' => env('MOBILE_MONEY_PROVIDER', 'cpay'),
        'currency' => env('MOBILE_MONEY_CURRENCY', 'UGX'),
        'providers' => [
            'mock' => [
                'webhook_secret' => env('MOCK_MOBILE_MONEY_WEBHOOK_SECRET'),
            ],
            'cpay' => [
                'webhook_secret' => env('CPAY_CALLBACK_SECRET'),
            ],
        ],
    ],

    'openai_api_key' => env('OPENAI_API_KEY'),
    'pinecone' => [
        'key' => env('PINECONE_API_KEY'),
        'url' => env('PINECONE_URL'),
    ],
    'opfin' => [
        'enable_demo_routes' => env('OPFIN_ENABLE_DEMO_ROUTES', false),
    ],
];
