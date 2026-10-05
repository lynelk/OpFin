<?php

return [
    // Each rail fixes its permitted contract types and how price is expressed. Islamic products are
    // priced as a profit or rental rate and never through conventional interest (APR) logic.
    'rails' => [
        'CONVENTIONAL' => ['contract_types' => ['CREDIT', 'HIRE_PURCHASE', 'FINANCE_LEASE'], 'price_kind' => 'apr_bps'],
        'ISLAMIC' => ['contract_types' => ['MURABAHA', 'IJARA', 'MUSHARAKA_MUTANAQISA'], 'price_kind' => 'profit_rate_bps'],
    ],

    'families' => ['asset_finance', 'device_finance', 'vehicle_finance', 'productive_asset_finance', 'working_capital'],

    // Families that finance a registered asset must name the asset classes they may finance.
    'asset_families' => ['asset_finance', 'device_finance', 'vehicle_finance', 'productive_asset_finance'],

    'fee_types' => ['arrangement', 'late_payment', 'early_settlement', 'insurance_pass_through'],

    'disclosure_keys' => ['total_cost', 'late_payment_consequences', 'complaints_contact', 'cooling_off', 'ownership_and_repossession'],
];
