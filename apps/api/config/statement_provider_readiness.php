<?php

/*
 * Candidate Uganda providers. These are NOT issuer attestations.
 * Each adapter and authenticity channel must be evidenced against authorised
 * redacted samples before a statement can acquire verified issuer status.
 */
return [
    'UG' => [
        'mtn_momo_uganda' => [
            'layout_sample_evidence' => 'not_received',
            'independent_issuer_authenticity' => 'not_verified',
            'production_accepted' => false,
        ],
        'airtel_money_uganda' => [
            'layout_sample_evidence' => 'not_received',
            'independent_issuer_authenticity' => 'not_verified',
            'production_accepted' => false,
        ],
        'licensed_banks' => [
            'layout_sample_evidence' => 'issuer_specific_samples_required',
            'independent_issuer_authenticity' => 'not_verified',
            'production_accepted' => false,
        ],
    ],
];
