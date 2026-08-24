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
    'payment_gateway' => env('PAYMENT_GATEWAY'),
    'sms_gateway' => env('SMS_GATEWAY'),
    'yo' => [
        'base_url' => env('YO_SMS_GATEWAY'),
        'account' => env('YO_SMS_ACCOUNT'),
        'password' => env('YO_SMS_PASSWORD'),
    ],
    'cpay' => [
        // v2/Cito contract. Keep legacy aliases below until old call sites are retired.
        'base_url' => env('CPAY_BASE_URL', env('MOBILE_MONEY_API')),
        'merchant_number' => env('CPAY_MERCHANT_NUMBER', env('MOBILE_MONEY_MERCHANT_ID')),
        'private_key' => env('CPAY_PRIVATE_KEY', env('MOBILE_MONEY_PRIVATE_KEY')),
        'callback_url' => env('CPAY_CALLBACK_URL'),
        'callback_secret' => env('CPAY_CALLBACK_SECRET'),
        'country' => env('CPAY_COUNTRY', 'UG'),
        'currency' => env('CPAY_CURRENCY', 'UGX'),
        'channel' => env('CPAY_CHANNEL'),
        'minor_unit_exponent' => (int) env('CPAY_MINOR_UNIT_EXPONENT', 0),
        'timeout_seconds' => (int) env('CPAY_TIMEOUT_SECONDS', 30),
        'connect_retries' => (int) env('CPAY_CONNECT_RETRIES', 1),
        'retry_delay_ms' => (int) env('CPAY_RETRY_DELAY_MS', 250),
        'account' => env('MOBILE_MONEY_MERCHANT_ID'),
        'password' => env('MOBILE_MONEY_PRIVATE_KEY'),
    ],
    'crb' => [
        'base_url' => env('CRB_URL'),
        'account' => env('CRB_CLIENT_ID'),
        'password' => env('CRB_CLIENT_SECRET'),
    ],
    'airtel' => [
        'client_id' => env('AIRTEL_CLIENT_ID'),
        'client_secret' => env('AIRTEL_CLIENT_SECRET'),
        'base_url' => env('AIRTEL_BASE_URL'),
        'country' => env('AIRTEL_COUNTRY'),
        'currency' => env('AIRTEL_CURRENCY'),
        'pin' => env('AIRTEL_PIN'),
        'public_key' => env('AIRTEL_PUBLIC_KEY'),
    ],
    'mtn' => [
        'collection_sub_key' => env('MTN_MOMO_COLLECTION_SUB_KEY'),
        'disbursement_sub_key' => env('MTN_MOMO_DISBURSEMENT_SUB_KEY'),
        'base_url' => env('MTN_MOMO_BASE_URL', 'https://sandbox.momodeveloper.mtn.com'),
        'callback_url' => env('MTN_MOMO_CALLBACK_URL'),
        'api_user' => env('MTN_MOMO_API_USER'),
        'api_key' => env('MTN_MOMO_API_KEY'),
        'currency' => env('MTN_MOMO_CURRENCY', 'UGX'),
        'target_env' => env('MTN_MOMO_ENVIRONMENT', 'sandbox'),
    ],
    'mobile_money' => [
        'default_provider' => env('MOBILE_MONEY_PROVIDER', 'mock'),
        'currency' => env('MOBILE_MONEY_CURRENCY', 'UGX'),
        'providers' => [
            'mock' => [
                'webhook_secret' => env('MOCK_MOBILE_MONEY_WEBHOOK_SECRET'),
            ],
            'cpay' => [
                'webhook_secret' => env('CPAY_CALLBACK_SECRET'),
            ],
            'mtn' => [
                'base_url' => env('MTN_MOMO_BASE_URL'),
                'callback_url' => env('MTN_MOMO_CALLBACK_URL'),
                'collection_sub_key' => env('MTN_MOMO_COLLECTION_SUB_KEY'),
                'disbursement_sub_key' => env('MTN_MOMO_DISBURSEMENT_SUB_KEY'),
                'api_user' => env('MTN_MOMO_API_USER'),
                'api_key' => env('MTN_MOMO_API_KEY'),
                'access_token' => env('MTN_MOMO_ACCESS_TOKEN'),
                'currency' => env('MTN_MOMO_CURRENCY', 'UGX'),
                'target_env' => env('MTN_MOMO_ENVIRONMENT', 'sandbox'),
                'webhook_secret' => env('MTN_MOMO_WEBHOOK_SECRET'),
            ],
            'airtel' => [
                'base_url' => env('AIRTEL_BASE_URL'),
                'client_id' => env('AIRTEL_CLIENT_ID'),
                'client_secret' => env('AIRTEL_CLIENT_SECRET'),
                'access_token' => env('AIRTEL_ACCESS_TOKEN'),
                'country' => env('AIRTEL_COUNTRY', 'UG'),
                'currency' => env('AIRTEL_CURRENCY', 'UGX'),
                'pin' => env('AIRTEL_PIN'),
                'public_key' => env('AIRTEL_PUBLIC_KEY'),
                'webhook_secret' => env('AIRTEL_WEBHOOK_SECRET'),
            ],
        ],
    ],
    'payment_callback_secret' => env('PAYMENT_CALLBACK_SECRET'),
    'openai_api_key' => env('OPENAI_API_KEY'),
    'pinecone' => [
        'key' => env('PINECONE_API_KEY'),
        'url' => env('PINECONE_URL'),
    ],
    'opfin' => [
        'enable_demo_routes' => env('OPFIN_ENABLE_DEMO_ROUTES', false),
    ],
];
