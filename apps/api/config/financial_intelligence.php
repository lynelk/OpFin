<?php

return [
    // Activation is an operational release decision, not an inference from a successful import.
    'enabled' => env('OPFIN_FINANCIAL_INTELLIGENCE_ENABLED', false),
    'entitlement' => 'financial_intelligence',
    'max_upload_bytes' => 25 * 1024 * 1024,
    'max_loans' => 50000,
    'management_npl_days' => 90,
    'stale_after_hours' => 48,
    'statement_disk' => 'local',
    'statement_prefix' => 'financial-intelligence/evidence',
    'country' => 'UG',
    'report_retention_days' => 365,
];
