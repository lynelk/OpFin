from pathlib import Path
import re


def replace(path, old, new, count=1):
    p = Path(path)
    text = p.read_text()
    if text.count(old) != count:
        raise RuntimeError(f'{path}: unexpected replacement anchor {old[:80]!r}')
    p.write_text(text.replace(old, new))


p = Path('apps/api/app/Services/FinancingService.php')
s = p.read_text()
marker = "        if ($intent->principles_preference === 'SHARIA_ONLY') {"
assert s.count(marker) == 1
s = s.replace(marker, r'''        // Islamic approval is required for every preference, including ALL_SUITABLE.
        $query->where(function ($rails) {
            $rails->where('rail', 'CONVENTIONAL')->orWhere(function ($islamic) {
                $islamic->where('rail', 'ISLAMIC')->whereHas('shariaApproval', function ($approval) {
                    $approval->where('status', 'approved')
                        ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
                        ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
                });
            });
        });

''' + marker)
s = s.replace("if ((int) $intent->user_id !== (int) $user->id || $intent->status !== 'open')", "if ((int) $intent->user_id !== (int) $user->id || $intent->status !== 'open' || ($intent->expires_at && $intent->expires_at->isPast()))")
start = s.index('    private function assertSpaceAuthority(')
s = s[:start] + r'''    public function assertSpaceAuthority(User $user, int $spaceId): void
    {
        $allowed = DB::table('financial_space_memberships as memberships')
            ->join('financial_spaces as spaces', 'spaces.id', '=', 'memberships.financial_space_id')
            ->where('spaces.id', $spaceId)->where('spaces.status', 'active')->whereNull('spaces.deleted_at')
            ->where('memberships.user_id', $user->id)->where('memberships.status', 'active')
            ->whereNull('memberships.deleted_at')->exists();
        if (! $allowed) {
            throw new InvalidArgumentException('You do not have active authority in that Financial Space.');
        }
    }
}
'''
p.write_text(s)

p = 'apps/api/app/Http/Controllers/Api/PartnerFinancialIntentController.php'
replace(p, 'use App\\Services\\FinancingService;', 'use App\\Services\\FinancingService;\nuse App\\Services\\AuditLogger;')
replace(p, 'public function __construct(private readonly FinancingService $financing) {}', 'public function __construct(private readonly FinancingService $financing, private readonly AuditLogger $audit) {}')
replace(p, "        $customerModel = User::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($customer);", "        if (($partner->financial_intent_source_platform ?? null) !== $validated['source_platform']) {\n            return ApiResponse::error('The requested source platform is not approved for this partner account.', 403);\n        }\n        $customerModel = User::withoutGlobalScopes()->whereNull('deleted_at')->where('role', User::ROLE_CUSTOMER)->findOrFail($customer);")
replace(p, 'DB::transaction(function () use ($validated, $idempotency, $customerModel) {', "DB::transaction(function () use ($request, $validated, $idempotency, $customerModel) {\n            User::withoutGlobalScopes()->whereKey($customerModel->id)->whereNull('deleted_at')->lockForUpdate()->firstOrFail();\n            $currentPartner = DB::table('partner_distribution_accounts')->where('id', $validated['partner_account_id'])->lockForUpdate()->first();\n            abort_unless($currentPartner && in_array($currentPartner->status, ['active', 'approved'], true)\n                && $currentPartner->financial_intent_source_platform === $validated['source_platform']\n                && $this->partnerAllowsFinancialIntent($currentPartner->allowed_products), 403);")
replace(p, '            return PartnerFinancialIntentRequest::create([', '            $created = PartnerFinancialIntentRequest::create([')
replace(p, "                ...$requestPayload,\n            ]);\n        });", "                ...$requestPayload,\n            ]);\n            $this->audit->record('partner.financial_intent.referred', $request->user(), $created, [\n                'customer_id' => $customerModel->id, 'source_platform' => $created->source_platform,\n                'partner_account_id' => $created->partner_account_id,\n            ], $request);\n            return $created;\n        });")
replace(p, "            $intent = DB::transaction(function () use ($request, $partnerRequest, $validated) {", "            $intent = DB::transaction(function () use ($request, $partnerRequest, $validated) {\n                User::withoutGlobalScopes()->whereKey($request->user()->id)->whereNull('deleted_at')->lockForUpdate()->firstOrFail();\n                $this->financing->assertSpaceAuthority($request->user(), (int) $validated['financial_space_id']);")
replace(p, "                    return \\App\\Models\\FinancialIntent::findOrFail($locked->confirmed_financial_intent_id);", "                    $existing = \\App\\Models\\FinancialIntent::findOrFail($locked->confirmed_financial_intent_id);\n                    if ((int) $existing->financial_space_id !== (int) $validated['financial_space_id']\n                        || $existing->principles_preference !== ($validated['principles_preference'] ?? 'ALL_SUITABLE')) {\n                        throw new ConflictHttpException('This referral was already confirmed with different customer instructions.');\n                    }\n                    return $existing;")
replace(p, '                return $intent;\n            });', "                $this->audit->record('partner.financial_intent.confirmed', $request->user(), $locked, [\n                    'financial_space_id' => $intent->financial_space_id, 'financial_intent_id' => $intent->id,\n                    'principles_preference' => $intent->principles_preference,\n                ], $request);\n                return $intent;\n            });")
replace(p, 'DB::transaction(function () use ($partnerRequest) {', 'DB::transaction(function () use ($request, $partnerRequest) {')
replace(p, "                $locked->update(['status' => 'declined', 'declined_at' => now()]);", "                $locked->update(['status' => 'declined', 'declined_at' => now()]);\n                $this->audit->record('partner.financial_intent.declined', $request->user(), $locked, [], $request);")

Path('apps/api/database/migrations/2026_10_02_000100_bind_partner_referral_source.php').write_text(r'''<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_distribution_accounts', fn (Blueprint $table) => $table->string('financial_intent_source_platform', 80)->nullable());
        // Existing accounts remain unconfigured until their separate authorised review.
    }

    public function down(): void
    {
        Schema::table('partner_distribution_accounts', fn (Blueprint $table) => $table->dropColumn('financial_intent_source_platform'));
    }
};
''')

p = Path('apps/api/app/Http/Controllers/Api/LongRangeGovernanceController.php')
s = p.read_text()
a = s.index('    public function partner(')
b = s.index('    public function referralReward(', a)
part = s[a:b].replace("['status' => 'required|in:approved,rejected']", "['status' => 'required|in:approved,rejected', 'financial_intent_source_platform' => 'nullable|in:stolets,shamba,coreworks']")
s = s[:a] + part + s[b:]
p.write_text(s)
p = Path('apps/api/app/Services/LongRangeGovernanceService.php')
s = p.read_text()
a = s.index('    public function approvePartner(')
b = s.index('\n    public function ', a + 5)
part = s[a:b]
part = part.replace("            'status' => $data['status'],", "            'status' => $data['status'],\n            'financial_intent_source_platform' => $data['status'] === 'approved'\n                ? ($data['financial_intent_source_platform'] ?? $record->financial_intent_source_platform) : null,")
# Preserve the existing maker-checker rule and audit the source decision explicitly.
part = part.replace("'status' => $data['status']]);", "'status' => $data['status'], 'financial_intent_source_platform' => $data['financial_intent_source_platform'] ?? null]);")
s = s[:a] + part + s[b:]
p.write_text(s)

# The legacy public account-deletion form performs credential authentication itself.
# Do not convert that existing channel into an undocumented logged-in-only endpoint.
p = Path('apps/api/app/Http/Controllers/Api/AuthController.php')
s = p.read_text()
a = s.index('    public function destroy(Request $request)')
b = s.index('    protected function beforeUserDelete(', a)
s = s[:a] + r'''    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'pin' => ['nullable', 'required_without:password', 'string'],
            'password' => ['nullable', 'required_without:pin', 'string'],
            'confirmation' => ['required', 'in:DELETE'],
        ]);
        $credential = (string) ($validated['pin'] ?? $validated['password']);
        $user = User::withoutGlobalScopes()->whereNull('deleted_at')->where('phone', $validated['phone'])->first();
        if (! $user || ! Hash::check($credential, $user->password)) {
            return back()->with('error', 'User details provided are invalid.');
        }
        $result = app(\App\Services\AccountDeletionService::class)->deleteOrRequest($user, $credential, $request);
        if (($result['deletion_status'] ?? null) !== 'completed') {
            return back()->with('error', $result['message'] ?? 'Account deletion could not be completed.')
                ->with('deletion_blockers', $result['active_obligations'] ?? []);
        }
        if ((int) ($request->user()?->id ?? 0) === (int) $user->id) {
            \Illuminate\Support\Facades\Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        return redirect('/')->with('success', 'Your account has been deleted. Records requiring lawful retention are preserved.');
    }

''' + s[b:]
# beforeUserDelete is now unused in this class; preserve only if another caller exists.
old = re.search(r'    protected function beforeUserDelete\(User \$user\): void\n    \{[\s\S]*?\n    \}\n', s)
if old and s.count('beforeUserDelete(') == 1:
    s = s[:old.start()] + s[old.end():]
p.write_text(s)

p = Path('apps/client/lib/account_delete_screen.dart')
s = p.read_text()
s = s.replace("final data = (decoded['data'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};\n      if (!mounted) return;", "final data = (decoded['data'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};\n      if (data['deletion_status'] != 'data_deleted') {\n        throw Exception('The selected data deletion was not confirmed. Please refresh the account status.');\n      }\n      if (!mounted) return;")
p.write_text(s)

p = Path('apps/api/app/Services/AccountDeletionObligationService.php')
s = p.read_text()
# Add every nonterminal policy state while preserving existing amount checks.
s = s.replace("['premium_due', 'active', 'lapsed']", "['premium_due', 'premium_pending', 'pending_issuance', 'active', 'lapsed']")
p.write_text(s)

# iOS 13 support must not invoke the iOS 14-only instance status API unguarded.
p = Path('apps/client/ios/Runner/AppDelegate.swift')
s = p.read_text().replace('switch manager.authorizationStatus {', 'switch currentAuthorisation(manager) {')
marker = '  private func requestCurrentLocation() {'
assert marker in s
s = s.replace(marker, '''  private func currentAuthorisation(_ manager: CLLocationManager) -> CLAuthorizationStatus {
    if #available(iOS 14.0, *) { return manager.authorizationStatus }
    return CLLocationManager.authorizationStatus()
  }

''' + marker)
p.write_text(s)
print('Partner provenance, governed source configuration and legacy-channel compatibility repairs prepared.')
