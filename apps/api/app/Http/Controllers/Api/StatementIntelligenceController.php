<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use App\Services\FinancialIntelligence\StatementVault;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use JsonException;

final class StatementIntelligenceController extends Controller
{
    public function __construct(private readonly StatementVault $vault) {}
    public function issuers(Request $r, FinancialSpace $space) { return response()->json(['data' => $this->vault->issuers($space, $r->user())]); }
    public function index(Request $r, FinancialSpace $space) { return response()->json(['data' => $this->vault->listing($space, $r->user(), $this->page($r))]); }
    public function show(Request $r, FinancialSpace $space, int $statement) { return response()->json(['data' => $this->vault->detail($space, $r->user(), $statement, $this->page($r))]); }
    public function revoke(Request $r, FinancialSpace $space, int $statement) { return response()->json(['data' => $this->vault->revoke($space, $r->user(), $statement)]); }

    public function upload(Request $r, FinancialSpace $space)
    {
        $v = $r->validate(['statement_file' => ['required', 'file', 'max:24414'], 'issuer_version_id' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'regex:/^[A-Z]{3}$/'], 'period_start' => ['required', 'date_format:Y-m-d'], 'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'opening_balance_minor' => ['nullable', 'integer'], 'closing_balance_minor' => ['nullable', 'integer'],
            'account_reference' => ['required', 'string', 'max:120'], 'authority_reference' => ['required', 'string', 'max:255'], 'authority_confirmed' => ['required', 'accepted'],
            'authority_expires_at' => ['required', 'date', 'after:now', 'before:+367 days'], 'purpose' => ['required', Rule::in(['financial_analysis'])],
            'mapping' => ['nullable', 'string', 'max:20000']]);
        unset($v['statement_file']);
        $v['authority_confirmed'] = true;
        $v['authority_expires_at'] = Carbon::parse($v['authority_expires_at'])->toDateTimeString();
        try {
            $v['mapping'] = isset($v['mapping']) ? json_decode($v['mapping'], true, 64, JSON_THROW_ON_ERROR) : [];
            if (! is_array($v['mapping'])) { throw new InvalidArgumentException('Column mapping must be an object.'); }
            return response()->json(['data' => $this->vault->upload($space, $r->user(), $r->file('statement_file'), $v, (string) $r->header('Idempotency-Key', ''))], 201);
        } catch (InvalidArgumentException $e) { return response()->json(['message' => $e->getMessage()], 422); }
        catch (JsonException) { return response()->json(['message' => 'The column mapping is invalid JSON.'], 422); }
    }

    public function proposeIssuer(Request $r)
    {
        $v = $r->validate(['issuer_code' => ['required', 'string', 'max:80'], 'legal_name' => ['required', 'string', 'max:255'],
            'country' => ['required', 'regex:/^[A-Z]{2}$/'], 'product_type' => ['required', Rule::in(['bank', 'mobile_money', 'microfinance', 'sacco', 'investment', 'insurance'])],
            'regulator' => ['required', 'string', 'max:120'], 'licence_reference' => ['required', 'string', 'max:120'], 'evidence_reference' => ['required', 'string', 'max:255'],
            'valid_from' => ['required', 'date_format:Y-m-d'], 'valid_until' => ['required', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'review_due_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before:+367 days']]);
        return response()->json(['data' => $this->vault->proposeIssuer($r->user(), $v)], 201);
    }

    public function reviewIssuer(Request $r, int $issuer)
    {
        $v = $r->validate(['revoke' => ['required', 'boolean']]);
        return response()->json(['data' => $this->vault->reviewIssuer($r->user(), $issuer, (bool) $v['revoke'])]);
    }
    private function page(Request $r): int { $v = $r->validate(['page' => ['sometimes', 'integer', 'between:1,100000']]); return (int) ($v['page'] ?? 1); }
}
