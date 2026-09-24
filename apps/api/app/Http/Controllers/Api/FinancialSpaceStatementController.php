<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use App\Models\FinancialSpaceGeneratedStatement;
use App\Models\FinancialSpaceStatementImport;
use App\Models\FinancialSpaceStatementRow;
use App\Models\FinancialSpaceTransaction;
use App\Models\FinancialSpaceTreasuryAccount;
use App\Services\FinancialSpaceStatementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class FinancialSpaceStatementController extends Controller
{
    public function __construct(private readonly FinancialSpaceStatementService $statements) {}

    public function accounts(FinancialSpace $space, Request $request): JsonResponse
    {
        return ApiResponse::success('Treasury accounts loaded.', [
            'accounts' => $this->statements->accounts($space, $request->user()),
        ]);
    }

    public function createAccount(FinancialSpace $space, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_name' => ['required', 'string', 'max:160'],
            'account_type' => ['required', Rule::in(['bank', 'mobile_money', 'cash', 'custodian', 'broker', 'investment_wallet', 'other'])],
            'institution_name' => ['nullable', 'string', 'max:160'],
            'account_reference' => ['nullable', 'string', 'max:160'],
            'currency' => ['nullable', 'string', 'size:3'],
            'opening_balance_minor' => ['sometimes', 'integer', 'min:-9000000000000000', 'max:9000000000000000'],
            'balance_as_of' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $account = $this->statements->createAccount($space, $request->user(), $validated);

            return ApiResponse::success('Treasury account created.', ['account' => $account], 201);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }
    }

    public function transactions(
        FinancialSpace $space,
        FinancialSpaceTreasuryAccount $account,
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return ApiResponse::success('Treasury transactions loaded.', [
            'transactions' => $this->statements->transactions(
                $space,
                $account,
                $request->user(),
                $validated['from'] ?? null,
                $validated['to'] ?? null,
            ),
        ]);
    }

    public function recordTransaction(
        FinancialSpace $space,
        FinancialSpaceTreasuryAccount $account,
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'transaction_reference' => ['nullable', 'string', 'max:160'],
            'transaction_type' => ['sometimes', 'string', 'max:80'],
            'direction' => ['required', Rule::in(['debit', 'credit'])],
            'amount_minor' => ['required', 'integer', 'min:1', 'max:9000000000000000'],
            'currency' => ['nullable', 'string', 'size:3'],
            'description' => ['required', 'string', 'max:500'],
            'counterparty_name' => ['nullable', 'string', 'max:200'],
            'transaction_date' => ['required', 'date'],
            'value_date' => ['nullable', 'date'],
            'source_type' => ['sometimes', 'string', 'max:80'],
            'source_reference' => ['nullable', 'string', 'max:200'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $transaction = $this->statements->recordTransaction(
                $space,
                $account,
                $request->user(),
                $validated
            );

            return ApiResponse::success('Treasury transaction recorded.', [
                'transaction' => $transaction,
            ], 201);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }
    }

    public function imports(
        FinancialSpace $space,
        FinancialSpaceTreasuryAccount $account,
        Request $request
    ): JsonResponse {
        return ApiResponse::success('Statement imports loaded.', [
            'imports' => $this->statements->imports($space, $account, $request->user()),
        ]);
    }

    public function importStatement(
        FinancialSpace $space,
        FinancialSpaceTreasuryAccount $account,
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'statement_file' => ['required', 'file', 'max:10240', 'mimes:csv,txt'],
            'mapping' => ['required'],
            'minor_unit_exponent' => ['sometimes', 'integer', 'min:0', 'max:4'],
            'opening_balance_minor' => ['nullable', 'integer', 'min:-9000000000000000', 'max:9000000000000000'],
            'closing_balance_minor' => ['nullable', 'integer', 'min:-9000000000000000', 'max:9000000000000000'],
        ]);

        $mapping = $validated['mapping'];
        if (is_string($mapping)) {
            $mapping = json_decode($mapping, true);
        }
        if (! is_array($mapping)) {
            return ApiResponse::error('Statement column mapping must be a JSON object.', 422);
        }

        try {
            $import = $this->statements->importCsv(
                $space,
                $account,
                $request->user(),
                $request->file('statement_file'),
                $mapping,
                (int) ($validated['minor_unit_exponent'] ?? 0),
                isset($validated['opening_balance_minor']) ? (int) $validated['opening_balance_minor'] : null,
                isset($validated['closing_balance_minor']) ? (int) $validated['closing_balance_minor'] : null,
            );

            return ApiResponse::success('Statement imported.', [
                'import' => $import,
            ], 201);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }
    }

    public function importDetail(
        FinancialSpace $space,
        FinancialSpaceStatementImport $import,
        Request $request
    ): JsonResponse {
        $this->statements->accounts($space, $request->user());
        abort_unless($import->financial_space_id === $space->id, 404);

        return ApiResponse::success('Statement import loaded.', [
            'import' => $import->load(['rows.matchedTransaction', 'account']),
        ]);
    }

    public function matchRow(
        FinancialSpace $space,
        FinancialSpaceStatementRow $row,
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'transaction_id' => ['required', 'integer', 'min:1'],
        ]);

        $transaction = FinancialSpaceTransaction::query()->findOrFail(
            (int) $validated['transaction_id']
        );

        return ApiResponse::success('Statement row matched.', [
            'row' => $this->statements->matchStatementRow(
                $space,
                $row,
                $transaction,
                $request->user(),
            ),
        ]);
    }

    public function reconcile(
        FinancialSpace $space,
        FinancialSpaceStatementImport $import,
        Request $request
    ): JsonResponse {
        try {
            return ApiResponse::success('Statement reconciliation completed.', [
                'import' => $this->statements->reconcile($space, $import, $request->user()),
            ]);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }
    }

    public function statements(FinancialSpace $space, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return ApiResponse::success('Generated statements loaded.', [
            'statements' => $this->statements->generatedStatements(
                $space,
                $request->user(),
                isset($validated['account_id']) ? (int) $validated['account_id'] : null,
            ),
        ]);
    }

    public function generate(
        FinancialSpace $space,
        FinancialSpaceTreasuryAccount $account,
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
        ]);

        $data = $this->statements->generateStatement(
            $space,
            $account,
            $request->user(),
            $validated['from'],
            $validated['to'],
        );

        $statement = $data['statement'];

        return ApiResponse::success('Bank-style statement issued.', [
            'statement' => $statement,
            'account' => $data['account'],
            'space' => $data['space'],
            'rows' => $data['rows'],
            'downloads' => [
                'html' => route('financial-spaces.statements.html', [
                    'space' => $space->id,
                    'statement' => $statement->id,
                ], false),
                'csv' => route('financial-spaces.statements.csv', [
                    'space' => $space->id,
                    'statement' => $statement->id,
                ], false),
            ],
        ], 201);
    }

    public function show(
        FinancialSpace $space,
        FinancialSpaceGeneratedStatement $statement,
        Request $request
    ): JsonResponse {
        $data = $this->statements->statementData($space, $statement, $request->user());

        return ApiResponse::success('Generated statement loaded.', [
            'statement' => $data['statement'],
            'account' => $data['account'],
            'space' => $data['space'],
            'rows' => $data['rows'],
        ]);
    }

    public function html(
        FinancialSpace $space,
        FinancialSpaceGeneratedStatement $statement,
        Request $request
    ) {
        $data = $this->statements->statementData($space, $statement, $request->user());

        return response($this->statements->renderBankStyleHtml($data), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="'.$statement->statement_number.'.html"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function csv(
        FinancialSpace $space,
        FinancialSpaceGeneratedStatement $statement,
        Request $request
    ) {
        $data = $this->statements->statementData($space, $statement, $request->user());

        return response($this->statements->renderCsv($data), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$statement->statement_number.'.csv"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

}