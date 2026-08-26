<?php

return [
    'default_country' => env('OPFIN_DEFAULT_COUNTRY', 'UG'),

    'capabilities' => [
        // Customer shell and foundation
        'home' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'identity' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'kyc' => ['status' => 'PILOT', 'owner' => 'opfin'],
        'consent' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'financial_passport' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'support' => ['status' => 'PILOT', 'owner' => 'opfin'],
        'security_centre' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],

        // Customer financial journeys
        'borrow' => ['status' => 'PILOT', 'owner' => 'opfin'],
        'budgeting' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'financial_calendar' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'money_autopilot' => ['status' => 'PLANNED', 'owner' => 'opfin'],
        'credit_builder' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'financial_shock_centre' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'save' => ['status' => 'PILOT', 'owner' => 'opfin'],
        'savings_partner_products' => ['status' => 'PILOT', 'owner' => 'opfin'],
        'savings_contributions' => ['status' => 'PILOT', 'owner' => 'opfin'],
        'savings_withdrawals' => ['status' => 'PILOT', 'owner' => 'opfin'],
        'insurance' => ['status' => 'PILOT', 'owner' => 'opfin'],
        'protection_enrollment' => ['status' => 'PILOT', 'owner' => 'opfin'],
        'protection_premiums' => ['status' => 'PILOT', 'owner' => 'opfin'],
        'protection_claims' => ['status' => 'PILOT', 'owner' => 'opfin'],
        'investments' => ['status' => 'PLANNED', 'owner' => 'opfin'],
        'household_finance' => ['status' => 'PLANNED', 'owner' => 'opfin'],
        'microbusiness' => ['status' => 'PLANNED', 'owner' => 'opfin'],

        // Institutional journeys
        'employer' => ['status' => 'PILOT', 'owner' => 'opfin'],
        'sacco' => ['status' => 'PLANNED', 'owner' => 'opfin'],
        'capital' => ['status' => 'PLANNED', 'owner' => 'opfin'],

        // Shared platform capabilities
        'payments' => ['status' => 'AVAILABLE', 'owner' => 'cpay'],
        'payment_reconciliation' => ['status' => 'AVAILABLE', 'owner' => 'cpay'],
        'product_factory' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'workflow_engine' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'rules_engine' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
    ],

    'countries' => [
        'UG' => [
            'name' => 'Uganda',
            'status' => 'PILOT',
            'currency' => 'UGX',
            'languages' => ['en'],
            'payment_platform' => 'cpay',
            'payment_status' => 'PRODUCTION',
        ],
    ],
];
