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
    // PDF analysis runs on the queue under these bounds; larger documents need a shorter period or the CSV export.
    'statement_pdf_max_bytes' => 10 * 1024 * 1024,
    'statement_pdf_max_pages' => 200,
    'statement_pdf_decode_limit_bytes' => 64 * 1024 * 1024,
    'statement_pdf_max_lines' => 20000,
    // Days after analysis permission expires or is withdrawn before the original and its analysis are purged.
    // A legal hold defers purging. Confirm the period with legal/privacy before launch.
    'statement_retention_days' => (int) env('OPFIN_STATEMENT_RETENTION_DAYS', 90),
    'country' => 'UG',
    'report_retention_days' => 365,
];
