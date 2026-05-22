<?php

namespace App\Services;

use App\Models\Account;
use App\Models\DemoConsent;
use App\Models\DemoLoanDecision;
use App\Models\DemoLoanOffer;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\MobileMoneyTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MobileMoney\MobileMoneyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class InvestorDemoService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly LoanService $loanService,
        private readonly MobileMoneyService $mobileMoneyService,
    ) {}

    public function grantConsent(User $user, Request $request): DemoConsent
    {
        $consent = DemoConsent::updateOrCreate(
            ['user_id' => $user->id, 'purpose' => DemoConsent::PURPOSE_CREDIT_PROCESSING],
            [
                'status' => DemoConsent::STATUS_GRANTED,
                'granted_at' => now(),
                'revoked_at' => null,
                'metadata' => [
                    'mock_integration' => true,
                    'label' => 'Investor demo credit-processing consent',
                ],
            ],
        );

        $this->auditLogger->record('demo.consent.granted', $user, $consent, [
            'mock_integration' => true,
            'purpose' => $consent->purpose,
        ], $request);

        return $consent;
    }

    public function revokeConsent(User $user, Request $request): DemoConsent
    {
        $consent = DemoConsent::updateOrCreate(
            ['user_id' => $user->id, 'purpose' => DemoConsent::PURPOSE_CREDIT_PROCESSING],
            [
                'status' => DemoConsent::STATUS_REVOKED,
                'revoked_at' => now(),
                'metadata' => [
                    'mock_integration' => true,
                    'label' => 'Investor demo credit-processing consent revoked',
                ],
            ],
        );

        $this->auditLogger->record('demo.consent.revoked', $user, $consent, [
            'mock_integration' => true,
            'purpose' => $consent->purpose,
        ], $request);

        return $consent;
    }

    public function createApplicationWithDecision(User $user, array $payload, Request $request): array
    {
        if ($user->nin_status !== 'VALID') {
            throw new AccessDeniedHttpException('Verified KYC is required for the investor demo.');
        }

        if (!$this->hasCreditConsent($user)) {
            throw new AccessDeniedHttpException('Credit processing consent is required for the investor demo.');
        }

        return DB::transaction(function () use ($user, $payload, $request) {
            $application = LoanApplication::create([
                'user_id' => $user->id,
                'loan_product_id' => $payload['loan_product_id'],
                'loan_product_term_id' => $payload['loan_product_term_id'],
                'institution_id' => $payload['institution_id'],
                'amount' => (string) $payload['amount'],
                'status' => 'Pending',
                'reason' => $payload['reason'],
            ]);

            $this->auditLogger->record('demo.loan_application.submitted', $user, $application, [
                'mock_integration' => true,
                'amount_minor' => (int) $payload['amount'],
            ], $request);

            $decision = $this->decisionFor($application);
            $offer = $decision->status === DemoLoanDecision::STATUS_APPROVED
                ? $this->offerFor($decision)
                : null;

            return [
                'application' => $application->fresh(['loanProduct', 'loanProductTerm']),
                'decision' => $decision,
                'offer' => $offer,
            ];
        });
    }

    public function acceptOffer(User $user, DemoLoanOffer $offer, Request $request): array
    {
        if ($offer->user_id !== $user->id && !$user->hasAnyRole([User::ROLE_PLATFORM_ADMIN, User::ROLE_OPERATIONS])) {
            throw new AccessDeniedHttpException('You cannot accept this offer.');
        }

        return DB::transaction(function () use ($user, $offer, $request) {
            $offer = DemoLoanOffer::whereKey($offer->id)->lockForUpdate()->firstOrFail();
            if ($offer->status !== DemoLoanOffer::STATUS_PENDING) {
                throw new BadRequestHttpException('This investor demo offer is not pending acceptance.');
            }

            $offer->update([
                'status' => DemoLoanOffer::STATUS_ACCEPTED,
                'accepted_at' => now(),
            ]);

            $application = $offer->application()->with(['user', 'loanProductTerm'])->firstOrFail();
            $application->update([
                'status' => 'Disbursed',
                'disbursed_at' => now(),
            ]);

            $this->ensureDemoAccounts($application);

            $transaction = Transaction::create([
                'user_id' => $application->user_id,
                'institution_id' => $application->institution_id,
                'loan_application_id' => $application->id,
                'loan_id' => null,
                'type' => 'Disbursement',
                'amount' => $offer->principal_amount_minor,
                'phone' => $application->user->phone,
                'reference' => 'demo-disbursement-' . $offer->id,
                'status' => 'SUCCESSFUL',
                'data' => json_encode(['mock_integration' => true, 'source' => 'investor_demo']),
            ]);

            $this->loanService->processSuccessfulTransaction($transaction);
            $loan = Loan::where('loan_application_id', $application->id)->firstOrFail();

            $mobileMoney = $this->mobileMoneyService->disburse([
                'transaction_id' => $transaction->id,
                'user_id' => $application->user_id,
                'institution_id' => $application->institution_id,
                'idempotency_key' => 'demo-mm-disbursement-' . $offer->id,
                'amount_minor' => (int) $offer->principal_amount_minor,
                'currency' => 'UGX',
                'phone' => $application->user->phone,
                'mock_integration' => true,
                'loan_application_id' => $application->id,
            ], 'mock');

            $this->auditLogger->record('demo.loan_offer.accepted', $user, $offer, [
                'mock_integration' => true,
                'loan_id' => $loan->id,
                'transaction_id' => $transaction->id,
                'mobile_money_transaction_id' => $mobileMoney->id,
            ], $request);
            $this->auditLogger->record('demo.loan_account.created', $user, $loan, [
                'mock_integration' => true,
                'application_id' => $application->id,
            ], $request);
            $this->auditLogger->record('demo.repayment_schedule.generated', $user, $loan, [
                'mock_integration' => true,
                'schedule_count' => $loan->schedules()->count(),
            ], $request);
            $this->auditLogger->record('demo.ledger_entries.created', $user, $transaction, [
                'mock_integration' => true,
                'entry_count' => JournalEntry::where('reference', $transaction->reference)->count(),
                'reference' => $transaction->reference,
            ], $request);
            $this->auditLogger->record('demo.disbursement.recorded', $user, $mobileMoney, [
                'mock_integration' => true,
                'provider' => 'mock',
            ], $request);

            return [
                'offer' => $offer->fresh(),
                'loan' => $loan->fresh(['schedules']),
                'transaction' => $transaction->fresh(),
                'mobile_money' => $mobileMoney->fresh(),
                'ledger_entries' => JournalEntry::where('reference', $transaction->reference)->get(),
                'repayment_schedule' => $loan->fresh()->schedules()->get(),
            ];
        });
    }

    public function snapshot(): array
    {
        return [
            'customers' => User::where('role', User::ROLE_CUSTOMER)->latest()->take(20)->get(),
            'applications' => LoanApplication::with(['user', 'loanProduct', 'loanProductTerm', 'loan'])->latest()->take(20)->get(),
            'decisions' => DemoLoanDecision::with('application')->latest()->take(20)->get(),
            'offers' => DemoLoanOffer::with('application')->latest()->take(20)->get(),
            'loans' => Loan::with(['user', 'loanApplication'])->latest()->take(20)->get(),
            'ledger_entries' => JournalEntry::latest()->take(50)->get(),
            'repayment_schedules' => Loan::with('schedules')->latest()->take(20)->get()
                ->flatMap(fn (Loan $loan) => $loan->schedules)
                ->values(),
            'mobile_money' => MobileMoneyTransaction::latest()->take(20)->get(),
            'audit_trail' => \App\Models\AuditLog::latest()->take(50)->get(),
        ];
    }

    private function hasCreditConsent(User $user): bool
    {
        return DemoConsent::where('user_id', $user->id)
            ->where('purpose', DemoConsent::PURPOSE_CREDIT_PROCESSING)
            ->where('status', DemoConsent::STATUS_GRANTED)
            ->exists();
    }

    private function decisionFor(LoanApplication $application): DemoLoanDecision
    {
        $amount = (int) $application->amount;
        $monthlyIncome = 1200000;
        $estimatedObligation = (int) round($amount / 3);
        $approved = $amount <= 500000 && $estimatedObligation <= ($monthlyIncome * 0.35);

        $reasonCodes = ['KYC_VERIFIED', 'CONSENT_GRANTED', 'MOCK_AFFORDABILITY_CHECK'];
        $reasonCodes[] = $approved ? 'DEBT_SERVICE_WITHIN_LIMIT' : 'REQUESTED_AMOUNT_ABOVE_DEMO_LIMIT';

        $decision = DemoLoanDecision::create([
            'loan_application_id' => $application->id,
            'user_id' => $application->user_id,
            'status' => $approved ? DemoLoanDecision::STATUS_APPROVED : DemoLoanDecision::STATUS_DECLINED,
            'requested_amount_minor' => $amount,
            'approved_amount_minor' => $approved ? $amount : 0,
            'monthly_income_minor' => $monthlyIncome,
            'estimated_monthly_obligation_minor' => $estimatedObligation,
            'reason_codes' => $reasonCodes,
            'decision_summary' => $approved
                ? 'Approved by mock affordability rules for investor demo only.'
                : 'Declined by mock affordability rules for investor demo only.',
            'decided_at' => now(),
        ]);

        $application->update([
            'status' => $approved ? 'Approved' : 'Rejected',
            $approved ? 'approved_at' : 'rejected_at' => now(),
        ]);

        $this->auditLogger->record('demo.loan_decision.created', $application->user, $decision, [
            'mock_integration' => true,
            'reason_codes' => $reasonCodes,
        ]);

        return $decision;
    }

    private function offerFor(DemoLoanDecision $decision): DemoLoanOffer
    {
        $application = $decision->application()->with('loanProductTerm')->firstOrFail();
        $term = $application->loanProductTerm;
        $repayment = Loan::getRepaymentAmount(
            ((float) $term->interest_rate) / 100,
            (float) $decision->approved_amount_minor,
            $term->interest_type,
            Loan::getInstallments($term->duration, $term->repayment_frequency),
            $term->interest_cycle,
            $term->duration,
        );

        $offer = DemoLoanOffer::create([
            'demo_loan_decision_id' => $decision->id,
            'loan_application_id' => $application->id,
            'user_id' => $application->user_id,
            'status' => DemoLoanOffer::STATUS_PENDING,
            'principal_amount_minor' => $decision->approved_amount_minor,
            'total_repayment_minor' => (int) round($repayment),
            'duration_days' => $term->duration,
            'interest_rate' => $term->interest_rate,
            'interest_type' => $term->interest_type,
            'repayment_frequency' => $term->repayment_frequency,
            'expires_at' => now()->addDay(),
        ]);

        $this->auditLogger->record('demo.loan_offer.created', $application->user, $offer, [
            'mock_integration' => true,
            'decision_id' => $decision->id,
        ]);

        return $offer;
    }

    private function ensureDemoAccounts(LoanApplication $application): void
    {
        Account::firstOrCreate(
            ['name' => 'Airtel Disbursement'],
            ['balance' => 5000000],
        );
        Account::firstOrCreate(
            ['loan_product_id' => $application->loan_product_id],
            ['name' => $application->loanProduct->name . ' Portfolio', 'balance' => 0],
        );
    }
}
