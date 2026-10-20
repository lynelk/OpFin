<?php

namespace App\Support;

/**
 * Scope-aware workspace operations. Do not confuse membership with financial
 * write authority, plan entitlement or regulatory/product activation.
 */
final class FinancialSpaceRolePolicy
{
    private const FINANCIAL_VIEWERS = [
        'owner', 'administrator', 'chairperson', 'treasurer', 'director',
        'accountant', 'finance_manager', 'auditor', 'manager',
    ];

    private const FINANCIAL_MANAGERS = ['owner', 'administrator', 'chairperson', 'treasurer', 'director'];

    public static function canViewFinancialRecords(string $type, string $role): bool
    {
        // Corporate employment membership is NOT blanket permission to browse
        // employer balances, payroll funding, debts, or owned assets.
        if (in_array($type, ['business', 'institution', 'investment_fund', 'partner'], true)) {
            return in_array($role, self::FINANCIAL_VIEWERS, true);
        }

        // A personal Space is only available to its owner. Other Space types
        // rely on explicit, active membership and permit member visibility.
        if ($type === 'personal') {
            return $role === 'owner';
        }

        return $role !== '';
    }

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
