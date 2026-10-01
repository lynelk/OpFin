<?php

namespace App\Services\PayrollDeduction;

use App\Models\ConsentRecord;
use App\Models\FinancingApplication;
use App\Models\PayrollDeductionCase;
use App\Models\PayrollDeductionEvent;
use App\Models\PayrollDeductionReconciliation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PayrollDeductionService
{
    private const UNDERTAKING_POLICY_VERSION = 'payroll-undertaking-v1';

    private const UNDERTAKING_SCOPE = 'government_payroll_deduction';

    public function createCase(
        User $user,
        FinancingApplication $application,
        array $data,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): PayrollDeductionCase {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('An idempotency key is required.');
        }

        return DB::transaction(function () use ($user, $application, $data, $idempotencyKey, $correlationId) {
            $lockedApplication = FinancingApplication::query()
                ->with('product')
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedApplication->user_id !== (int) $user->id) {
                throw new InvalidArgumentException('This financing application does not belong to the customer.');
            }
            if (! $lockedApplication->product
                || strtolower((string) $lockedApplication->product->family) !== 'salary_finance') {
                throw new InvalidArgumentException('Payroll deduction is available only for salary-finance applications.');
            }
            if ($lockedApplication->status !== 'submitted') {
                throw new InvalidArgumentException('Payroll deduction requires a submitted salary-finance application.');
            }

            $instructionHash = $this->instructionHash('create_case', [
                'financing_application_id' => (int) $lockedApplication->id,
                'scheme' => $data['scheme'] ?? 'government_pdms',
                'provider' => $data['provider'] ?? 'pdms',
                'vote_code' => $data['vote_code'] ?? null,
                'vote_name' => $data['vote_name'] ?? null,
                'employment_reference_hash' => isset($data['employment_reference'])
                    ? hash('sha256', trim((string) $data['employment_reference']))
                    : null,
                'currency' => strtoupper((string) ($data['currency'] ?? 'UGX')),
            ]);

            $existing = PayrollDeductionCase::where('financing_application_id', $lockedApplication->id)->first();
            if ($existing) {
                $startEvent = PayrollDeductionEvent::query()
                    ->where('payroll_deduction_case_id', $existing->id)
                    ->where('event_type', 'case_started')
                    ->oldest('id')
                    ->first();

                if (! $startEvent || ! hash_equals((string) $startEvent->instruction_hash, $instructionHash)) {
                    throw new InvalidArgumentException(
                        'This salary-finance application already has a payroll case bound to a different start instruction.'
                    );
                }

                return $existing->fresh();
            }

            $case = PayrollDeductionCase::create([
                'reference' => (string) Str::uuid(),
                'financing_application_id' => $lockedApplication->id,
                'user_id' => $user->id,
                'financial_space_id' => $lockedApplication->financial_space_id,
                'scheme' => $data['scheme'] ?? 'government_pdms',
                'provider' => $data['provider'] ?? 'pdms',
                'vote_code' => $data['vote_code'] ?? null,
                'vote_name' => $data['vote_name'] ?? null,
                'employment_reference_hash' => isset($data['employment_reference'])
                    ? hash('sha256', trim((string) $data['employment_reference']))
                    : null,
                'status' => 'affordability_pending',
                'affordability_status' => 'pending',
                'currency' => strtoupper((string) ($data['currency'] ?? 'UGX')),
            ]);

            $this->recordEvent(
                $case,
                $user,
                $idempotencyKey,
                $instructionHash,
                $correlationId,
                'case_started',
                null,
                'affordability_pending',
                ['scheme' => $case->scheme, 'provider' => $case->provider],
            );

            return $case->fresh();
        });
    }

    public function recordAffordability(
        PayrollDeductionCase $case,
        User $actor,
        array $data,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): PayrollDeductionCase {
        return $this->mutate(
            $case,
            $actor,
            $idempotencyKey,
            $correlationId,
            'record_affordability',
            $data,
            function (PayrollDeductionCase $locked) use ($data) {
            $this->requireStatus($locked, ['affordability_pending', 'buyoff_quote_required']);

            $affordable = (bool) $data['affordable'];
            $buyoff = (bool) ($data['buyoff_quote_required'] ?? false);
            $to = $affordable ? 'affordable' : ($buyoff ? 'buyoff_quote_required' : 'unaffordable');

            $locked->fill([
                'status' => $to,
                'affordability_status' => $affordable ? 'affordable' : 'not_affordable',
                'affordable_amount_minor' => $affordable ? (int) ($data['affordable_amount_minor'] ?? 0) : null,
                'last_provider_reference' => $data['provider_reference'] ?? $locked->last_provider_reference,
                'provider_state' => array_merge($locked->provider_state ?? [], [
                    'affordability_checked_at' => now()->toIso8601String(),
                    'buyoff_quote_required' => $buyoff,
                ]),
            ])->save();

            return ['event' => 'affordability_recorded', 'to' => $to, 'evidence' => [
                'affordable' => $affordable,
                'affordable_amount_minor' => $locked->affordable_amount_minor,
                'buyoff_quote_required' => $buyoff,
                'provider_reference' => $data['provider_reference'] ?? null,
            ]];
            },
        );
    }

    public function requestReservation(
        PayrollDeductionCase $case,
        User $customer,
        array $data,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): PayrollDeductionCase {
        if ((int) $case->user_id !== (int) $customer->id) {
            throw new InvalidArgumentException('This payroll deduction case does not belong to the customer.');
        }

        $requested = (int) $data['requested_deduction_minor'];
        $consent = ConsentRecord::query()
            ->whereKey((int) $data['undertaking_consent_record_id'])
            ->where('user_id', $customer->id)
            ->where('purpose', ConsentRecord::PURPOSE_CREDIT_PROCESSING)
            ->where('policy_version', self::UNDERTAKING_POLICY_VERSION)
            ->where('status', ConsentRecord::STATUS_GRANTED)
            ->whereNull('revoked_at')
            ->first();

        $consentMetadata = is_array($consent?->metadata) ? $consent->metadata : [];
        $consentBoundToInstruction = $consent
            && ($consentMetadata['scope'] ?? null) === self::UNDERTAKING_SCOPE
            && (int) ($consentMetadata['payroll_case_id'] ?? 0) === (int) $case->id
            && (string) ($consentMetadata['payroll_case_reference'] ?? '') === (string) $case->reference
            && (int) ($consentMetadata['requested_deduction_minor'] ?? 0) === $requested;

        if (! $consentBoundToInstruction) {
            throw new InvalidArgumentException(
                'A current payroll undertaking consent bound to this case and deduction amount is required before reservation.'
            );
        }

        return $this->mutate(
            $case,
            $customer,
            $idempotencyKey,
            $correlationId,
            'request_reservation',
            $data,
            function (PayrollDeductionCase $locked) use ($data, $consent, $requested) {
                $this->requireStatus($locked, ['affordable', 'amendment_required']);
                if ($locked->affordable_amount_minor !== null
                    && $requested > (int) $locked->affordable_amount_minor) {
                    throw new InvalidArgumentException(
                        'The requested payroll deduction exceeds the verified affordable amount.'
                    );
                }

                $locked->fill([
                    'status' => 'reservation_pending',
                    'requested_deduction_minor' => $requested,
                    'undertaking_consent_record_id' => $consent->id,
                    'provider_agreement_reference' => $data['provider_agreement_reference'] ?? null,
                    'reservation_reference' => null,
                    'reservation_expires_at' => now()->addHours(
                        max(1, (int) config('payroll_deduction.reservation_ttl_hours', 72))
                    ),
                    'rejection_code' => null,
                    'rejection_reason' => null,
                ])->save();

                return ['event' => 'reservation_requested', 'to' => 'reservation_pending', 'evidence' => [
                    'requested_deduction_minor' => $requested,
                    'consent_record_id' => $consent->id,
                    'consent_policy_version' => $consent->policy_version,
                    'consent_scope' => self::UNDERTAKING_SCOPE,
                    'reservation_expires_at' => $locked->reservation_expires_at?->toIso8601String(),
                ]];
            },
        );
    }

    public function recordReservation(
        PayrollDeductionCase $case,
        User $actor,
        array $data,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): PayrollDeductionCase {
        return $this->mutate(
            $case,
            $actor,
            $idempotencyKey,
            $correlationId,
            'record_reservation',
            $data,
            function (PayrollDeductionCase $locked) use ($data) {
                $this->requireStatus($locked, ['reservation_pending']);
                $accepted = (bool) $data['reserved'];
                $to = $accepted ? 'reserved' : 'reservation_failed';
                $reservationReference = trim(
                    (string) ($data['reservation_reference'] ?? $locked->reservation_reference ?? '')
                );
                $agreementReference = trim(
                    (string) ($data['provider_agreement_reference'] ?? $locked->provider_agreement_reference ?? '')
                );

                if ($accepted && ! $locked->undertaking_consent_record_id) {
                    throw new InvalidArgumentException(
                        'Confirmed payroll reservation requires a current undertaking consent.'
                    );
                }
                if ($accepted && $locked->reservation_expires_at && $locked->reservation_expires_at->isPast()) {
                    throw new InvalidArgumentException(
                        'The payroll reservation request has expired and must be authorised again.'
                    );
                }
                if ($accepted && ($reservationReference === '' || $agreementReference === '')) {
                    throw new InvalidArgumentException(
                        'Confirmed payroll reservation requires both the reservation reference and agreement reference.'
                    );
                }

                $locked->fill([
                    'status' => $to,
                    'reservation_reference' => $reservationReference !== '' ? $reservationReference : null,
                    'provider_agreement_reference' => $agreementReference !== '' ? $agreementReference : null,
                    'last_provider_reference' => $data['provider_reference'] ?? $locked->last_provider_reference,
                    'rejection_code' => $accepted ? null : ($data['rejection_code'] ?? 'RESERVATION_FAILED'),
                    'rejection_reason' => $accepted
                        ? null
                        : ($data['rejection_reason'] ?? 'Payroll reservation was not confirmed.'),
                ])->save();

                return [
                    'event' => $accepted ? 'reservation_confirmed' : 'reservation_failed',
                    'to' => $to,
                    'evidence' => [
                        'reservation_reference' => $locked->reservation_reference,
                        'provider_agreement_reference' => $locked->provider_agreement_reference,
                        'provider_reference' => $data['provider_reference'] ?? null,
                        'rejection_code' => $locked->rejection_code,
                    ],
                ];
            },
        );
    }

    public function submitKeyFacts(
        PayrollDeductionCase $case,
        User $actor,
        array $data,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): PayrollDeductionCase {
        return $this->mutate(
            $case,
            $actor,
            $idempotencyKey,
            $correlationId,
            'submit_key_facts',
            $data,
            function (PayrollDeductionCase $locked) use ($data) {
            $this->requireStatus($locked, ['reserved']);
            if ($locked->reservation_expires_at && $locked->reservation_expires_at->isPast()) {
                throw new InvalidArgumentException('The payroll reservation has expired and must be refreshed.');
            }

            $locked->fill([
                'status' => 'vote_approval_pending',
                'key_facts_snapshot' => $data['key_facts'],
                'last_provider_reference' => $data['provider_reference'] ?? $locked->last_provider_reference,
            ])->save();

            return ['event' => 'key_facts_submitted', 'to' => 'vote_approval_pending', 'evidence' => [
                'provider_reference' => $data['provider_reference'] ?? null,
                'key_facts_version' => $data['key_facts']['version'] ?? null,
                'key_facts_snapshot' => $data['key_facts'],
            ]];
            },
        );
    }

    public function recordVoteDecision(
        PayrollDeductionCase $case,
        User $actor,
        array $data,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): PayrollDeductionCase {
        return $this->mutate(
            $case,
            $actor,
            $idempotencyKey,
            $correlationId,
            'record_vote_decision',
            $data,
            function (PayrollDeductionCase $locked) use ($data) {
            $this->requireStatus($locked, ['vote_approval_pending']);
            $approved = (bool) $data['approved'];
            if ($approved && $locked->reservation_expires_at && $locked->reservation_expires_at->isPast()) {
                throw new InvalidArgumentException(
                    'The payroll reservation expired before vote approval and must be refreshed.'
                );
            }
            $to = $approved ? 'deduction_approved' : 'vote_rejected';

            $locked->fill([
                'status' => $to,
                'approved_at' => $approved ? now() : null,
                'reservation_expires_at' => $approved ? $locked->reservation_expires_at : now(),
                'last_provider_reference' => $data['provider_reference'] ?? $locked->last_provider_reference,
                'rejection_code' => $approved ? null : ($data['rejection_code'] ?? 'VOTE_REJECTED'),
                'rejection_reason' => $approved ? null : ($data['rejection_reason'] ?? 'The payroll deduction was not approved at the vote.'),
            ])->save();

            return ['event' => $approved ? 'deduction_approved' : 'vote_rejected', 'to' => $to, 'evidence' => [
                'provider_reference' => $data['provider_reference'] ?? null,
                'rejection_code' => $locked->rejection_code,
            ]];
            },
        );
    }

    public function recordPayrollSubmission(
        PayrollDeductionCase $case,
        User $actor,
        array $data,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): PayrollDeductionCase {
        return $this->mutate($case, $actor, $idempotencyKey, $correlationId, function (PayrollDeductionCase $locked) use ($data) {
            $this->requireStatus($locked, ['deduction_approved']);
            $submissionCode = (string) ($data['submission_code'] ?? '482');
            if ($locked->scheme === 'government_pdms' && $submissionCode !== '482') {
                throw new InvalidArgumentException('Government payroll deduction submissions must use Code 482.');
            }

            $locked->fill([
                'status' => 'payroll_submitted',
                'payroll_submitted_at' => now(),
                'last_provider_reference' => $data['provider_reference'] ?? $locked->last_provider_reference,
                'provider_state' => array_merge($locked->provider_state ?? [], [
                    'payroll_period' => $data['payroll_period'],
                    'submission_file_reference' => $data['submission_file_reference'] ?? null,
                    'submission_code' => $submissionCode,
                ]),
            ])->save();

            return ['event' => 'payroll_submitted', 'to' => 'payroll_submitted', 'evidence' => [
                'payroll_period' => $data['payroll_period'],
                'submission_file_reference' => $data['submission_file_reference'] ?? null,
                'submission_code' => $submissionCode,
            ]];
        });
    }

    public function recordPayrollResult(
        PayrollDeductionCase $case,
        User $actor,
        array $data,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): PayrollDeductionCase {
        return $this->mutate($case, $actor, $idempotencyKey, $correlationId, function (PayrollDeductionCase $locked) use ($data) {
            $this->requireStatus($locked, ['payroll_submitted']);
            $submittedPeriod = (string) (($locked->provider_state ?? [])['payroll_period'] ?? '');
            if ($submittedPeriod !== '' && $submittedPeriod !== (string) $data['payroll_period']) {
                throw new InvalidArgumentException('Payroll result period must match the submitted payroll period.');
            }

            $category = (string) $data['result_category'];
            $allowed = ['success', 'rejected', 'off_payroll_lt_3_months', 'off_payroll_ge_3_months'];
            if (! in_array($category, $allowed, true)) {
                throw new InvalidArgumentException('Unsupported payroll result category.');
            }

            $expected = (int) ($locked->requested_deduction_minor ?? 0);
            $recovered = max(0, (int) ($data['recovered_minor'] ?? 0));
            $variance = $recovered - $expected;
            $success = $category === 'success';
            $to = $success ? 'reconciliation_pending' : 'amendment_required';

            PayrollDeductionReconciliation::updateOrCreate(
                [
                    'payroll_deduction_case_id' => $locked->id,
                    'payroll_period' => $data['payroll_period'],
                ],
                [
                    'expected_minor' => $expected,
                    'recovered_minor' => $recovered,
                    'variance_minor' => $variance,
                    'currency' => $locked->currency,
                    'status' => $success ? 'pending' : 'rejected',
                    'result_category' => $category,
                    'provider_reference' => $data['provider_reference'] ?? null,
                    'evidence' => $data['evidence'] ?? null,
                ],
            );

            $locked->fill([
                'status' => $to,
                'last_provider_reference' => $data['provider_reference'] ?? $locked->last_provider_reference,
                'rejection_code' => $success ? null : ($data['rejection_code'] ?? strtoupper($category)),
                'rejection_reason' => $success ? null : ($data['rejection_reason'] ?? 'Payroll submission requires review and amendment.'),
            ])->save();

            return ['event' => 'payroll_result_recorded', 'to' => $to, 'evidence' => [
                'payroll_period' => $data['payroll_period'],
                'result_category' => $category,
                'expected_minor' => $expected,
                'recovered_minor' => $recovered,
                'variance_minor' => $variance,
                'provider_reference' => $data['provider_reference'] ?? null,
                'provider_evidence' => $data['evidence'] ?? null,
            ]];
        });
    }

    public function amendAfterReject(
        PayrollDeductionCase $case,
        User $actor,
        array $data,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): PayrollDeductionCase {
        return $this->mutate($case, $actor, $idempotencyKey, $correlationId, function (PayrollDeductionCase $locked) use ($data) {
            $this->requireStatus($locked, ['amendment_required']);
            $requested = (int) ($data['requested_deduction_minor'] ?? $locked->requested_deduction_minor ?? 0);
            if ($requested <= 0) {
                throw new InvalidArgumentException('The amended deduction amount must be greater than zero.');
            }
            if ($locked->affordable_amount_minor !== null && $requested > (int) $locked->affordable_amount_minor) {
                throw new InvalidArgumentException('The amended deduction exceeds the verified affordable amount.');
            }

            $locked->fill([
                'status' => 'reserved',
                'requested_deduction_minor' => $requested,
                'provider_agreement_reference' => $data['provider_agreement_reference'] ?? $locked->provider_agreement_reference,
                'reservation_reference' => $data['reservation_reference'] ?? $locked->reservation_reference,
                'reservation_expires_at' => now()->addHours(max(1, (int) config('payroll_deduction.reservation_ttl_hours', 72))),
                'rejection_code' => null,
                'rejection_reason' => null,
            ])->save();

            return ['event' => 'deduction_amended', 'to' => 'reserved', 'evidence' => [
                'requested_deduction_minor' => $requested,
                'reservation_reference' => $locked->reservation_reference,
            ]];
        });
    }

    public function reconcile(
        PayrollDeductionCase $case,
        User $actor,
        array $data,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): PayrollDeductionCase {
        return $this->mutate($case, $actor, $idempotencyKey, $correlationId, function (PayrollDeductionCase $locked) use ($data) {
            $this->requireStatus($locked, ['reconciliation_pending', 'reconciliation_exception']);

            $reconciliation = PayrollDeductionReconciliation::query()
                ->where('payroll_deduction_case_id', $locked->id)
                ->where('payroll_period', (string) $data['payroll_period'])
                ->lockForUpdate()
                ->first();

            if (! $reconciliation) {
                throw new InvalidArgumentException(
                    'Recorded payroll-result evidence is required before payment reconciliation.'
                );
            }
            if ($reconciliation->result_category !== 'success') {
                throw new InvalidArgumentException(
                    'Only a successful payroll result can proceed to payment reconciliation.'
                );
            }

            $expected = (int) $reconciliation->expected_minor;
            $recovered = (int) $data['recovered_minor'];
            if ($recovered < 0) {
                throw new InvalidArgumentException('Recovered payroll amount cannot be negative.');
            }

            $variance = $recovered - $expected;
            $matched = $variance === 0;
            $to = $matched ? 'reconciled' : 'reconciliation_exception';
            $existingEvidence = is_array($reconciliation->evidence) ? $reconciliation->evidence : [];
            $settlementEvidence = [
                'provider_reference' => $data['provider_reference'] ?? null,
                'evidence' => $data['evidence'] ?? null,
                'recorded_at' => now()->toIso8601String(),
            ];

            $reconciliation->fill([
                'recovered_minor' => $recovered,
                'variance_minor' => $variance,
                'status' => $matched ? 'reconciled' : 'exception',
                'evidence' => array_merge($existingEvidence, ['settlement' => $settlementEvidence]),
                'reconciled_at' => $matched ? now() : null,
            ])->save();

            $locked->fill([
                'status' => $to,
                'reconciled_at' => $matched ? now() : null,
                'last_provider_reference' => $data['provider_reference'] ?? $locked->last_provider_reference,
            ])->save();

            return ['event' => $matched ? 'payment_reconciled' : 'reconciliation_exception', 'to' => $to, 'evidence' => [
                'payroll_period' => $data['payroll_period'],
                'expected_minor' => $expected,
                'recovered_minor' => $recovered,
                'variance_minor' => $variance,
                'settlement_provider_reference' => $data['provider_reference'] ?? null,
                'settlement_evidence' => $data['evidence'] ?? null,
            ]];
        });
    }

    public function cancel(
        PayrollDeductionCase $case,
        User $customer,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): PayrollDeductionCase {
        if ((int) $case->user_id !== (int) $customer->id) {
            throw new InvalidArgumentException('This payroll deduction case does not belong to the customer.');
        }

        return $this->mutate($case, $customer, $idempotencyKey, $correlationId, function (PayrollDeductionCase $locked) {
            $this->requireStatus($locked, [
                'affordability_pending', 'buyoff_quote_required', 'unaffordable', 'affordable', 'reservation_pending',
                'reservation_failed', 'reserved', 'vote_approval_pending', 'vote_rejected', 'amendment_required',
            ]);
            $locked->fill(['status' => 'cancelled', 'reservation_expires_at' => now()])->save();

            return ['event' => 'case_cancelled', 'to' => 'cancelled', 'evidence' => []];
        });
    }

    public function expireReservations(): int
    {
        $expired = 0;
        PayrollDeductionCase::query()
            ->whereIn('status', ['reservation_pending', 'reserved', 'vote_approval_pending'])
            ->whereNotNull('reservation_expires_at')
            ->where('reservation_expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($cases) use (&$expired) {
                foreach ($cases as $case) {
                    DB::transaction(function () use ($case, &$expired) {
                        $locked = PayrollDeductionCase::whereKey($case->id)->lockForUpdate()->first();
                        if (! $locked || ! in_array($locked->status, ['reservation_pending', 'reserved', 'vote_approval_pending'], true)) {
                            return;
                        }
                        $from = $locked->status;
                        $locked->fill(['status' => 'expired'])->save();
                        PayrollDeductionEvent::firstOrCreate(
                            [
                                'payroll_deduction_case_id' => $locked->id,
                                'idempotency_key' => 'reservation-expiry:'.($locked->reservation_expires_at?->timestamp ?? $locked->id),
                            ],
                            [
                                'correlation_id' => (string) Str::uuid(),
                                'event_type' => 'reservation_expired',
                                'from_status' => $from,
                                'to_status' => 'expired',
                                'actor_user_id' => null,
                                'channel' => 'scheduler',
                                'evidence' => ['reservation_expires_at' => $locked->reservation_expires_at?->toIso8601String()],
                                'occurred_at' => now(),
                            ],
                        );
                        $expired++;
                    });
                }
            });

        return $expired;
    }

    private function mutate(
        PayrollDeductionCase $case,
        User $actor,
        string $idempotencyKey,
        ?string $correlationId,
        callable $mutation,
    ): PayrollDeductionCase {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('An idempotency key is required.');
        }

        return DB::transaction(function () use ($case, $actor, $idempotencyKey, $correlationId, $mutation) {
            $locked = PayrollDeductionCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            if (PayrollDeductionEvent::where('payroll_deduction_case_id', $locked->id)
                ->where('idempotency_key', $idempotencyKey)
                ->exists()) {
                return $locked->fresh();
            }

            $from = $locked->status;
            $result = $mutation($locked);
            $this->recordEvent(
                $locked,
                $actor,
                $idempotencyKey,
                $correlationId,
                $result['event'],
                $from,
                $result['to'],
                $result['evidence'] ?? [],
            );

            return $locked->fresh();
        });
    }

    private function recordEvent(
        PayrollDeductionCase $case,
        ?User $actor,
        string $idempotencyKey,
        ?string $correlationId,
        string $event,
        ?string $from,
        string $to,
        array $evidence,
    ): void {
        PayrollDeductionEvent::create([
            'payroll_deduction_case_id' => $case->id,
            'correlation_id' => $correlationId ?: (string) Str::uuid(),
            'idempotency_key' => $idempotencyKey,
            'event_type' => $event,
            'from_status' => $from,
            'to_status' => $to,
            'actor_user_id' => $actor?->id,
            'channel' => 'api',
            'evidence' => $evidence,
            'occurred_at' => now(),
        ]);
    }

    private function requireStatus(PayrollDeductionCase $case, array $allowed): void
    {
        if (! in_array($case->status, $allowed, true)) {
            throw new InvalidArgumentException("Payroll deduction cannot move from status [{$case->status}] using this action.");
        }
    }
}
