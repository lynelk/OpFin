<?php

namespace App\Support;

/**
 * Scope-aware workspace operations. Do not confuse membership with financial
 * write authority, plan entitlement or regulatory/product activation.
 */
final class FinancialSpaceRolePolicy
{
    private const FINANCIAL_MANAGERS = ['owner', 'administrator', 'chairperson', 'treasurer', 'director'];

    public static function canManageFinancialRecords(string $type, string $role): bool
    {
        return in_array($role, self::FINANCIAL_MANAGERS, true);
    }

    public static function canManageMembers(string $role): bool
    {
        return in_array($role, self::FINANCIAL_MANAGERS, true);
    }

    public static function canManagePeople(string $type, string $role): bool
    {
        return $type === 'business' && in_array($role, ['owner', 'administrator', 'director'], true);
    }

    public static function label(string $type): string
    {
        return match ($type) {
            'personal' => 'Personal',
            'household' => 'Household',
            'savings_group' => 'Savings Groups',
            'sacco' => 'SACCO',
            'investment_club' => 'Investment Club',
            'business' => 'Business',
            'investment_fund' => 'Investment Fund',
            'partner' => 'Partner',
            'institution' => 'Institution',
            default => 'Organisation',
        };
    }
}
