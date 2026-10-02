from pathlib import Path
import re


def edit(path, old, new, count=1):
    p = Path(path)
    text = p.read_text()
    actual = text.count(old)
    if actual != count:
        raise RuntimeError(f'{path}: expected {count} anchors, got {actual}')
    p.write_text(text.replace(old, new))


Path('apps/api/app/Services/SerialisedAccountDeletionService.php').write_text(r'''<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

class SerialisedAccountDeletionService extends AccountDeletionService
{
    public function deleteOrRequest(User $user, string $credential, Request $request): array
    {
        return app(EssentialsCustomerMutex::class)->run(
            $user->id,
            fn (User $current): array => parent::deleteOrRequest($current, $credential, $request),
        );
    }
}
''')
edit('apps/api/app/Http/Controllers/Api/AccountController.php',
     "if ($result['deletion_status'] === 'blocked_obligations')",
     "if (($result['deletion_status'] ?? null) !== 'completed')")

p = Path('apps/api/app/Services/AccountDataDeletionService.php')
s = p.read_text()
start = s.index('    private function deleteLocation(int $userId): int')
s = s[:start] + r'''    private function deleteLocation(int $userId): int
    {
        if (! Schema::hasTable('location_contexts')) {
            return 0;
        }

        // Optional personal discovery context is not authority over a shared
        // Space, another customer, or retained asset/claim/contract evidence.
        return DB::table('location_contexts')
            ->where('subject_type', 'user')
            ->where('subject_id', $userId)
            ->where('purpose', 'personal_service_discovery')
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)->orWhereNull('user_id');
            })
            ->delete();
    }
}
'''
p.write_text(s)

p = 'apps/api/app/Services/AccountDeletionObligationService.php'
edit(p, '            ...$this->loans($user->id),',
     '            ...$this->loans($user->id),\n            ...$this->financingArrangements($user->id),\n            ...$this->collectionInstructions($user->id),')
edit(p, "            ->where('memberships.user_id', $userId)\n            ->where('spaces.type', 'personal')",
     "            ->where('memberships.user_id', $userId)\n            ->where('memberships.role', 'owner')\n            ->where('memberships.status', 'active')\n            ->whereNull('memberships.deleted_at')\n            ->whereNull('spaces.deleted_at')\n            ->where('spaces.type', 'personal')")
edit(p, "                'reconciliation_exception',\n            ])", "                'reconciliation_exception',\n                'cancellation_pending',\n            ])")
s = Path(p).read_text()
s = s.replace("['active', 'premium_due', 'lapsed']", "['active', 'premium_due', 'premium_pending', 'pending_issuance', 'lapsed']")
marker = '    private function personalObligations(int $userId): array'
assert marker in s
methods = r'''    private function financingArrangements(int $userId): array
    {
        if (! Schema::hasTable('financing_arrangements')) {
            return [];
        }

        $rows = DB::table('financing_arrangements as arrangements')
            ->leftJoin('financial_products as products', 'products.id', '=', 'arrangements.financial_product_id')
            ->leftJoin('partners as partners', 'partners.id', '=', 'products.partner_id')
            ->leftJoin('institutions as institutions', 'institutions.id', '=', 'partners.institution_id')
            ->where('arrangements.user_id', $userId)
            ->whereNotIn('arrangements.status', ['settled', 'closed', 'cancelled', 'rejected'])
            ->select('arrangements.*', 'partners.name as partner_name', 'institutions.name as provider_name',
                'institutions.phone as provider_phone', 'institutions.email as provider_email', 'institutions.address as provider_address')
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $legacyType = strtolower((string) ($row->legacy_type ?? ''));
            if (in_array($legacyType, ['loan', 'legacy_loan', 'app\\models\\loan'], true)
                && $row->legacy_id && DB::table('loans')->where('id', $row->legacy_id)->where('user_id', $userId)
                    ->whereNotIn('status', ['Cleared', 'Cancelled', 'Rejected'])->exists()) {
                continue;
            }
            $item = $this->item('financing_arrangement', 'Financing arrangement still open', $row->reference,
                $row->status, isset($row->total_obligation_minor) ? (int) $row->total_obligation_minor : null,
                $row->currency, null, $this->contact($row->provider_name ?? $row->partner_name ?? 'Financing provider',
                    $row->provider_phone, $row->provider_email, $row->provider_address));
            $item['amount_basis'] = 'contract_total_not_current_balance';
            $items[] = $item;
        }
        return $items;
    }

    private function collectionInstructions(int $userId): array
    {
        if (! Schema::hasTable('essentials_collection_instructions')) {
            return [];
        }

        return DB::table('essentials_collection_instructions as instructions')
            ->join('essentials_repayments as repayments', 'repayments.id', '=', 'instructions.repayment_id')
            ->join('essentials_advances as advances', 'advances.id', '=', 'instructions.advance_id')
            ->leftJoin('partners as partners', 'partners.id', '=', 'advances.lender_partner_id')
            ->leftJoin('institutions as institutions', 'institutions.id', '=', 'partners.institution_id')
            ->where('instructions.user_id', $userId)
            ->where(function ($query) {
                $query->whereNotIn('instructions.status', ['applied', 'failed', 'reversed'])
                    ->orWhere(function ($reversed) {
                        $reversed->where('instructions.status', 'reversed')->where('repayments.status', '<>', 'reversed');
                    });
            })
            ->select('instructions.id', 'instructions.status', 'instructions.amount_minor', 'instructions.currency',
                'repayments.reference', 'partners.name as partner_name', 'institutions.name as provider_name',
                'institutions.phone as provider_phone', 'institutions.email as provider_email', 'institutions.address as provider_address')
            ->get()->map(fn ($row) => $this->item('essentials_collection_reconciliation',
                'Collection or reversal still requires reconciliation', $row->reference ?: 'collection-'.$row->id,
                $row->status, (int) $row->amount_minor, $row->currency, null,
                $this->contact($row->provider_name ?? $row->partner_name ?? 'Collection provider',
                    $row->provider_phone, $row->provider_email, $row->provider_address)))
            ->all();
    }

'''
Path(p).write_text(s.replace(marker, methods + marker))

p = Path('apps/api/app/Http/Controllers/Api/AuthController.php')
s = p.read_text()
pattern = r'    public function destroy\(Request \$request\)[\s\S]*?(?=\n    (?:public|private|protected) function|\n})'
m = re.search(pattern, s)
if not m:
    raise RuntimeError('Legacy web account-deletion method was not found')
s = s[:m.start()] + r'''    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'confirmation' => ['required', 'in:DELETE'],
        ]);
        $result = app(\App\Services\AccountDeletionService::class)->deleteOrRequest(
            $request->user(), $validated['password'], $request,
        );
        if (($result['deletion_status'] ?? null) !== 'completed') {
            return back()->withErrors(['account' => $result['message'] ?? 'Account deletion could not be completed.'])
                ->with('deletion_blockers', $result['blockers'] ?? []);
        }
        \Illuminate\Support\Facades\Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login')->with('status', 'Your account has been deleted. Records that require lawful retention are preserved.');
    }
''' + s[m.end():]
p.write_text(s)

p = Path('apps/api/resources/views/account/delete.blade.php')
s = p.read_text()
# Append escaped blocker detail within the existing view, before its form.
block = r'''@if (session('deletion_blockers'))
    <section aria-label="Account deletion blockers">
        <h2>Resolve these obligations before submitting a fresh deletion request</h2>
        @foreach (session('deletion_blockers', []) as $blocker)
            <div>
                <strong>{{ $blocker['label'] ?? 'Outstanding obligation' }}</strong>
                <p>Reference: {{ $blocker['reference'] ?? '' }}; status: {{ $blocker['status'] ?? '' }}</p>
                @if (isset($blocker['amount_minor']))
                    <p>{{ $blocker['currency'] ?? '' }} {{ $blocker['amount_minor'] }}
                    @if (($blocker['amount_basis'] ?? '') === 'contract_total_not_current_balance') (contract total, not a current balance) @endif</p>
                @endif
                <p>{{ $blocker['provider']['name'] ?? 'Recorded provider' }}</p>
                @if (!empty($blocker['provider']['direct_contact_available']))
                    <p>{{ $blocker['provider']['phone'] ?? '' }} {{ $blocker['provider']['email'] ?? '' }} {{ $blocker['provider']['address'] ?? '' }}</p>
                @else
                    <p>No direct provider contact is recorded. Keep the reference above for follow-up.</p>
                @endif
            </div>
        @endforeach
    </section>
@endif
'''
index = s.find('<form')
if index < 0:
    raise RuntimeError('Legacy deletion form not found')
p.write_text(s[:index] + block + s[index:])

p = 'apps/api/app/Models/FinancialSpaceTreasuryAccount.php'
s = Path(p).read_text()
s = s.replace(" && $this->transactions()->exists()", '')
s = s.replace('Opening balance is locked after the first cashbook transaction.', 'Opening balance is locked from account creation; use a reviewed accounting correction.')
s = s.replace('The opening-balance baseline may be corrected only before the first\n     * cashbook transaction. Once economic activity exists, changing it would\n     * rewrite every historical balance derived from that baseline.', 'The opening-balance baseline is immutable from creation. Corrections\n     * require a separately reviewed accounting adjustment.')
Path(p).write_text(s)

# Client success must be explicit, not inferred from an HTTP 200 response.
p = Path('apps/client/lib/account_delete_screen.dart')
s = p.read_text()
anchor = '      await OfflineSyncService.clearLocalData();'
assert anchor in s
s = s.replace(anchor, "      if ((data['deletion_status']?.toString() ?? '') != 'completed') {\n        throw Exception('Account deletion was not completed. Your account remains available.');\n      }\n\n" + anchor)
p.write_text(s)

print('Account deletion repair batch prepared.')
