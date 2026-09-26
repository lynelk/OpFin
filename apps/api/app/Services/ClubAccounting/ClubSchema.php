<?php

namespace App\Services\ClubAccounting;

class ClubSchema
{
    public function describe(): array
    {
        $field = static fn (string $key, string $label, string $type = 'integer', bool $required = true, array $extra = []): array =>
            array_merge(['key' => $key, 'label' => $label, 'type' => $type, 'required' => $required], $extra);
        $member = $field('member_user_id', 'Member', 'integer', true, ['source' => 'members']);
        $amount = $field('amount_minor', 'Amount in currency minor units', 'integer', true, ['minimum' => 1, 'maximum' => 9007199254740991]);
        $cash = [$field('treasury_account_id', 'Treasury account', 'integer', true, ['source' => 'treasury_links']),
            $field('treasury_transaction_id', 'Existing matching cashbook entry, when already recorded', 'integer', false)];
        $asset = $field('asset_id', 'Investment holding', 'integer', true, ['source' => 'assets']);
        $quantity = $field('quantity_micro', 'Quantity in micro-units', 'integer', true, ['minimum' => 1, 'scale' => 1000000]);
        $capital = [$field('units_micro', 'Ownership micro-units for a unitised book', 'integer', false),
            $field('capital_minor', 'Capital interest for a capital-account book', 'integer', false)];
        $operations = [
            'opening' => ['title' => 'Approve opening accounts', 'effect' => 'Reconcile source balances and explicit member ownership; does not invent historical contributions.', 'fields' => [
                $field('members', 'Opening member allocations', 'array', true, ['items' => [
                    $field('user_id', 'Member', 'integer', true, ['source' => 'members']),
                    $field('capital_minor', 'Opening member capital'), $field('units_micro', 'Opening ownership micro-units', 'integer', false)]]),
                $field('balances', 'Opening non-member account balances', 'array', true, ['items' => [
                    $field('account_code', 'Account code', 'string', true, ['source' => 'accounts']),
                    $field('direction', 'Debit or credit', 'string', true, ['enum' => ['debit', 'credit']]), $amount]]),
                $field('assets', 'Opening investment holdings', 'array', false, ['items' => [
                    $field('name', 'Investment name', 'string'), $field('asset_class', 'Asset class', 'string'), $quantity,
                    $field('cost_minor', 'Original cost'), $field('carrying_value_minor', 'Recorded opening valuation')]]),
            ]],
            'contribution' => ['title' => 'Record a member contribution', 'effect' => 'Record cash and capital once; unitised subscriptions use the current recorded NAV.', 'fields' => array_merge([$member, $amount], $cash, [$field('capital_call_id', 'Apply against a capital call', 'integer', false, ['source' => 'capital_calls'])])],
            'redemption' => ['title' => 'Redeem member ownership', 'effect' => 'Calculate the NAV-based entitlement, remove exact ownership and record the approved payment; no payment provider is invoked.', 'fields' => array_merge([$member], $capital, $cash, [$field('withholding_minor', 'Approved withholding amount', 'integer', false)])],
            'allocate_result' => ['title' => 'Allocate surplus or loss', 'effect' => 'Capital-account books allocate the recorded result to existing members before a new contribution.', 'fields' => []],
            'ownership_transfer' => ['title' => 'Transfer existing ownership', 'effect' => 'Transfer units or capital between members without implying that OpFin executed a payment.', 'fields' => array_merge([
                $field('from_user_id', 'Transfer from', 'integer', true, ['source' => 'members']),
                $field('to_user_id', 'Transfer to', 'integer', true, ['source' => 'members'])], $capital)],
            'capital_call' => ['title' => 'Issue contribution calls', 'effect' => 'Create member contribution requests, not loan receivables or credit defaults.', 'fields' => [
                $field('member_user_ids', 'Members', 'array', true, ['items_type' => 'integer', 'source' => 'members']), $amount, $field('due_date', 'Due date', 'date')]],
            'contribution_plan' => ['title' => 'Schedule monthly contributions', 'effect' => 'Generate due calls on the approved monthly day, clamped to the month end where necessary.', 'fields' => [
                $member, $amount, $field('first_due_month', 'First due month', 'month'), $field('due_day', 'Due day', 'integer', true, ['minimum' => 1, 'maximum' => 31]), $field('end_date', 'End date', 'date', false)]],
            'contribution_plan_state' => ['title' => 'Pause, resume or end a plan', 'effect' => 'Change future call generation without deleting already-issued calls.', 'fields' => [
                $field('plan_id', 'Contribution plan', 'integer', true, ['source' => 'contribution_plans']), $field('status', 'New state', 'string', true, ['enum' => ['active', 'paused', 'closed']])]],
            'capital_call_cancel' => ['title' => 'Cancel an unpaid call', 'effect' => 'Retain the unpaid call and its cancellation evidence.', 'fields' => [$field('capital_call_id', 'Capital call', 'integer', true, ['source' => 'capital_calls'])]],
            'asset_acquisition' => ['title' => 'Record an investment acquisition', 'effect' => 'Capitalise the evidenced purchase and acquisition costs using integer amounts.', 'fields' => array_merge([
                $field('asset_id', 'Existing investment, or leave empty for a new holding', 'integer', false, ['source' => 'assets']),
                $field('name', 'New investment name', 'string', false), $field('asset_class', 'New asset class', 'string', false), $quantity, $amount,
                $field('fee_minor', 'Acquisition costs', 'integer', false)], $cash)],
            'asset_valuation' => ['title' => 'Update a holding valuation', 'effect' => 'Append the evidenced fair-value change and preserve prior valuations.', 'fields' => [$asset, $field('carrying_value_minor', 'Total current recorded value', 'integer', true, ['minimum' => 0])]],
            'asset_disposal' => ['title' => 'Record an investment disposal', 'effect' => 'Release weighted-average cost and carrying value; recognise the realised result and disposal fees.', 'fields' => array_merge([$asset, $quantity, $amount, $field('fee_minor', 'Disposal costs', 'integer', false)], $cash)],
            'asset_split' => ['title' => 'Record a split or consolidation', 'effect' => 'Change quantity exactly while preserving cost and carrying value; no fractional entitlement is invented.', 'fields' => [$asset,
                $field('numerator', 'New quantity ratio numerator', 'integer', true, ['minimum' => 1, 'maximum' => 1000000]),
                $field('denominator', 'Ratio denominator', 'integer', true, ['minimum' => 1, 'maximum' => 1000000])]],
            'cash_entry' => ['title' => 'Classify income, an expense or another cash movement', 'effect' => 'Post a recorded treasury entry exactly once to the selected ordinary accounts.', 'fields' => array_merge([
                $field('direction', 'Cash receipt or payment', 'string', true, ['enum' => ['credit', 'debit']]), $amount], $cash, [
                    $field('allocations', 'Account allocation', 'array', true, ['items' => [
                        $field('account_code', 'Account code', 'string', true, ['source' => 'accounts']), $amount,
                        $field('member_user_id', 'Related member, when applicable', 'integer', false, ['source' => 'members'])]])])],
            'journal' => ['title' => 'Record a non-cash adjustment', 'effect' => 'Post a balanced, evidenced journal without bypassing controlled capital, holdings, cash or distributions.', 'fields' => [
                $field('entries', 'Journal entries', 'array', true, ['items' => [$field('account_code', 'Account code', 'string', true, ['source' => 'accounts']),
                    $field('direction', 'Debit or credit', 'string', true, ['enum' => ['debit', 'credit']]), $amount]])]],
            'reverse_journal' => ['title' => 'Reverse an ordinary journal', 'effect' => 'Append an equal opposite ordinary journal or classified cash entry. The source is not changed or deleted.', 'fields' => [$field('original_journal_id', 'Original ordinary journal')]],
            'distribution_declare' => ['title' => 'Declare an income distribution', 'effect' => 'Freeze member entitlements using the record-date ownership and exact largest-remainder allocation.', 'fields' => [$amount]],
            'distribution_pay' => ['title' => 'Record a distribution payment', 'effect' => 'Reduce the exact member entitlement and record cash and approved withholding separately.', 'fields' => array_merge([$member,
                $field('distribution_id', 'Declared distribution', 'integer', true, ['source' => 'distributions']), $amount,
                $field('withholding_minor', 'Approved withholding', 'integer', false)], $cash)],
            'account_create' => ['title' => 'Add an ordinary accounting account', 'effect' => 'Create a book-specific account without replacing reserved control accounts.', 'fields' => [
                $field('code', 'Unique account code', 'string'), $field('name', 'Account name', 'string'),
                $field('kind', 'Account type', 'string', true, ['enum' => ['asset', 'liability', 'equity', 'income', 'expense']])]],
            'treasury_link' => ['title' => 'Link another treasury account', 'effect' => 'Link an account in the same Space and currency. New non-zero opening accounts require a separately reviewed opening basis.', 'fields' => [$field('treasury_account_id', 'Treasury account identifier')]],
            'close_period' => ['title' => 'Close an accounting period', 'effect' => 'Freeze the trial balance and prevent backdating into the closed period. Unposted cashbook items and pending instructions must be resolved.', 'fields' => [$field('closed_through', 'Close through date', 'date')]],
        ];
        foreach ($operations as $type => &$operation) {
            $operation['type'] = $type;
            $operation['fields'][] = $field('evidence_reference', 'Source evidence reference', 'string', true, ['maxLength' => 255]);
            $operation['fields'][] = $field('description', 'Description or correction reason', 'string', in_array($type, ['journal', 'reverse_journal'], true), ['maxLength' => 300]);
            $operation['approval'] = 'distinct_maker_checker';
            $operation['provider_execution'] = false;
        }
        unset($operation);

        return ['version' => '1.0.0', 'operations' => array_values($operations),
            'instruction_envelope' => ['type', 'business_date', 'idempotency_key', 'payload'],
            'approval_payload' => ['payload_hash'], 'rejection_payload' => ['reason'],
            'money_type' => 'integer minor units in the selected currency', 'unit_scale' => 1000000,
            'states' => ['pending', 'approved', 'rejected', 'cancelled'],
            'preview' => 'Transactional simulation only; writes and simulated identifiers are rolled back.',
            'identity' => 'Book, Space, actor, source instruction and idempotency identity are enforced server-side.',
            'accounting_boundary' => 'Separate club-owned books. No club asset, capital or investment income is recorded as OpFin corporate income.',
            'approval_is_not_payment_execution' => true];
    }
}
