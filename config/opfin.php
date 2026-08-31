<?php

return [
    'default_country' => env('OPFIN_DEFAULT_COUNTRY', 'UG'),

    'capabilities' => [
        // Customer shell and foundation
        'home' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'activation' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'identity' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'kyc' => ['status' => 'AVAILABLE', 'owner' => 'opfin', 'external_gate' => 'identity_provider_credentials_and_certification'],
        'consent' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'financial_passport' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'support' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'security_centre' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],

        // Customer financial journeys
        'borrow' => ['status' => 'AVAILABLE', 'owner' => 'opfin', 'external_gate' => 'licensed_product_and_funding_configuration'],
        'budgeting' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'financial_calendar' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'money_autopilot' => ['status' => 'AVAILABLE', 'owner' => 'opfin', 'external_gate' => 'provider_mandates_for_automatic_money_movement'],
        'credit_builder' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'financial_shock_centre' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'save' => ['status' => 'AVAILABLE', 'owner' => 'opfin', 'external_gate' => 'licensed_savings_partner_products'],
        'savings_partner_products' => ['status' => 'AVAILABLE', 'owner' => 'opfin', 'external_gate' => 'partner_product_activation'],
        'savings_contributions' => ['status' => 'AVAILABLE', 'owner' => 'opfin', 'external_gate' => 'cpay_collection_and_partner_confirmation'],
        'savings_withdrawals' => ['status' => 'AVAILABLE', 'owner' => 'opfin', 'external_gate' => 'partner_withdrawal_and_cpay_payout'],
        'insurance' => ['status' => 'AVAILABLE', 'owner' => 'opfin', 'external_gate' => 'licensed_insurer_underwriter_products'],
        'protection_enrollment' => ['status' => 'AVAILABLE', 'owner' => 'opfin', 'external_gate' => 'insurer_issuance'],
        'protection_premiums' => ['status' => 'AVAILABLE', 'owner' => 'opfin', 'external_gate' => 'cpay_collection_and_insurer_confirmation'],
        'protection_claims' => ['status' => 'AVAILABLE', 'owner' => 'opfin', 'external_gate' => 'insurer_claim_adjudication'],
        'investments' => ['status' => 'AVAILABLE', 'owner' => 'opfin', 'external_gate' => 'licensed_investment_provider_custody_and_settlement'],
        'household_finance' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'microbusiness' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'asset_finance' => ['status' => 'AVAILABLE', 'owner' => 'opfin', 'external_gate' => 'asset_supplier_and_finance_product_activation'],
        'p2p_participatory_finance' => ['status' => 'AVAILABLE', 'owner' => 'opfin', 'external_gate' => 'lender_of_record_custody_settlement_and_regulatory_approval'],
        'rewards_referrals' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'linked_accounts' => ['status' => 'AVAILABLE', 'owner' => 'opfin', 'external_gate' => 'provider_open_api_or_authorised_data_connection'],

        // Inclusive and assisted channels
        'whatsapp' => ['status' => 'AVAILABLE', 'owner' => 'opfin', 'external_gate' => 'meta_whatsapp_production_credentials'],
        'ussd' => ['status' => 'AVAILABLE', 'owner' => 'opfin', 'external_gate' => 'ussd_aggregator_short_code_and_callback_configuration'],
        'offline_aware_mode' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],

        // Institutional journeys
        'employer' => ['status' => 'AVAILABLE', 'owner' => 'opfin', 'external_gate' => 'employer_payroll_integration_for_live_deductions'],
        'sacco' => ['status' => 'AVAILABLE', 'owner' => 'opfin', 'external_gate' => 'institution_integration_and_membership_verification'],
        'capital' => ['status' => 'AVAILABLE', 'owner' => 'opfin', 'external_gate' => 'capital_provider_and_regulatory_activation'],
        'partner_distribution' => ['status' => 'AVAILABLE', 'owner' => 'opfin', 'external_gate' => 'partner_due_diligence_contract_and_credentials'],

        // Shared platform capabilities
        'payments' => ['status' => 'AVAILABLE', 'owner' => 'cpay'],
        'payment_reconciliation' => ['status' => 'AVAILABLE', 'owner' => 'cpay'],
        'product_factory' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'workflow_engine' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'rules_engine' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'platform_autopilot' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'regulatory_reporting' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
        'financial_integrity_audit' => ['status' => 'AVAILABLE', 'owner' => 'opfin'],
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
