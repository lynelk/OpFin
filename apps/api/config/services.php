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
        // CPay is OpFin's preferred governed money-movement route, not an availability dependency.
        'base_url' => env('CPAY_BASE_URL'),
        'merchant_number' => env('CPAY_MERCHANT_NUMBER'),
        'merchant_id' => env('CPAY_MERCHANT_ID'),
        'private_key' => env('CPAY_PRIVATE_KEY'),
        'callback_url' => env('CPAY_CALLBACK_URL'),
        'callback_secret' => env('CPAY_CALLBACK_SECRET'),
        // Messaging is a separate CPay contract. No endpoint is guessed in production.
        'sms_path' => env('CPAY_SMS_PATH'),
        'bill_lookup_path' => env('CPAY_BILL_LOOKUP_PATH'),
        'bill_payment_path' => env('CPAY_BILL_PAYMENT_PATH'),
        'beneficiary_payment_path' => env('CPAY_BENEFICIARY_PAYMENT_PATH'),
        'lender_repayment_path' => env('CPAY_LENDER_REPAYMENT_PATH'),
        'transaction_status_path' => env('CPAY_TRANSACTION_STATUS_PATH'),
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

    'cito' => [
        // Cito is the preferred third-party service gateway. These may deliberately be unset.
        'base_url' => env('CITO_BASE_URL', env('CPAY_BASE_URL')),
        'merchant_number' => env('CITO_MERCHANT_NUMBER', env('CPAY_MERCHANT_NUMBER')),
        'private_key' => env('CITO_PRIVATE_KEY', env('CPAY_PRIVATE_KEY')),
        'environment' => env('CITO_ENVIRONMENT', env('CPAY_ENVIRONMENT', 'sandbox')),
        'timeout_seconds' => (int) env('CITO_TIMEOUT_SECONDS', 15),
        'essentials_lending_path' => env('CITO_ESSENTIALS_LENDING_PATH'),
        'essentials_drawdown_path' => env('CITO_ESSENTIALS_DRAWDOWN_PATH'),
        'essentials_drawdown_status_path' => env('CITO_ESSENTIALS_DRAWDOWN_STATUS_PATH'),
        'essentials_drawdown_release_path' => env('CITO_ESSENTIALS_DRAWDOWN_RELEASE_PATH'),
    ],

    'crb' => [
        'provider' => env('CRB_PROVIDER'),
        'base_url' => env('CRB_URL'),
        'account' => env('CRB_CLIENT_ID'),
        'password' => env('CRB_CLIENT_SECRET'),
        'environment' => env('CRB_ENVIRONMENT', 'production'),
    ],

    'efris' => [
        'url' => env('EFRIS_URL'),
        'token' => env('EFRIS_TOKEN'),
        'tin' => env('EFRIS_TIN'),
    ],

    'credit_reference_reporting' => [
        'url' => env('CREDIT_REFERENCE_REPORTING_URL'),
        'token' => env('CREDIT_REFERENCE_REPORTING_TOKEN'),
        'provider' => env('CREDIT_REFERENCE_REPORTING_PROVIDER', 'configured_credit_reference'),
    ],

    'identity_verification' => [
        'provider' => env('IDENTITY_VERIFICATION_PROVIDER'),
        'url' => env('IDENTITY_VERIFICATION_URL'),
        'token' => env('IDENTITY_VERIFICATION_TOKEN'),
        'disk' => env('KYC_FILESYSTEM_DISK', env('FILESYSTEM_DISK', 'local')),
    ],

    'scoring' => [
        'mno' => [
            'url' => env('MNO_SCORING_URL'),
            'token' => env('MNO_SCORING_TOKEN'),
        ],
        'third_party' => [
            'url' => env('THIRD_PARTY_SCORING_URL'),
            'token' => env('THIRD_PARTY_SCORING_TOKEN'),
        ],
    ],

    // Airtel integration retained here is KYC-related unless a separately certified money adapter is configured.
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
                'production_certified' => false,
            ],
            'cpay' => [
                'webhook_secret' => env('CPAY_CALLBACK_SECRET'),
                'production_certified' => true,
            ],
            'mtn' => [
                'adapter' => env('MTN_MOBILE_MONEY_ADAPTER_CLASS'),
                'production_certified' => (bool) env('MTN_MOBILE_MONEY_PRODUCTION_CERTIFIED', false),
            ],
            'airtel' => [
                'adapter' => env('AIRTEL_MOBILE_MONEY_ADAPTER_CLASS'),
                'production_certified' => (bool) env('AIRTEL_MOBILE_MONEY_PRODUCTION_CERTIFIED', false),
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
        'web_url' => env('OPFIN_WEB_URL'),
    ],
    'ussd' => [
        'shared_secret' => env('USSD_SHARED_SECRET'),
    ],
];
