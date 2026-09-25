<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\LoanProduct;
use App\Models\Partner;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CreditDistributionService;
use App\Services\FundingPoolService;
use App\Services\PlatformCreditRoutingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LendingPlatformController extends Controller
{
    public function __construct(private readonly PlatformCreditRoutingService $routing, private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->routing->canManage($request->user()), 403);

        return ApiResponse::success('Lending platform configuration loaded.', [
            'institutions' => Institution::orderBy('name')->get(),
            'lender_records' => Partner::whereNotNull('institution_id')->get(['id', 'institution_id', 'code', 'name']),
            'products' => LoanProduct::with('terms')->orderBy('name')->get(),
            'strategy' => $this->routing->strategy(),
            'strategy_history' => DB::table('platform_credit_strategies')->latest('id')->limit(30)->get(),
            'distribution_rules' => DB::table('credit_distribution_rules')->latest('id')->limit(100)->get(),
            'distribution_defaults' => config('credit_distribution.defaults'),
            'channels' => config('credit_distribution.channels'),
            'enabled_countries' => config('credit_distribution.enabled_countries'),
            'can_set_strategy' => $request->user()->hasRole(User::ROLE_PLATFORM_ADMIN),
        ]);
    }

    public function institution(Request $request): JsonResponse
    {
        abort_unless($this->routing->canManage($request->user()), 403);
        $data = $request->validate([
            'id' => 'nullable|integer|exists:institutions,id',
            'name' => 'required|string|max:255', 'address' => 'required|string|max:255',
            'phone' => 'required|string|max:40', 'email' => 'required|email|max:255',
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
            'lender_relationship' => ['required', Rule::in(['independent', 'affiliated'])],
            'country' => ['required', 'regex:/^[A-Z]{2}$/'],
            'regulator_code' => 'nullable|string|max:80', 'licence_class' => 'nullable|string|max:120',
            'authority_basis' => ['required', Rule::in(['pending', 'licensed', 'other_authority', 'exempt'])],
            'authority_reference' => 'nullable|string|max:255', 'authority_valid_until' => 'nullable|date|after_or_equal:today',
            'rate_change_approval_required' => 'required|boolean',
        ]);
        if ($data['status'] === 'Active') {
            abort_if($data['authority_basis'] === 'pending' || blank($data['authority_reference'] ?? null), 422, 'Record the applicable lender authority or exemption evidence before activation.');
            abort_if($data['authority_basis'] === 'licensed' && blank($data['regulator_code'] ?? null), 422, 'Record the licensing authority.');
        }
        $institution = isset($data['id']) ? Institution::findOrFail($data['id']) : new Institution;
        if (! $institution->exists || $institution->lender_relationship !== $data['lender_relationship']) {
            abort_unless($request->user()->hasRole(User::ROLE_PLATFORM_ADMIN), 403, 'Only platform administrators can establish or change lender ownership.');
        }
        unset($data['id']);
        DB::transaction(function () use ($institution, $data, $request) {
            $institution->fill($data)->save();
            // An accounting/provider record is not a login or partner user account.
            Partner::updateOrCreate(['code' => 'LENDER-'.$institution->id], [
                'institution_id' => $institution->id, 'name' => $institution->name,
                'partner_type' => 'lender', 'country' => $institution->country,
                'status' => $institution->status === 'Active' ? 'active' : 'onboarding',
                'regulatory_evidence' => ['authority_basis' => $institution->authority_basis, 'authority_reference' => $institution->authority_reference, 'regulator' => $institution->regulator_code],
                'metadata' => ['managed_from_platform_admin' => true],
            ]);
            $this->audit->record('lending_platform.institution.updated', $request->user(), $institution, ['lender_relationship' => $institution->lender_relationship, 'country' => $institution->country, 'authority_basis' => $institution->authority_basis]);

        });

        return ApiResponse::success('Lender profile saved.', ['institution' => $institution], 201);
    }

    public function product(Request $request): JsonResponse
    {
        abort_unless($this->routing->canManage($request->user()), 403);
        $data = $request->validate([
            'id' => 'nullable|integer|exists:loan_products,id', 'institution_id' => 'required|integer|exists:institutions,id',
            'name' => 'required|string|max:255', 'product_category' => ['required', 'regex:/^[a-z][a-z0-9_]{1,79}$/'],
            'country' => ['required', 'regex:/^[A-Z]{2}$/'], 'currency' => ['required', 'regex:/^[A-Z]{3}$/'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
            'min_amount_minor' => 'required|integer|min:1', 'max_amount_minor' => 'nullable|integer|gte:min_amount_minor',
            'borrower_purposes' => 'nullable|array', 'borrower_purposes.*' => 'string|max:255',
            'funding_pool_id' => 'nullable|integer|exists:capital_mandates,id',
        ]);
        $institution = Institution::findOrFail($data['institution_id']);
        $product = isset($data['id']) ? LoanProduct::findOrFail($data['id']) : new LoanProduct;
        abort_if($product->exists && (int) $product->institution_id !== (int) $institution->id, 422, 'Create a separate product when the responsible lender changes.');
        abort_if($institution->country !== $data['country'], 422, 'Use a lender profile for this product jurisdiction.');
        if ($data['status'] === 'Active') {
            abort_if($institution->authority_basis === 'pending' || blank($institution->authority_reference) || $institution->status !== 'Active', 422, 'Complete the active lender profile first.');
        }
        if (! empty($data['funding_pool_id'])) {
            try {
                app(FundingPoolService::class)->validateLender($data['funding_pool_id'], $institution);
            } catch (\InvalidArgumentException $exception) {
                return ApiResponse::error($exception->getMessage(), 422);
            }
        }
        unset($data['id']);
        $product->fill($data);
        if (! $product->exists) {
            $product->type = $data['product_category'] === 'asset_finance' ? 'Asset' : 'Cash';
        }
        $product->save();
        $this->audit->record('lending_platform.product.updated', $request->user(), $product, ['institution_id' => $institution->id, 'country' => $product->country, 'currency' => $product->currency]);

        return ApiResponse::success('Lender product saved. Configure terms before offering it.', ['product' => $product], 201);
    }

    public function term(LoanProduct $product, Request $request): JsonResponse
    {
        abort_unless($this->routing->canManage($request->user()), 403);
        $data = $request->validate([
            'duration' => 'required|integer|min:1|max:36500',
            'interest_rate' => 'required|numeric|min:0', 'default_interest_rate' => 'nullable|numeric|min:0',
            'interest_type' => ['required', Rule::in(['Flat', 'reducing_balance'])],
            'interest_cycle' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'annual'])],
            'repayment_frequency' => ['required', Rule::in(['daily', 'weekly', 'fortnightly', 'monthly'])],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);
        $term = $product->terms()->create($data);
        $this->audit->record('lending_platform.term.created', $request->user(), $term, ['product_id' => $product->id, 'duration_days' => $term->duration]);

        return ApiResponse::success('Product term created.', ['term' => $term], 201);
    }

    public function strategy(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole(User::ROLE_PLATFORM_ADMIN), 403);
        $data = $request->validate([
            'mode' => ['required', Rule::in(['withhold', 'external_first', 'affiliated_first'])],
            'reason' => 'required|string|min:10|max:2000',
            'max_affiliated_loan_minor' => 'nullable|integer|min:1',
            'effective_from' => 'required|date', 'effective_to' => 'nullable|date|after:effective_from',
        ]);
        $data['effective_from'] = Carbon::parse($data['effective_from'])->setTimezone(config('app.timezone'));
        $data['effective_to'] = empty($data['effective_to']) ? null : Carbon::parse($data['effective_to'])->setTimezone(config('app.timezone'));
        $id = DB::table('platform_credit_strategies')->insertGetId([...$data, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record('lending_platform.strategy.created', $request->user(), null, ['strategy_id' => $id, ...$data]);

        return ApiResponse::success('Credit deployment strategy recorded.', ['strategy' => DB::table('platform_credit_strategies')->find($id)], 201);
    }

    public function delegate(User $user, Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole(User::ROLE_PLATFORM_ADMIN), 403);
        $data = $request->validate(['enabled' => 'required|boolean']);
        abort_unless($user->hasRole(User::ROLE_OPERATIONS), 422, 'Delegation is available only to an operations user.');
        $user->forceFill(['can_manage_platform_credit' => $data['enabled']])->save();
        $this->audit->record('lending_platform.access.changed', $request->user(), $user, $data);

        return ApiResponse::success('Platform credit access updated.', ['user_id' => $user->id, 'enabled' => $user->can_manage_platform_credit]);
    }

    public function distribution(Request $request, CreditDistributionService $distribution): JsonResponse
    {
        abort_unless($request->user()->hasRole(User::ROLE_PLATFORM_ADMIN), 403);
        $data = $request->validate([
            'channel' => ['required', Rule::in($distribution->channels())],
            'country' => ['required', 'regex:/^(\*|[A-Z]{2})$/'],
            'product_category' => ['required', 'regex:/^(\*|[a-z][a-z0-9_]{1,79})$/'],
            'institution_id' => 'nullable|integer|exists:institutions,id', 'loan_product_id' => 'nullable|integer|exists:loan_products,id',
            'partner_product_id' => 'nullable|integer|exists:partner_products,id',
            'availability' => ['required', Rule::in(['available', 'unavailable', 'review'])],
            'min_duration_days' => 'nullable|integer|min:1', 'max_apr_percent' => 'nullable|numeric|min:0',
            'reason' => 'required|string|min:10|max:2000', 'source_reference' => 'required|string|max:255',
            'source_url' => 'nullable|url:http,https|max:2000',
            'effective_from' => 'required|date', 'effective_to' => 'nullable|date|after:effective_from',
        ]);
        $data['effective_from'] = Carbon::parse($data['effective_from'])->setTimezone(config('app.timezone'));
        $data['effective_to'] = empty($data['effective_to']) ? null : Carbon::parse($data['effective_to'])->setTimezone(config('app.timezone'));
        abort_if(! empty($data['loan_product_id']) && ! empty($data['partner_product_id']), 422, 'Select one product catalogue scope.');
        if (! empty($data['partner_product_id'])) {
            $owner = DB::table('partner_products as pp')->join('partners as p', 'p.id', '=', 'pp.partner_id')->where('pp.id', $data['partner_product_id'])->value('p.institution_id');
            abort_if(! empty($data['institution_id']) && (int) $data['institution_id'] !== (int) $owner, 422, 'The partner product must belong to the selected lender.');
            $data['institution_id'] = $owner;
        }
        $data['channel'] = $distribution->channel($data['channel']);
        if (! empty($data['loan_product_id'])) {
            $product = LoanProduct::findOrFail($data['loan_product_id']);
            abort_if(! empty($data['institution_id']) && (int) $data['institution_id'] !== (int) $product->institution_id, 422, 'The product must belong to the selected lender.');
            $data['institution_id'] = $product->institution_id;
        }
        $scope = hash('sha256', json_encode([$data['channel'], $data['country'], $data['product_category'], $data['institution_id'] ?? null, $data['loan_product_id'] ?? null, $data['partner_product_id'] ?? null], JSON_THROW_ON_ERROR));
        $rule = DB::transaction(function () use ($data, $scope, $request) {
            // Serialise configuration writes without a new infrastructure dependency.
            User::withoutGlobalScopes()->orderBy('id')->lockForUpdate()->firstOrFail();
            $version = (int) DB::table('credit_distribution_rules')->where('scope_key', $scope)->max('version') + 1;
            $id = DB::table('credit_distribution_rules')->insertGetId([...$data, 'scope_key' => $scope, 'version' => $version, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->audit->record('lending_platform.distribution.created', $request->user(), null, ['rule_id' => $id, 'version' => $version, 'scope_key' => $scope, 'source_reference' => $data['source_reference']]);

            return DB::table('credit_distribution_rules')->find($id);
        });

        return ApiResponse::success('Distribution policy revision recorded.', ['rule' => $rule], 201);
    }
}
