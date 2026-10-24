<?php

/**
 * Canonical product discovery metadata. This registry is NOT a permission,
 * entitlement, regulatory activation, or production readiness decision.
 */
return [
    'schema_version' => 1,
    'spaces' => ['personal', 'household', 'business', 'savings_group', 'sacco', 'investment_club', 'institution'],
    // Relationship directions are discoverable product boundaries, not
    // entitlements, institution licences, consent grants or money permissions.
    // All five reuse the same person, Financial Spaces and domain APIs.
    'relationship_models' => [
        'b2c' => [
            'originator' => 'opfin_and_customer',
            'consumer' => 'person',
            'owner' => 'financial_records',
            'scope' => 'provider_independent_financial_life',
            'source_foundation' => 'personal_financial_life',
            'provider_activated' => false,
        ],
        'b2b' => [
            'originator' => 'institution',
            'consumer' => 'institution',
            'owner' => 'institutional_operations',
            'scope' => 'independent_institution_management',
            'source_foundation' => 'financial_spaces_and_institutional_workspace',
            'provider_activated' => false,
        ],
        'b2b2c' => [
            'originator' => 'institution',
            'consumer' => 'authorised_member_or_customer',
            'owner' => 'financial_spaces',
            'scope' => 'institution_scoped_member_experience',
            'source_foundation' => 'financial_space_membership',
            'provider_activated' => false,
        ],
        'c2b' => [
            'originator' => 'person',
            'consumer' => 'authorised_institution',
            'owner' => 'financing',
            'scope' => 'customer_initiated_financial_intent',
            'source_foundation' => 'financial_intents',
            'provider_activated' => false,
        ],
        'c2b2b' => [
            'originator' => 'person_via_institution',
            'consumer' => 'contracted_second_institution',
            'owner' => 'integrations_and_financing',
            'scope' => 'customer_confirmed_scoped_partner_handoff',
            'source_foundation' => 'partner_financial_intent_requests',
            'multi_hop_generalised' => false,
            'provider_activated' => false,
        ],
    ],
    'tools' => [
        'budgets' => ['owner' => 'financial_records', 'spaces' => ['personal', 'household', 'business', 'savings_group', 'sacco', 'investment_club', 'institution']],
        'asset_register' => ['owner' => 'financial_records', 'spaces' => ['personal', 'household', 'business', 'savings_group', 'sacco', 'investment_club', 'institution']],
        'debt_management' => ['owner' => 'financial_records', 'spaces' => ['personal', 'household', 'business', 'savings_group', 'sacco', 'investment_club', 'institution']],
        'cash_flow' => ['owner' => 'financial_records', 'spaces' => ['personal', 'household', 'business', 'savings_group', 'sacco', 'investment_club', 'institution']],
        'goals' => ['owner' => 'financial_records', 'spaces' => ['personal', 'household', 'savings_group', 'investment_club']],
        'accounting' => ['owner' => 'accounting', 'spaces' => ['business', 'savings_group', 'sacco', 'investment_club', 'institution']],
        'approvals' => ['owner' => 'governance', 'spaces' => ['household', 'business', 'savings_group', 'sacco', 'investment_club', 'institution']],
        'documents' => ['owner' => 'governance', 'spaces' => ['personal', 'household', 'business', 'savings_group', 'sacco', 'investment_club', 'institution']],
        'tasks' => ['owner' => 'governance', 'spaces' => ['business', 'savings_group', 'sacco', 'investment_club', 'institution']],
        'reporting' => ['owner' => 'intelligence', 'spaces' => ['personal', 'household', 'business', 'savings_group', 'sacco', 'investment_club', 'institution']],
    ],
    'services' => [
        'personal' => ['owner' => 'financial_records', 'spaces' => ['personal']],
        'household' => ['owner' => 'financial_records', 'spaces' => ['household']],
        'savings_groups' => ['owner' => 'group_finance', 'spaces' => ['savings_group']],
        'sacco' => ['owner' => 'group_finance', 'spaces' => ['sacco']],
        'investment_clubs' => ['owner' => 'investment_operations', 'spaces' => ['investment_club']],
        'business' => ['owner' => 'financial_records', 'spaces' => ['business']],
        'people' => ['owner' => 'people', 'spaces' => ['business', 'institution'], 'modules' => ['hr', 'leave', 'payroll', 'attendance', 'biometrics', 'assets', 'appraisals', 'rewards', 'kpis', 'field_accountability']],
        'credit' => ['owner' => 'credit_risk', 'spaces' => ['personal', 'business', 'savings_group', 'sacco']],
        'wealth' => ['owner' => 'investment_operations', 'spaces' => ['personal', 'investment_club']],
        'protection' => ['owner' => 'savings_protection', 'spaces' => ['personal', 'savings_group', 'sacco']],
        'essentials' => ['owner' => 'financial_records', 'spaces' => ['personal', 'household', 'business']],
        'partners' => ['owner' => 'integrations', 'spaces' => ['business', 'institution']],
        'insights' => ['owner' => 'intelligence', 'spaces' => ['personal', 'household', 'business', 'savings_group', 'sacco', 'investment_club', 'institution']],
    ],
];
