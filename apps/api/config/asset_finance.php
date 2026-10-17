<?php

return [
    'jurisdiction' => 'UG',
    'currency' => 'UGX',

    // Uganda launch pack. These are product-control classes, not regulator/provider approvals.
    'verticals' => [
        'device' => [
            'asset_families' => ['device'],
            'product_families' => ['device_finance', 'asset_finance'],
            'pre_approval_evidence' => ['merchant_quote', 'fraud_screen', 'warranty_terms'],
            'activation_evidence' => ['purchase_invoice', 'possession_activation', 'protection_confirmation'],
        ],
        'auto' => [
            'asset_families' => ['vehicle'],
            'product_families' => ['vehicle_finance', 'asset_finance'],
            'pre_approval_evidence' => ['dealer_quote', 'valuation', 'inspection', 'registry_check', 'protection_quote'],
            'activation_evidence' => ['supplier_invoice', 'handover_registration', 'protection_confirmation'],
        ],
        'productive' => [
            'asset_families' => ['productive'],
            'product_families' => ['productive_asset_finance', 'asset_finance'],
            'pre_approval_evidence' => ['supplier_quote', 'valuation', 'inspection', 'protection_quote'],
            'activation_evidence' => ['supplier_invoice', 'handover', 'protection_confirmation'],
            // Asset-specific evidence for solar, machinery and agricultural equipment.
            // These extend the existing productive-asset lifecycle, not a new money rail.
            'class_evidence' => [
                'solar_system' => [
                    'pre_approval' => ['site_suitability'],
                    'activation' => ['installation_commissioning'],
                ],
                'machinery' => [
                    'pre_approval' => ['equipment_condition_report'],
                    'activation' => ['operator_handover'],
                ],
                'agricultural_equipment' => [
                    'pre_approval' => ['seasonal_use_assessment'],
                    'activation' => ['field_delivery_confirmation'],
                ],
            ],
        ],
    ],

    'evidence_types' => [
        'merchant_quote', 'dealer_quote', 'supplier_quote', 'purchase_invoice', 'supplier_invoice',
        'valuation', 'inspection', 'registry_check', 'fraud_screen', 'warranty_terms',
        'possession_activation', 'handover_registration', 'handover',
        'protection_quote', 'protection_confirmation',
        'site_suitability', 'installation_commissioning', 'equipment_condition_report',
        'operator_handover', 'seasonal_use_assessment', 'field_delivery_confirmation',
    ],

    // Device controls remain deliberately unavailable until separately approved legal/security/OEM rails exist.
    'remote_device_controls_enabled' => false,
];
