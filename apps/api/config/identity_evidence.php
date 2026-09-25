<?php

return [
    // Reuse is opt-in and must have an approved provider-storage policy.
    // An unset interval means that no retention or freshness period was agreed.
    'enabled' => (bool) env('IDENTITY_EVIDENCE_REUSE_ENABLED', false),
    'policy_version' => env('IDENTITY_EVIDENCE_POLICY_VERSION', ''),
    'approval_reference' => env('IDENTITY_EVIDENCE_APPROVAL_REFERENCE', ''),
    'max_age_seconds' => (int) env('IDENTITY_EVIDENCE_MAX_AGE_SECONDS', 0),
    'refresh_after_seconds' => (int) env('IDENTITY_EVIDENCE_REFRESH_AFTER_SECONDS', 0),
    'retention_seconds' => (int) env('IDENTITY_EVIDENCE_RETENTION_SECONDS', 0),
    'consent_purpose' => 'identity_evidence_reuse',
];
