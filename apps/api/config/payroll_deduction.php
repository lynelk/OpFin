<?php

return [
    'default_provider' => env('PAYROLL_DEDUCTION_PROVIDER', 'pdms'),

    'reservation_ttl_hours' => (int) env('PAYROLL_DEDUCTION_RESERVATION_TTL_HOURS', 72),

    'providers' => [
        'pdms' => [
            'mode' => env('PDMS_MODE', 'manual_evidence'),
            'base_url' => env('PDMS_BASE_URL'),
            'credential_reference' => env('PDMS_CREDENTIAL_REFERENCE'),
        ],
    ],
];
