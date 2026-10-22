<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClubBook;
use App\Models\FinancialSpace;
use App\Services\ClubAccounting\ClubAccess;
use App\Services\ClubAccounting\ClubInstructions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ClubMigrationController extends Controller
{
    private const COLUMNS = [
        'record_type', 'user_id', 'capital_minor', 'units_micro',
        'account_code', 'direction', 'amount_minor', 'name', 'asset_class',
        'quantity_micro', 'cost_minor', 'carrying_value_minor',
    ];

    public function __construct(
        private readonly ClubAccess $access,
        private readonly ClubInstructions $instructions
    ) {}

    public function template(Request $request, FinancialSpace $space, int $book): JsonResponse
    {
        $record = $this->book($space, $book, $request);
        $members = DB::table('financial_space_memberships as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.financial_space_id', $space->id)
            ->where('m.status', 'active')
            ->whereNull('m.deleted_at')
            ->whereNull('u.deleted_at')
            ->select('u.id as user_id', 'm.role')->orderBy('u.id')->get();

        return response()->json(['data' => [
            'book_id' => $record->id,
            'currency' => $record->currency,
            'ownership_model' => $record->ownership_model,
            'cutover_date' => $record->cutover_date->toDateString(),
            'header' => self::COLUMNS,
            'allowed_record_types' => ['member', 'balance', 'asset'],
            'active_members' => $members,
            'note' => 'Use integer currency minor units (UGX whole shillings). Rows are opening records, never historical synthetic payments. An independent officer must approve the generated opening instruction.',
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function preview(Request $request, FinancialSpace $space, int $book): JsonResponse
    {
        $record = $this->book($space, $book, $request);
        $parsed = $this->parse($request, $record);

        return response()->json(['data' => $this->safePreview($parsed)]);
    }

    public function submit(Request $request, FinancialSpace $space, int $book): JsonResponse
    {
        $record = $this->book($space, $book, $request);
        $parsed = $this->parse($request, $record);
        $instruction = $this->instructions->submit(
            $record,
            $request->user(),
            [
                'type' => 'opening',
                'business_date' => $record->cutover_date->toDateString(),
                'idempotency_key' => 'migration-'.$parsed['source_hash'],
                'payload' => $parsed['opening_payload'],
            ]
        );

        return response()->json(['data' => [
            'instruction' => $instruction,
            'source_hash' => $parsed['source_hash'],
            'row_count' => $parsed['row_count'],
            'status' => 'awaiting_independent_checker',
            'activation' => 'Use the existing Club Accounting instruction preview and approval workflow.',
            'posted' => false,
        ]], 201);
    }

    private function book(FinancialSpace $space, int $id, Request $request): ClubBook
    {
        abort_unless($space->type === 'investment_club', 404);
        $book = ClubBook::query()
            ->where('financial_space_id', $space->id)
            ->where('id', $id)->firstOrFail();
        $this->access->book($book, $request->user(), write: true);
        abort_unless($book->status === 'draft', 409, 'Opening CSV imports are allowed only before book activation.');

        return $book;
    }

    private function parse(Request $request, ClubBook $book): array
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:csv,txt'],
        ]);
        $file = $request->file('file');
        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false || strlen($bytes) < 10) {
            throw new InvalidArgumentException('Unable to read the source workbook CSV.');
        }
        $hash = hash('sha256', $bytes);
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, $bytes);
        rewind($handle);
        $headers = fgetcsv($handle);
        if (! is_array($headers)) {
            fclose($handle);
            throw new InvalidArgumentException('CSV header missing.');
        }
        $headers = array_map(static fn ($value) => trim((string) $value), $headers);
        if ($headers !== self::COLUMNS) {
            fclose($handle);
            throw new InvalidArgumentException('Use the exact template header and column order. Unknown columns are not silently dropped.');
        }

        $members = [];
        $balances = [];
        $assets = [];
        $lineNumber = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $lineNumber++;
            if (count($values) === 1 && trim((string) $values[0]) === '') {
                continue;
            }
            if ($lineNumber > 2001) {
                fclose($handle);
                throw new InvalidArgumentException('Split the opening migration into batches below 2,000 rows.');
            }
            if (count($values) !== count(self::COLUMNS)) {
                fclose($handle);
                throw new InvalidArgumentException('Incorrect CSV field count on line '.$lineNumber);
            }
            $row = array_combine(self::COLUMNS, array_map(
                static fn ($value) => trim((string) $value),
                $values
            ));
            $kind = $row['record_type'];
            if ($kind === 'member') {
                $user = $this->integer($row['user_id'], $lineNumber);
                abort_unless(
                    DB::table('financial_space_memberships')
                        ->where('financial_space_id', $book->financial_space_id)
                        ->where('user_id', $user)
                        ->where('status', 'active')
                        ->whereNull('deleted_at')->exists(),
                    422,
                    'Unknown or inactive investment-club member on line '.$lineNumber
                );
                abort_if(isset($members[$user]), 422, 'Duplicate member opening row on line '.$lineNumber);
                $member = [
                    'user_id' => $user,
                    'capital_minor' => $this->integer($row['capital_minor'], $lineNumber, allowZero: true),
                ];
                if ($row['units_micro'] !== '') {
                    $member['units_micro'] = $this->integer($row['units_micro'], $lineNumber, allowZero: true);
                }
                $members[$user] = $member;
            } elseif ($kind === 'balance') {
                abort_unless(in_array($row['direction'], ['debit', 'credit'], true), 422);
                abort_unless($row['account_code'] !== '' && strlen($row['account_code']) <= 80, 422);
                abort_unless(
                    DB::table('club_accounts')->where('book_id', $book->id)
                        ->where('code', $row['account_code'])->exists(),
                    422,
                    'Unrecognised book account on line '.$lineNumber
                );
                $balances[] = [
                    'account_code' => $row['account_code'],
                    'direction' => $row['direction'],
                    'amount_minor' => $this->integer($row['amount_minor'], $lineNumber),
                ];
            } elseif ($kind === 'asset') {
                abort_unless($row['name'] !== '' && $row['asset_class'] !== '', 422);
                $assets[] = [
                    'name' => $row['name'],
                    'asset_class' => $row['asset_class'],
                    'quantity_micro' => $this->integer($row['quantity_micro'], $lineNumber),
                    'cost_minor' => $this->integer($row['cost_minor'], $lineNumber, allowZero: true),
                    'carrying_value_minor' => $this->integer($row['carrying_value_minor'], $lineNumber, allowZero: true),
                ];
            } else {
                fclose($handle);
                throw new InvalidArgumentException('Unsupported record_type on line '.$lineNumber);
            }
        }
        fclose($handle);

        $payload = [
            'members' => array_values($members),
            'balances' => $balances,
            'assets' => $assets,
            'evidence_reference' => 'migration-csv-sha256:'.$hash,
            'description' => 'Historical opening balances migrated from independently reviewed CSV evidence.',
        ];
        // Invoke the existing full opening contract before accepting anything;
        // this does not post, value holdings or generate historical cash events.
        $payload = $this->instructions->validatePayload('opening', $payload);

        return [
            'source_hash' => $hash,
            'row_count' => $lineNumber - 1,
            'opening_payload' => $payload,
        ];
    }

    private function integer(string $value, int $line, bool $allowZero = false): int
    {
        abort_unless(preg_match('/^(0|[1-9][0-9]*)$/D', $value) === 1, 422, 'Invalid integer amount on line '.$line);
        $parsed = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $allowZero ? 0 : 1, 'max_range' => 9007199254740991],
        ]);
        abort_unless($parsed !== false, 422, 'Out-of-range integer on line '.$line);

        return $parsed;
    }

    private function safeSum(array $rows, string $field): int
    {
        $total = 0;
        foreach ($rows as $row) {
            $amount = (int) $row[$field];
            abort_unless(
                $amount >= 0 && $amount <= 9007199254740991 - $total,
                422,
                'Opening total exceeds safe currency precision.'
            );
            $total += $amount;
        }

        return $total;
    }

    private function safePreview(array $parsed): array
    {
        $payload = $parsed['opening_payload'];
        $memberCapital = $this->safeSum($payload['members'], 'capital_minor');
        $assetCarrying = $this->safeSum($payload['assets'], 'carrying_value_minor');
        $debits = 0;
        $credits = 0;
        foreach ($payload['balances'] as $row) {
            if ($row['direction'] === 'debit') {
                abort_unless($debits <= 9007199254740991 - $row['amount_minor'], 422, 'Debit total out of range.');
                $debits += $row['amount_minor'];
            } else {
                abort_unless($credits <= 9007199254740991 - $row['amount_minor'], 422, 'Credit total out of range.');
                $credits += $row['amount_minor'];
            }
        }

        return [
            'source_hash' => $parsed['source_hash'],
            'row_count' => $parsed['row_count'],
            'member_count' => count($payload['members']),
            'balance_count' => count($payload['balances']),
            'asset_count' => count($payload['assets']),
            'member_capital_minor' => $memberCapital,
            'other_asset_carrying_minor' => $assetCarrying,
            'balance_debits_minor' => $debits,
            'balance_credits_minor' => $credits,
            'status' => 'preview_only',
            'posting_enabled' => false,
            'note' => 'Opening ownership and trial-balance invariants are enforced again when the separate officer approves the actual opening instruction. CSV previews are not a substitute for source bank/holding reconciliation.',
        ];
    }
}
