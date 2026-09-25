<?php

namespace App\Services;

class LenderAuthorityEvidence
{
    public static function complete(?array $evidence): bool
    {
        if (! $evidence) {
            return false;
        }
        if (filled($evidence['licence_number'] ?? null) && filled($evidence['licence_authority'] ?? null)) {
            return true;
        }
        if (blank($evidence['authority_reference'] ?? null)) {
            return false;
        }

        return match ($evidence['authority_basis'] ?? null) {
            'licensed' => filled($evidence['regulator'] ?? null),
            'other_authority', 'exempt' => true,
            default => false,
        };
    }
}
