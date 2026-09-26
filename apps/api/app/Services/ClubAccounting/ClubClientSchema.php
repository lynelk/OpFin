<?php

namespace App\Services\ClubAccounting;

/** Additive client metadata; execution continues to use the existing payload validator. */
class ClubClientSchema extends ClubSchema
{
    public function describe(): array
    {
        $schema = parent::describe();
        $schema['version'] = '1.1.0';
        $schema['request_recovery'] = 'Server-persisted exact request; resume and acknowledge before replacing it.';
        foreach ($schema['operations'] as &$operation) {
            if ($operation['type'] === 'reverse_journal') {
                array_splice($operation['fields'], 1, 0, [[
                    'key' => 'treasury_transaction_id', 'label' => 'Actual matching opposite cashbook entry, required for an external cash reversal',
                    'type' => 'integer', 'required' => false, 'minimum' => 1,
                ]]);
            }
        }
        unset($operation);
        return $schema;
    }
}
