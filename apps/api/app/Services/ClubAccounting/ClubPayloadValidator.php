<?php

namespace App\Services\ClubAccounting;

use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class ClubPayloadValidator
{
    public function validate(string $type, array $payload): array
    {
        $positive = ['required', 'integer', 'min:1', 'max:9007199254740991'];
        $zero = ['nullable', 'integer', 'min:0', 'max:9007199254740991'];
        $id = ['required', 'integer', 'min:1'];
        $cash = ['treasury_account_id' => $id, 'treasury_transaction_id' => ['nullable', 'integer', 'min:1']];
        $rules = ['evidence_reference' => ['required', 'string', 'min:3', 'max:255', 'not_regex:/[\x00-\x1F]/'],
            'description' => ['nullable', 'string', 'max:300']];
        $specific = match ($type) {
            'opening' => [
                'members' => ['present', 'array', 'max:1000'], 'members.*' => ['array:user_id,capital_minor,units_micro'],
                'members.*.user_id' => $id, 'members.*.capital_minor' => ['required', 'integer', 'min:0', 'max:9007199254740991'],
                'members.*.units_micro' => $zero, 'balances' => ['present', 'array', 'max:500'],
                'balances.*' => ['array:account_code,direction,amount_minor'], 'balances.*.account_code' => ['required', 'string', 'max:80'],
                'balances.*.direction' => ['required', 'in:debit,credit'], 'balances.*.amount_minor' => $positive,
                'assets' => ['nullable', 'array', 'max:250'], 'assets.*' => ['array:name,asset_class,quantity_micro,cost_minor,carrying_value_minor'],
                'assets.*.name' => ['required', 'string', 'max:160'], 'assets.*.asset_class' => ['required', 'string', 'max:32'],
                'assets.*.quantity_micro' => $positive, 'assets.*.cost_minor' => ['required', 'integer', 'min:0', 'max:9007199254740991'],
                'assets.*.carrying_value_minor' => ['required', 'integer', 'min:0', 'max:9007199254740991'],
            ],
            'contribution' => $cash + ['member_user_id' => $id, 'amount_minor' => $positive, 'capital_call_id' => ['nullable', 'integer', 'min:1']],
            'redemption' => $cash + ['member_user_id' => $id, 'units_micro' => $zero, 'capital_minor' => $zero, 'withholding_minor' => $zero],
            'ownership_transfer' => ['from_user_id' => $id, 'to_user_id' => $id, 'units_micro' => $zero, 'capital_minor' => $zero],
            'allocate_result' => [],
            'capital_call' => ['member_user_ids' => ['required', 'array', 'min:1', 'max:1000'], 'member_user_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
                'amount_minor' => $positive, 'due_date' => ['required', 'date_format:Y-m-d']],
            'contribution_plan' => ['member_user_id' => $id, 'amount_minor' => $positive, 'due_day' => ['required', 'integer', 'min:1', 'max:31'],
                'first_due_month' => ['required', 'date_format:Y-m'], 'end_date' => ['nullable', 'date_format:Y-m-d']],
            'contribution_plan_state' => ['plan_id' => $id, 'status' => ['required', 'in:active,paused,closed']],
            'capital_call_cancel' => ['capital_call_id' => $id],
            'asset_acquisition' => $cash + ['asset_id' => ['nullable', 'integer', 'min:1'], 'name' => ['required_without:asset_id', 'string', 'max:160'],
                'asset_class' => ['required_without:asset_id', 'string', 'max:32'], 'quantity_micro' => $positive, 'amount_minor' => $positive, 'fee_minor' => $zero],
            'asset_valuation' => ['asset_id' => $id, 'carrying_value_minor' => ['required', 'integer', 'min:0', 'max:9007199254740991']],
            'asset_disposal' => $cash + ['asset_id' => $id, 'quantity_micro' => $positive, 'amount_minor' => $positive, 'fee_minor' => $zero],
            'asset_split' => ['asset_id' => $id, 'numerator' => ['required', 'integer', 'min:1', 'max:1000000'], 'denominator' => ['required', 'integer', 'min:1', 'max:1000000']],
            'cash_entry' => $cash + ['amount_minor' => $positive, 'direction' => ['required', 'in:credit,debit'],
                'allocations' => ['required', 'array', 'min:1', 'max:250'], 'allocations.*' => ['array:account_code,amount_minor,member_user_id'],
                'allocations.*.account_code' => ['required', 'string', 'max:80'], 'allocations.*.amount_minor' => $positive,
                'allocations.*.member_user_id' => ['nullable', 'integer', 'min:1']],
            'journal' => ['description' => ['required', 'string', 'max:300'], 'entries' => ['required', 'array', 'min:2', 'max:250'],
                'entries.*' => ['array:account_code,direction,amount_minor'], 'entries.*.account_code' => ['required', 'string', 'max:80'],
                'entries.*.direction' => ['required', 'in:debit,credit'], 'entries.*.amount_minor' => $positive],
            'reverse_journal' => ['original_journal_id' => $id, 'description' => ['required', 'string', 'min:5', 'max:300'],
                'treasury_transaction_id' => ['nullable', 'integer', 'min:1']],
            'distribution_declare' => ['amount_minor' => $positive],
            'distribution_pay' => $cash + ['member_user_id' => $id, 'distribution_id' => $id, 'amount_minor' => $positive, 'withholding_minor' => $zero],
            'account_create' => ['code' => ['required', 'string', 'regex:/^[A-Z][A-Z0-9_-]{2,39}$/D'],
                'name' => ['required', 'string', 'max:160'], 'kind' => ['required', 'in:asset,liability,equity,income,expense']],
            'treasury_link' => ['treasury_account_id' => $id],
            'close_period' => ['closed_through' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']],
            default => throw new InvalidArgumentException('Unknown club accounting instruction type.'),
        };
        $rules = array_replace($rules, $specific);
        $allowed = array_unique(array_map(static fn (string $key): string => explode('.', $key)[0], array_keys($rules)));
        if (array_diff(array_keys($payload), $allowed) !== []) {
            throw new InvalidArgumentException('The payload contains fields not supported by this instruction type.');
        }
        $clean = Validator::make($payload, $rules)->validate();
        return $this->normalise($clean);
    }

    private function normalise(array $values): array
    {
        foreach ($values as $key => &$value) {
            if (is_array($value)) {
                $value = $this->normalise($value);
                if ($key === 'member_user_ids') {
                    foreach ($value as &$id) {
                        if (is_bool($id) || is_float($id) || ! preg_match('/^[0-9]+$/D', (string) $id)) {
                            throw new InvalidArgumentException('Member identifiers must be integers.');
                        }
                        $id = (int) $id;
                    }
                    unset($id);
                }
            } elseif ($value !== null && (str_ends_with((string) $key, '_minor') || str_ends_with((string) $key, '_micro')
                || str_ends_with((string) $key, '_id') || in_array($key, ['due_day', 'numerator', 'denominator'], true))) {
                if (is_bool($value) || is_float($value) || ! preg_match('/^[0-9]+$/D', (string) $value)) {
                    throw new InvalidArgumentException('Amounts, quantities and identifiers must be integers, never floating-point values.');
                }
                $value = (int) $value;
            }
        }
        unset($value);
        return $values;
    }
}
