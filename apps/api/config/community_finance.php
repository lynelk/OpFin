<?php

return [
    'activation' => [
        // Keep the full SACCO/community finance foundation built but closed until governance,
        // partner, custody, regulatory and production sign-off are all recorded.
        'mode' => env('OPFIN_COMMUNITY_FINANCE_MODE', 'dormant'),
        'live_enabled' => (bool) env('OPFIN_COMMUNITY_FINANCE_LIVE_ENABLED', false),
        'sacco_core_enabled' => (bool) env('OPFIN_SACCO_CORE_ENABLED', false),
        'public_routes_enabled' => (bool) env('OPFIN_COMMUNITY_FINANCE_PUBLIC_ROUTES_ENABLED', false),
        'required_signoffs' => [
            'board_or_product_committee_approval',
            'licence_or_regulated_partner_confirmation',
            'funds_custody_and_settlement_model',
            'member_terms_and_plain_language_disclosures',
            'risk_limits_and_loan_loss_reserve_policy',
            'data_protection_and_consent_review',
            'operations_playbook_and_member_support_readiness',
            'production_reconciliation_and_recovery_drill',
        ],
    ],

    'language' => [
        'regulated_community_capital_pools' => [
            'public_name' => 'Community Growth Circles',
            'short_name' => 'Growth Circles',
            'member_description' => 'A transparent member-backed pool for savings-led community finance, opened only after the approved governance, custody and regulatory checks are complete.',
            'internal_control_name' => 'regulated_community_capital_pools',
        ],
        'insurance_and_asset_finance' => [
            'public_name' => 'Protection & Asset Plans',
            'short_name' => 'Protection Plans',
            'member_description' => 'Practical cover and asset pathways for members who want to protect income, financed assets or household resilience through approved partners.',
            'internal_control_name' => 'insurance_and_asset_finance',
        ],
        'employer_backed_lending' => [
            'public_name' => 'Workplace Support Finance',
            'short_name' => 'Workplace Finance',
            'member_description' => 'Employer-supported savings, welfare and credit arrangements where salary or payroll information helps verify affordability and repayment arrangements.',
            'internal_control_name' => 'employer_backed_lending',
        ],
        'behaviour_scoring' => [
            'public_name' => 'Member Growth Score',
            'short_name' => 'Growth Score',
            'member_description' => 'A transparent score that explains financial habits, platform conduct, community participation and product behaviour instead of hiding decisions in a black box.',
            'internal_control_name' => 'behaviour_scoring',
        ],
        'sacco_core' => [
            'public_name' => 'Member Cooperative Core',
            'short_name' => 'Cooperative Core',
            'member_description' => 'The dormant SACCO operating foundation for membership, shares, savings, guarantors, committee approvals, statements and cooperative records.',
            'internal_control_name' => 'sacco_core',
        ],
    ],

    'modules' => [
        'community_growth_circles' => [
            'status' => 'DORMANT_READY',
            'activation_gate' => 'community_growth_circle_governance_custody_regulatory_signoff',
            'allowed_before_activation' => ['configuration', 'readiness_review', 'simulation', 'staff_training'],
            'blocked_before_activation' => ['member_deposit_collection', 'investment_acceptance', 'loan_disbursement', 'return_distribution'],
        ],
        'protection_asset_plans' => [
            'status' => 'DORMANT_READY',
            'activation_gate' => 'licensed_partner_product_underwriting_and_asset_supplier_signoff',
            'allowed_before_activation' => ['partner_due_diligence', 'pricing_simulation', 'disclosure_review'],
            'blocked_before_activation' => ['policy_issuance', 'premium_collection', 'asset_disbursement', 'supplier_settlement'],
        ],
        'workplace_support_finance' => [
            'status' => 'DORMANT_READY',
            'activation_gate' => 'employer_mou_payroll_consent_and_deduction_file_signoff',
            'allowed_before_activation' => ['employer_setup', 'eligibility_simulation', 'payroll_mapping'],
            'blocked_before_activation' => ['salary_deduction', 'live_loan_acceptance', 'employer_float_drawdown'],
        ],
        'member_growth_score' => [
            'status' => 'READY_FOR_INTERNAL_USE',
            'activation_gate' => 'score_policy_approval_and_adverse_action_disclosure_review',
            'allowed_before_activation' => ['score_preview', 'explainability_review', 'model_monitoring_dry_run'],
            'blocked_before_activation' => ['automated_decline', 'automated_limit_reduction', 'price_increase_without_review'],
        ],
        'member_cooperative_core' => [
            'status' => 'DORMANT_READY',
            'activation_gate' => 'sacco_entity_partner_or_membership_model_signoff',
            'allowed_before_activation' => ['schema_migration', 'admin_configuration', 'dry_run_member_import', 'training'],
            'blocked_before_activation' => ['member_share_collection', 'member_savings_collection', 'credit_committee_approval', 'dividend_distribution'],
        ],
    ],

    'score' => [
        'policy_version' => 'member-growth-score-v1.0-dormant',
        'composite_name' => 'OpFin Growth Passport',
        'public_component_name' => 'Member Growth Score',
        'bands' => [
            'growth_leader' => ['min' => 85, 'label' => 'Growth Leader'],
            'steady_builder' => ['min' => 70, 'label' => 'Steady Builder'],
            'building_momentum' => ['min' => 55, 'label' => 'Building Momentum'],
            'needs_support' => ['min' => 40, 'label' => 'Needs Support'],
            'rebuild_path' => ['min' => 0, 'label' => 'Rebuild Path'],
        ],
        'components' => [
            'financial' => [
                'label' => 'Financial Habits',
                'weight' => 40,
                'factors' => [
                    'savings_consistency' => ['label' => 'Savings consistency', 'weight' => 25],
                    'repayment_discipline' => ['label' => 'Repayment discipline', 'weight' => 30],
                    'affordability_buffer' => ['label' => 'Affordability buffer', 'weight' => 20],
                    'income_or_cashflow_stability' => ['label' => 'Income or cashflow stability', 'weight' => 15],
                    'existing_obligation_pressure' => ['label' => 'Existing obligation pressure', 'weight' => 10],
                ],
            ],
            'platform' => [
                'label' => 'Platform Trust',
                'weight' => 20,
                'factors' => [
                    'verified_identity_depth' => ['label' => 'Verified identity depth', 'weight' => 25],
                    'consent_reliability' => ['label' => 'Consent reliability', 'weight' => 20],
                    'account_security' => ['label' => 'Account security', 'weight' => 20],
                    'support_and_dispute_conduct' => ['label' => 'Support and dispute conduct', 'weight' => 15],
                    'data_completeness' => ['label' => 'Data completeness', 'weight' => 20],
                ],
            ],
            'community' => [
                'label' => 'Community Participation',
                'weight' => 20,
                'factors' => [
                    'group_savings_participation' => ['label' => 'Group savings participation', 'weight' => 25],
                    'guarantor_reliability' => ['label' => 'Guarantor reliability', 'weight' => 25],
                    'membership_duration' => ['label' => 'Membership duration', 'weight' => 15],
                    'workplace_or_group_standing' => ['label' => 'Workplace or group standing', 'weight' => 20],
                    'community_obligation_performance' => ['label' => 'Community obligation performance', 'weight' => 15],
                ],
            ],
            'protection_asset' => [
                'label' => 'Protection & Asset Readiness',
                'weight' => 10,
                'factors' => [
                    'cover_continuity' => ['label' => 'Cover continuity', 'weight' => 30],
                    'asset_deposit_discipline' => ['label' => 'Asset deposit discipline', 'weight' => 25],
                    'asset_care_or_usage' => ['label' => 'Asset care or usage', 'weight' => 20],
                    'supplier_or_partner_confirmation' => ['label' => 'Supplier or partner confirmation', 'weight' => 25],
                ],
            ],
            'conduct' => [
                'label' => 'Responsible Use',
                'weight' => 10,
                'factors' => [
                    'fraud_risk_absence' => ['label' => 'Fraud risk absence', 'weight' => 35],
                    'complaints_resolution' => ['label' => 'Complaints resolution', 'weight' => 20],
                    'terms_adherence' => ['label' => 'Terms adherence', 'weight' => 25],
                    'financial_education_progress' => ['label' => 'Financial education progress', 'weight' => 20],
                ],
            ],
        ],
    ],
];
