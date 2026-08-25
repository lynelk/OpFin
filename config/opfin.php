<?php

return [
    'default_country' => env('OPFIN_DEFAULT_COUNTRY', 'UG'),

    'capabilities' => [
        // Customer shell and foundation
        'home' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'identity' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'kyc' => ['status' => 'PILOT', 'owner' => 'opfin'],
        'consent' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'financial_passport' => ['status' => 'PILOT', 'owner' => 'opfin'],
        'support' => ['status' => 'PILOT', 'owner' => 'opfin'],
        'security_centre' => ['status' => 'PLANNED', 'owner' => 'opfin'],

        // Customer financial journeys
        'borrow' => ['status' => 'PILOT', 'owner' => 'opfin'],
        'budgeting' => ['status' => 'PLANNED', 'owner' => 'opfin'],
        'financial_calendar' => ['status' => 'PLANNED', 'owner' => 'opfin'],
        'money_autopilot' => ['status' => 'PLANNED', 'owner' => 'opfin'],
        'credit_builder' => ['status' => 'PLANNED', 'owner' => 'opfin'],
        'financial_shock_centre' => ['status' => 'PLANNED', 'owner' => 'opfin'],
        'save' => ['status' => 'PLANNED', 'owner' => 'opfin'],
        'insurance' => ['status' => 'PLANNED', 'owner' => 'opfin'],
        'investments' => ['status' => 'PLANNED', 'owner' => 'opfin'],
        'household_finance' => ['status' => 'PLANNED', 'owner' => 'opfin'],
        'microbusiness' => ['status' => 'PLANNED', 'owner' => 'opfin'],

        // Institutional journeys
        'employer' => ['status' => 'PILOT', 'owner' => 'opfin'],
        'sacco' => ['status' => 'PLANNED', 'owner' => 'opfin'],
        'capital' => ['status' => 'PLANNED', 'owner' => 'opfin'],

        // Shared platform capabilities
        'payments' => ['status' => 'SANDBOX', 'owner' => 'cpay'],
        'payment_reconciliation' => ['status' => 'PILOT', 'owner' => 'cpay'],
        'product_factory' => ['status' => 'PLANNED', 'owner' => 'opfin'],
        'workflow_engine' => ['status' => 'PLANNED', 'owner' => 'opfin'],
        'rules_engine' => ['status' => 'PLANNED', 'owner' => 'opfin'],
    ],

    'countries' => [
        'UG' => [
            'name' => 'Uganda',
            'status' => 'PILOT',
            'currency' => 'UGX',
            'languages' => ['en'],
            'payment_platform' => 'cpay',
            'payment_status' => 'SANDBOX',
        ],
    ],
];
