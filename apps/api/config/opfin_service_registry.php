<?php

/**
 * Canonical product discovery metadata. This registry is NOT a permission,
 * entitlement, regulatory activation, or production readiness decision.
 */
return [
    'schema_version' => 1,
    'spaces' => ['personal', 'household', 'business', 'savings_group', 'sacco', 'investment_club', 'institution'],
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
