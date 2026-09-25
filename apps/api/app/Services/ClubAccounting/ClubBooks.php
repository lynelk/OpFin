<?php

namespace App\Services\ClubAccounting;

use App\Models\ClubBook;
use App\Models\FinancialSpace;
use App\Models\FinancialSpaceTreasuryAccount;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ClubBooks
{
    public function __construct(private readonly ClubAccess $access, private readonly ClubLedger $ledger, private readonly AuditLogger $audit) {}

    public function create(FinancialSpace $space, User $actor, array $data): ClubBook
    {
        if (! in_array($this->access->role($space, $actor), ClubAccess::MAKER_ROLES, true)) {
            throw new AuthorizationException('Only an authorised club officer can set up accounting.');
        }
        $data = Validator::make($data, [
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/D'],
            'ownership_model' => ['required', 'in:capital_accounts,unitised'],
            'cutover_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'initial_unit_price_minor' => ['nullable', 'integer', 'min:1', 'max:9007199254740991'],
            'valuation_max_age_days' => ['required', 'integer', 'min:1', 'max:366'],
        ])->validate();
        if ($data['ownership_model'] === 'unitised' && empty($data['initial_unit_price_minor'])) {
            throw new InvalidArgumentException('A unitised book requires its approved initial unit price.');
        }

        return DB::transaction(function () use ($space, $actor, $data): ClubBook {
            FinancialSpace::query()->whereKey($space->id)->lockForUpdate()->firstOrFail();
            $this->access->role($space->fresh(), $actor);
            $existing = ClubBook::query()->where('financial_space_id', $space->id)->where('currency', $data['currency'])->first();
            if ($existing) {
                if ($existing->ownership_model !== $data['ownership_model']
                    || $existing->cutover_date->toDateString() !== $data['cutover_date']) {
                    throw new InvalidArgumentException('This Space already has a book with different opening terms.');
                }

                return $existing;
            }
            $book = ClubBook::create([
                'public_id' => (string) Str::uuid(), 'financial_space_id' => $space->id,
                'currency' => $data['currency'], 'ownership_model' => $data['ownership_model'],
                'status' => 'draft', 'cutover_date' => $data['cutover_date'],
                'initial_unit_price_minor' => $data['ownership_model'] === 'unitised' ? (int) $data['initial_unit_price_minor'] : null,
                'unit_scale' => 1000000, 'policy_version' => 1, 'created_by' => $actor->id,
                'policy' => ['valuation_max_age_days' => (int) $data['valuation_max_age_days'],
                    'cost_method' => 'weighted_average', 'trade_cost_policy' => 'capitalise_acquisition_expense_disposal',
                    'approval' => 'distinct_maker_checker', 'money_execution' => 'record_only',
                    'unit_rounding' => 'down_to_micro_unit_with_disclosed_remainder'],
            ]);
            $this->ledger->initialise($book);
            foreach (FinancialSpaceTreasuryAccount::query()->where('financial_space_id', $space->id)
                ->where('currency', $book->currency)->where('status', 'active')->get() as $account) {
                $this->linkTreasury($book, $account);
            }
            $this->audit->record('club.accounting.book_created', $actor, $book, [
                'financial_space_id' => $space->id, 'currency' => $book->currency,
                'ownership_model' => $book->ownership_model, 'opening_approval_required' => true,
            ]);

            return $book;
        });
    }

    public function linkTreasury(ClubBook $book, FinancialSpaceTreasuryAccount $account): object
    {
        if ((int) $account->financial_space_id !== (int) $book->financial_space_id
            || $account->currency !== $book->currency || $account->status !== 'active' || $account->deleted_at !== null) {
            throw new InvalidArgumentException('Treasury account, Financial Space and currency must match this book.');
        }
        $existing = DB::table('club_treasury_links')->where('treasury_account_id', $account->id)->first();
        if ($existing) {
            if ((int) $existing->book_id !== (int) $book->id) {
                throw new InvalidArgumentException('This treasury account belongs to another accounting book.');
            }

            return $existing;
        }
        if ($book->status !== 'draft' && $account->opening_balance_minor !== 0) {
            throw new InvalidArgumentException('A new non-zero opening account requires an explicit reviewed opening adjustment, not an inferred balance.');
        }
        $id = DB::table('club_accounts')->insertGetId([
            'book_id' => $book->id, 'code' => 'CASH-'.$account->id, 'name' => $account->account_name,
            'kind' => 'asset', 'controlled' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $link = DB::table('club_treasury_links')->insertGetId([
            'book_id' => $book->id, 'treasury_account_id' => $account->id, 'account_id' => $id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('club_treasury_links')->find($link);
    }
}
