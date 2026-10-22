<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use App\Services\MemberSettlementJournalService;
use App\Support\FinancialSpaceRolePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SaccoSettlementController extends Controller
{
    public function __construct(private readonly MemberSettlementJournalService $journals) {}

    public function allocate(Request $request, FinancialSpace $space, int $account): JsonResponse
    {
        $this->officer($request, $space);
        $data = $request->validate([
            'action_id' => ['required', 'integer', 'min:1'],
            'treasury_transaction_id' => ['required', 'integer', 'min:1'],
            'movement_type' => ['required', Rule::in(['deposit', 'withdrawal'])],
        ]);

        $movement = DB::transaction(function () use ($request, $space, $account, $data) {
            $memberAccount = DB::table('sacco_member_accounts')
                ->where('financial_space_id', $space->id)
                ->where('id', $account)
                ->where('status', 'active')
                ->lockForUpdate()->first();
            abort_unless($memberAccount, 404);
            $product = DB::table('sacco_products')
                ->where('financial_space_id', $space->id)
                ->where('id', $memberAccount->sacco_product_id)
                ->where('status', 'active')->first();
            abort_unless($product && $product->currency === $space->currency, 409);

            $attestation = DB::table('sacco_regulatory_attestations')
                ->where('financial_space_id', $space->id)
                ->where('id', $product->activation_attestation_id)
                ->where('jurisdiction', $space->country)
                ->where('review_status', 'reviewed')
                ->whereDate('valid_from', '<=', today()->toDateString())
                ->where(function ($query) {
                    $query->whereNull('expires_on')
                        ->orWhereDate('expires_on', '>=', today()->toDateString());
                })->first();
            abort_unless($attestation, 409, 'Current regulatory evidence review is required before postings.');
            $permitted = json_decode((string) $attestation->permitted_product_types, true);
            abort_unless(is_array($permitted) && in_array($product->product_type, $permitted, true), 409);

            $action = DB::table('financial_space_action_intents')
                ->where('id', $data['action_id'])
                ->where('financial_space_id', $space->id)->first();
            abort_unless($action, 404);
            $type = $data['movement_type'];
            abort_unless(
                (int) $action->initiated_by_user_id === (int) $memberAccount->user_id
                && $action->direction === ($type === 'deposit' ? 'collection' : 'disbursement')
                && $action->action_type === ($type === 'deposit' ? 'contribution' : 'withdrawal'),
                403,
                'Member initiation and the requested movement direction must match.'
            );
            abort_unless(
                DB::table('financial_space_memberships')
                    ->where('financial_space_id', $space->id)
                    ->where('user_id', $memberAccount->user_id)
                    ->where('status', 'active')->whereNull('deleted_at')->exists(),
                409,
                'The SACCO member is no longer active.'
            );
            abort_unless((int) $action->principal_amount_minor > 0, 422);
            $amount = (int) $action->principal_amount_minor;

            if ($type === 'withdrawal') {
                abort_unless($product->withdrawable && $product->product_type !== 'shares', 409);
                abort_unless(
                    $amount <= (int) $memberAccount->balance_minor - (int) $memberAccount->held_minor
                    && (int) $memberAccount->balance_minor - $amount >= (int) $product->minimum_balance_minor,
                    409,
                    'Insufficient available funds, held balance or minimum-balance restriction.'
                );
            }

            $isShares = $product->product_type === 'shares';
            if ($isShares) {
                abort_unless($type === 'deposit', 409);
                $rules = json_decode((string) $product->rules, true);
                $price = (int) ($rules['share_price_minor'] ?? 0);
                abort_unless($price > 0 && $amount % $price === 0, 422, 'Share price must divide the deposit exactly.');
                $shareCount = intdiv($amount, $price);
                abort_unless($shareCount > 0 && $shareCount <= 1000000000, 422);
            }

            $liability = $isShares ? 'sacco_member_share_capital' : 'sacco_member_deposits_liability';
            $journal = $this->journals->post(
                $space,
                (int) $data['action_id'],
                (int) $data['treasury_transaction_id'],
                'sacco_member_cash',
                'account:'.$account.':'.$type,
                'sacco_member_'.$type,
                $type === 'deposit'
                    ? ['code' => 'sacco_treasury_cash', 'member' => null]
                    : ['code' => $liability, 'member' => (int) $memberAccount->user_id],
                $type === 'deposit'
                    ? ['code' => $liability, 'member' => (int) $memberAccount->user_id]
                    : ['code' => 'sacco_treasury_cash', 'member' => null],
                $request->user()->id,
            );

            $newBalance = (int) $memberAccount->balance_minor
                + ($type === 'deposit' ? $amount : -$amount);
            abort_unless($newBalance >= 0, 409);
            DB::table('sacco_member_accounts')
                ->where('financial_space_id', $space->id)
                ->where('id', $account)
                ->update(['balance_minor' => $newBalance, 'updated_at' => now()]);

            if ($isShares) {
                $shareRow = DB::table('sacco_share_register')
                    ->where('financial_space_id', $space->id)
                    ->where('user_id', $memberAccount->user_id)
                    ->lockForUpdate()->first();
                $newShares = ($shareRow ? (int) $shareRow->shares_micro : 0) + $shareCount * 1000000;
                $newCapital = ($shareRow ? (int) $shareRow->paid_up_capital_minor : 0) + $amount;
                DB::table('sacco_share_register')->updateOrInsert([
                    'financial_space_id' => $space->id,
                    'user_id' => $memberAccount->user_id,
                ], [
                    'shares_micro' => $newShares,
                    'paid_up_capital_minor' => $newCapital,
                    'currency' => $space->currency,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            }

            $id = DB::table('sacco_member_cash_movements')->insertGetId([
                'financial_space_id' => $space->id,
                'member_account_id' => $account,
                'member_user_id' => $memberAccount->user_id,
                'action_intent_id' => $data['action_id'],
                'treasury_transaction_id' => $data['treasury_transaction_id'],
                'movement_type' => $type,
                'amount_minor' => $amount,
                'currency' => $space->currency,
                'allocated_by_user_id' => $request->user()->id,
                'allocated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'movement' => DB::table('sacco_member_cash_movements')->find($id),
                'member_balance_minor' => $newBalance,
                'journal_id' => $journal->id,
            ];
        }, 3);

        return response()->json(['data' => $movement]);
    }

    private function officer(Request $request, FinancialSpace $space): void
    {
        abort_unless($space->type === 'sacco' && $space->status === 'active', 404);
        $role = DB::table('financial_space_memberships')
            ->where('financial_space_id', $space->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->value('role');
        abort_unless(
            is_string($role) && FinancialSpaceRolePolicy::canManageFinancialRecords('sacco', $role),
            403
        );
    }
}
