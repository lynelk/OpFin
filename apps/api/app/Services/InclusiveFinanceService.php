<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\CreditProfile;
use App\Models\KycCase;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class InclusiveFinanceService
{
    public const MEASUREMENT_ONLY_KEYS = [
        'gender',
        'age_cohort',
        'disability_status',
        'refugee_or_displaced_status',
        'rural_urban',
        'employment_category',
        'first_time_formal_borrower',
    ];

    public const PROTECTED_SIGNAL_KEYS = [
        'gender',
        'sex',
        'age',
        'age_cohort',
        'age_group',
        'date_of_birth',
        'disability',
        'disability_status',
        'pwd_status',
        'refugee_status',
        'refugee_or_displaced_status',
        'displacement_status',
        'rural_urban',
        'employment_category',
        'first_time_formal_borrower',
    ];

    public const PROGRAMME_ELIGIBILITY_FIELDS = [
        'gender',
        'age_cohort',
        'disability_status',
        'refugee_or_displaced_status',
        'rural_urban',
        'employment_category',
        'first_time_formal_borrower',
        'kyc_verified',
        'financial_reputation_stage',
    ];

    public const SUPPORT_INSTRUMENT_TYPES = [
        'salary_undertaking',
        'employer_guarantee',
        'group_guarantee',
        'savings_pledge',
        'receivable',
        'insurance_guarantee',
        'warehouse_receipt',
        'asset_evidence',
        'development_guarantee',
        'other',
    ];

    public function profile(User $user): array
    {
        $profile = DB::table('inclusive_finance_profiles')->where('user_id', $user->id)->first();

        return [
            'programme_measurement_consent' => (bool) ($profile?->programme_measurement_consent ?? false),
            'measurement_attributes' => (bool) ($profile?->programme_measurement_consent ?? false)
                ? $this->json($profile?->measurement_attributes)
                : [],
            'service_preferences' => $this->json($profile?->service_preferences),
            'accessibility_preferences' => $user->accessibility_preferences ?? [],
            'preferred_language' => $user->preferred_language ?? 'en',
            'measurement_only_fields' => self::MEASUREMENT_ONLY_KEYS,
            'decisioning_use_allowed' => false,
            'notice' => 'Voluntary inclusion attributes support service adaptation and aggregate programme reporting. They are not credit-risk inputs.',
        ];
    }

    public function updateProfile(User $user, array $data): array
    {
        $existing = DB::table('inclusive_finance_profiles')->where('user_id', $user->id)->first();
        $consent = (bool) ($data['programme_measurement_consent'] ?? $existing?->programme_measurement_consent ?? false);

        $attributes = array_key_exists('measurement_attributes', $data)
            ? $this->filterMeasurementAttributes((array) $data['measurement_attributes'])
            : $this->json($existing?->measurement_attributes);

        if ($attributes !== [] && ! $consent) {
            throw new InvalidArgumentException('Programme measurement consent is required before voluntary inclusion attributes can be stored.');
        }

        if (! $consent) {
            $attributes = [];
        }

        $servicePreferences = array_key_exists('service_preferences', $data)
            ? collect((array) $data['service_preferences'])
                ->only(['assisted_onboarding', 'preferred_support_channel'])
                ->reject(fn ($value) => $value === null || $value === '')
                ->all()
            : $this->json($existing?->service_preferences);

        $wasConsented = (bool) ($existing?->programme_measurement_consent ?? false);
        $consentedAt = $existing?->consented_at;
        $withdrawnAt = $existing?->withdrawn_at;
        if ($consent && ! $wasConsented) {
            $consentedAt = now();
            $withdrawnAt = null;
        } elseif (! $consent && $wasConsented) {
            $withdrawnAt = now();
        }

        DB::table('inclusive_finance_profiles')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'programme_measurement_consent' => $consent,
                'measurement_attributes' => $attributes === [] ? null : json_encode($attributes),
                'service_preferences' => $servicePreferences === [] ? null : json_encode($servicePreferences),
                'consented_at' => $consentedAt,
                'withdrawn_at' => $withdrawnAt,
                'created_at' => $existing?->created_at ?? now(),
                'updated_at' => now(),
            ],
        );

        return $this->profile($user->fresh());
    }

    public function capability(User $user): array
    {
        $profile = CreditProfile::query()->where('user_id', $user->id)->first();
        $verifiedKyc = KycCase::query()
            ->where('user_id', $user->id)
            ->where('status', KycCase::STATUS_VERIFIED)
            ->exists();
        $clearedLoans = Loan::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('status', 'Cleared')
            ->count();

        $activeLoans = Loan::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['Cleared', 'Cancelled', 'Rejected', 'Reversed'])
            ->count();

        $budgetCount = Schema::hasTable('financial_budgets')
            ? DB::table('financial_budgets')->where('user_id', $user->id)->where('active', true)->count()
            : 0;

        $savingsGoalCount = Schema::hasTable('savings_goals')
            ? DB::table('savings_goals')->where('user_id', $user->id)->count()
            : 0;

        $guidance = [];
        if (! $verifiedKyc) {
            $guidance[] = $this->guidance('VERIFY_IDENTITY', 'Verify your identity', 'A verified identity helps you use regulated financial services safely.');
        }
        if ($budgetCount === 0) {
            $guidance[] = $this->guidance('CREATE_BUDGET', 'Create a simple budget', 'Start with what comes in, essential commitments and what remains.');
        }
        if ((int) ($profile?->total_outstanding_minor ?? 0) > 0 || $activeLoans > 0) {
            $guidance[] = $this->guidance('PLAN_REPAYMENT', 'Plan your next repayment', 'Review what is due before taking on another commitment.');
        }
        if ($savingsGoalCount === 0) {
            $guidance[] = $this->guidance('START_SAVINGS_GOAL', 'Set a savings goal', 'A small regular target can strengthen your financial resilience.');
        }
        if ($clearedLoans === 0 && $verifiedKyc) {
            $guidance[] = $this->guidance('BUILD_REPUTATION', 'Build your financial reputation', 'Good repayment behaviour can create a useful formal track record over time.');
        }

        return [
            'financial_reputation' => $this->reputation($user),
            'financial_position' => [
                'amount_due_minor' => (int) ($profile?->amount_due_minor ?? 0),
                'total_outstanding_minor' => (int) ($profile?->total_outstanding_minor ?? 0),
                'available_to_borrow_minor' => (int) ($profile?->available_to_borrow_minor ?? 0),
                'next_due_date' => $profile?->next_due_date?->toDateString(),
            ],
            'guidance' => $guidance,
            'guidance_version' => 'inclusive-finance-v1',
            'principle' => 'Capability guidance is educational and contextual. It does not override affordability, eligibility or regulated product controls.',
        ];
    }

    public function recordCapabilityEvent(User $user, array $data): array
    {
        $this->assertSpaceMembership($user, $data['financial_space_id'] ?? null);

        $id = DB::table('financial_capability_events')->insertGetId([
            'user_id' => $user->id,
            'financial_space_id' => $data['financial_space_id'] ?? null,
            'event_type' => $data['event_type'],
            'intervention_code' => $data['intervention_code'] ?? null,
            'context' => isset($data['context']) ? json_encode($data['context']) : null,
            'outcome' => isset($data['outcome']) ? json_encode($data['outcome']) : null,
            'guidance_version' => $data['guidance_version'] ?? 'inclusive-finance-v1',
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => $id, 'recorded' => true];
    }

    public function reputation(User $user): array
    {
        $verifiedKyc = KycCase::query()
            ->where('user_id', $user->id)
            ->where('status', KycCase::STATUS_VERIFIED)
            ->exists();

        $clearedLoans = Loan::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('status', 'Cleared')
            ->count();

        $profile = CreditProfile::query()->where('user_id', $user->id)->first();
        $positiveReports = Schema::hasTable('credit_information_reports')
            ? DB::table('credit_information_reports')
                ->where('user_id', $user->id)
                ->where('status', 'submitted')
                ->whereIn('event_type', ['origination', 'repayment', 'closure'])
                ->count()
            : 0;

        if (! $verifiedKyc) {
            $stage = 'identity_building';
        } elseif ($clearedLoans === 0) {
            $stage = 'starter';
        } elseif ($clearedLoans < 3) {
            $stage = 'building';
        } else {
            $stage = 'established';
        }

        return [
            'stage' => $stage,
            'cleared_loans' => $clearedLoans,
            'positive_credit_reports_submitted' => $positiveReports,
            'profile_status' => $profile?->status ?? 'not_ready',
            'model' => 'reputation-pathway-v1',
            'is_credit_score' => false,
            'explanation' => 'This is a progress pathway built from verified identity and actual financial behaviour. It is not a replacement credit score.',
        ];
    }

    public function signals(User $user): array
    {
        return [
            'signals' => DB::table('alternative_data_signals')
                ->where('user_id', $user->id)
                ->where('source_type', 'user_reported')
                ->orderByDesc('observed_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn ($signal) => $this->customerSignalPayload($signal))
                ->values(),
            'measurement_only_keys' => self::MEASUREMENT_ONLY_KEYS,
            'governance' => 'Customer-submitted signals are not risk eligible. Verified provider signals require active credit-processing consent and an approved scoring policy before they may influence underwriting.',
        ];
    }

    public function storeUserSignal(User $user, array $data): array
    {
        $this->assertSpaceMembership($user, $data['financial_space_id'] ?? null);

        $id = DB::table('alternative_data_signals')->insertGetId([
            'user_id' => $user->id,
            'financial_space_id' => $data['financial_space_id'] ?? null,
            'source_type' => 'user_reported',
            'signal_key' => $data['signal_key'],
            'signal_value' => json_encode($data['signal_value']),
            'purpose' => $data['purpose'],
            'consent_record_id' => null,
            'risk_eligible' => false,
            'verified' => false,
            'provider_reference' => null,
            'provenance' => json_encode(['submitted_by' => 'customer', 'channel' => $data['channel'] ?? 'app']),
            'observed_at' => now(),
            'expires_at' => $data['expires_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->signalPayload(DB::table('alternative_data_signals')->find($id));
    }

    public function ingestProviderSignal(array $data, User $actor): array
    {
        if (isset($data['financial_space_id'])) {
            $subject = User::query()->find($data['user_id']);
            if (! $subject) {
                throw new InvalidArgumentException('Alternative-data subject was not found.');
            }
            $this->assertSpaceMembership($subject, (int) $data['financial_space_id']);
        }

        if (in_array($data['source_type'], ['user_reported', 'programme_measurement'], true)) {
            throw new InvalidArgumentException('Provider signal ingestion requires an independently verifiable source.');
        }

        if (isset($data['consent_record_id'])) {
            $consentBelongsToSubject = ConsentRecord::query()
                ->whereKey($data['consent_record_id'])
                ->where('user_id', $data['user_id'])
                ->exists();
            if (! $consentBelongsToSubject) {
                throw new InvalidArgumentException('The supplied consent record does not belong to the alternative-data subject.');
            }
        }

        $id = DB::table('alternative_data_signals')->insertGetId([
            'user_id' => $data['user_id'],
            'financial_space_id' => $data['financial_space_id'] ?? null,
            'source_type' => $data['source_type'],
            'signal_key' => $data['signal_key'],
            'signal_value' => json_encode($data['signal_value']),
            'purpose' => $data['purpose'] ?? 'credit_assessment',
            'consent_record_id' => $data['consent_record_id'] ?? null,
            'risk_eligible' => false,
            'verified' => false,
            'provider_reference' => $data['provider_reference'],
            'provenance' => json_encode(array_merge(
                (array) ($data['provenance'] ?? []),
                ['ingested_by_user_id' => $actor->id],
            )),
            'observed_at' => $data['observed_at'] ?? now(),
            'expires_at' => $data['expires_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->signalPayload(DB::table('alternative_data_signals')->find($id));
    }

    public function verifySignal(int $signalId, bool $riskEligible, User $actor): array
    {
        return DB::transaction(function () use ($signalId, $riskEligible, $actor) {
            $signal = DB::table('alternative_data_signals')->where('id', $signalId)->lockForUpdate()->first();
            if (! $signal) {
                throw new InvalidArgumentException('Alternative-data signal not found.');
            }

            if ($riskEligible) {
                if ($this->isProtectedSignalKey((string) $signal->signal_key)) {
                    throw new InvalidArgumentException('Protected, accessibility and programme-measurement attributes can never be marked as credit-risk inputs.');
                }
                if (! in_array($signal->purpose, ['credit_assessment', 'affordability'], true)) {
                    throw new InvalidArgumentException('Only approved credit-assessment or affordability signals can be marked as risk eligible.');
                }
                if ($signal->expires_at !== null && now()->greaterThan(Carbon::parse($signal->expires_at))) {
                    throw new InvalidArgumentException('Expired alternative-data signals cannot be marked as risk eligible.');
                }
                if ($signal->source_type === 'user_reported' || ! $signal->provider_reference) {
                    throw new InvalidArgumentException('Risk-eligible signals require independently verifiable provider provenance.');
                }

                $consent = ConsentRecord::query()
                    ->where('user_id', $signal->user_id)
                    ->where('purpose', ConsentRecord::PURPOSE_CREDIT_PROCESSING)
                    ->where('status', ConsentRecord::STATUS_GRANTED)
                    ->latest('granted_at')
                    ->first();

                if (! $consent) {
                    throw new InvalidArgumentException('Active credit-processing consent is required before a signal can be marked risk eligible.');
                }
            }

            $provenance = $this->json($signal->provenance);
            $provenance['verified_by_user_id'] = $actor->id;
            $provenance['verified_at'] = now()->toIso8601String();

            DB::table('alternative_data_signals')->where('id', $signalId)->update([
                'verified' => true,
                'risk_eligible' => $riskEligible,
                'consent_record_id' => $riskEligible ? ($consent?->id ?? $signal->consent_record_id) : $signal->consent_record_id,
                'provenance' => json_encode($provenance),
                'updated_at' => now(),
            ]);

            return $this->signalPayload(DB::table('alternative_data_signals')->find($signalId));
        });
    }

    public function programmes(User $user): array
    {
        $programmes = DB::table('inclusive_finance_programmes')
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderBy('name')
            ->get()
            ->map(function ($programme) use ($user) {
                $status = DB::table('inclusive_finance_enrolments')
                    ->where('programme_id', $programme->id)
                    ->where('user_id', $user->id)
                    ->value('status');

                return $this->customerProgrammePayload(
                    $programme,
                    $status ? (string) $status : null,
                    $this->programmeEligibility($user, $programme),
                );
            })
            ->values();

        return [
            'programmes' => $programmes,
            'targeting_notice' => 'Target-population fields support outreach, eligibility and reporting. They are not credit-risk variables.',
        ];
    }

    public function adminProgrammes(): array
    {
        $programmes = DB::table('inclusive_finance_programmes')
            ->orderByDesc('id')
            ->get()
            ->map(function ($programme) {
                $payload = $this->programmePayload($programme);
                $payload['enrolled_people'] = DB::table('inclusive_finance_enrolments')
                    ->where('programme_id', $programme->id)
                    ->where('status', 'enrolled')
                    ->distinct('user_id')
                    ->count('user_id');

                return $payload;
            })
            ->values();

        return ['programmes' => $programmes];
    }

    public function createProgramme(array $data): array
    {
        $code = $this->normaliseProgrammeCode($data['code']);
        $this->assertProgrammeCodeAvailable($code);
        $this->assertProgrammeWindow($data['starts_at'] ?? null, $data['ends_at'] ?? null);

        $id = DB::table('inclusive_finance_programmes')->insertGetId([
            'code' => $code,
            'name' => $data['name'],
            'sponsor_space_id' => $data['sponsor_space_id'] ?? null,
            'partner_id' => $data['partner_id'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'target_population' => isset($data['target_population']) ? json_encode($data['target_population']) : null,
            'eligibility_rules' => isset($data['eligibility_rules']) ? json_encode($data['eligibility_rules']) : null,
            'product_config' => isset($data['product_config']) ? json_encode($data['product_config']) : null,
            'reporting_config' => isset($data['reporting_config']) ? json_encode($data['reporting_config']) : null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->programmePayload(DB::table('inclusive_finance_programmes')->find($id));
    }

    public function updateProgramme(int $programmeId, array $data): array
    {
        $programme = DB::table('inclusive_finance_programmes')->find($programmeId);
        if (! $programme) {
            throw new InvalidArgumentException('Inclusive-finance programme not found.');
        }

        $effectiveStartsAt = array_key_exists('starts_at', $data) ? $data['starts_at'] : $programme->starts_at;
        $effectiveEndsAt = array_key_exists('ends_at', $data) ? $data['ends_at'] : $programme->ends_at;
        $this->assertProgrammeWindow($effectiveStartsAt, $effectiveEndsAt);

        $update = ['updated_at' => now()];
        if (array_key_exists('code', $data)) {
            $code = $this->normaliseProgrammeCode($data['code']);
            $this->assertProgrammeCodeAvailable($code, $programmeId);
            $update['code'] = $code;
        }
        foreach (['name', 'sponsor_space_id', 'partner_id', 'status', 'starts_at', 'ends_at'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        foreach (['target_population', 'eligibility_rules', 'product_config', 'reporting_config'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field] === null ? null : json_encode($data[$field]);
            }
        }

        DB::table('inclusive_finance_programmes')->where('id', $programmeId)->update($update);

        return $this->programmePayload(DB::table('inclusive_finance_programmes')->find($programmeId));
    }

    public function enrol(User $user, int $programmeId, array $data): array
    {
        $this->assertSpaceMembership($user, $data['financial_space_id'] ?? null);

        $programme = DB::table('inclusive_finance_programmes')
            ->where('id', $programmeId)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->first();
        if (! $programme) {
            throw new InvalidArgumentException('This programme is not open for enrolment.');
        }

        $eligibility = $this->programmeEligibility($user, $programme);
        if ($eligibility['status'] !== 'eligible') {
            throw new InvalidArgumentException(
                $eligibility['status'] === 'incomplete'
                    ? 'This programme needs additional voluntary or verified eligibility information before enrolment.'
                    : 'This account does not meet the configured programme eligibility rules.'
            );
        }

        return DB::transaction(function () use ($user, $programme, $data, $eligibility) {
            $existing = DB::table('inclusive_finance_enrolments')
                ->where('programme_id', $programme->id)
                ->where('user_id', $user->id)
                ->first();

            DB::table('inclusive_finance_enrolments')->updateOrInsert(
                ['programme_id' => $programme->id, 'user_id' => $user->id],
                [
                    'financial_space_id' => $data['financial_space_id'] ?? null,
                    'status' => 'enrolled',
                    'eligibility_evidence' => json_encode([
                        'system_assessment' => $eligibility,
                        'customer_evidence' => (array) ($data['eligibility_evidence'] ?? []),
                    ]),
                    'enrolled_at' => $existing?->enrolled_at ?? now(),
                    'exited_at' => null,
                    'created_at' => $existing?->created_at ?? now(),
                    'updated_at' => now(),
                ],
            );

            $enrolment = DB::table('inclusive_finance_enrolments')
                ->where('programme_id', $programme->id)
                ->where('user_id', $user->id)
                ->first();

            if (! $existing || $existing->status !== 'enrolled') {
                DB::table('impact_events')->insert([
                    'programme_id' => $programme->id,
                    'enrolment_id' => $enrolment->id,
                    'user_id' => $user->id,
                    'financial_space_id' => $enrolment->financial_space_id,
                    'event_type' => 'programme_enrolled',
                    'outcome_code' => 'enrolled',
                    'numeric_value' => null,
                    'metadata' => json_encode(['source' => 'customer_enrolment']),
                    'occurred_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return [
                'enrolment' => $enrolment,
                'programme' => $this->customerProgrammePayload($programme, 'enrolled', $eligibility),
                'measurement_consent' => $this->profile($user)['programme_measurement_consent'],
            ];
        });
    }

    public function supportInstruments(User $user): array
    {
        return [
            'instruments' => DB::table('credit_support_instruments')
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->get()
                ->map(fn ($instrument) => $this->instrumentPayload($instrument))
                ->values(),
            'supported_types' => self::SUPPORT_INSTRUMENT_TYPES,
            'notice' => 'Recording evidence does not itself change credit eligibility. A product policy must explicitly recognise a verified support instrument.',
        ];
    }

    public function storeSupportInstrument(User $user, array $data): array
    {
        $this->assertSpaceMembership($user, $data['financial_space_id'] ?? null);
        if (isset($data['loan_application_id'])) {
            $owned = DB::table('loan_applications')
                ->where('id', $data['loan_application_id'])
                ->where('user_id', $user->id)
                ->exists();
            if (! $owned) {
                throw new InvalidArgumentException('The selected loan application does not belong to this customer.');
            }
        }

        $id = DB::table('credit_support_instruments')->insertGetId([
            'user_id' => $user->id,
            'financial_space_id' => $data['financial_space_id'] ?? null,
            'loan_application_id' => $data['loan_application_id'] ?? null,
            'instrument_type' => $data['instrument_type'],
            'provider_name' => $data['provider_name'] ?? null,
            'external_reference' => $data['external_reference'] ?? null,
            'value_minor' => $data['value_minor'] ?? null,
            'currency' => strtoupper($data['currency'] ?? 'UGX'),
            'verification_status' => 'pending',
            'evidence' => isset($data['evidence']) ? json_encode($data['evidence']) : null,
            'verified_by' => null,
            'verified_at' => null,
            'expires_at' => $data['expires_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->instrumentPayload(DB::table('credit_support_instruments')->find($id));
    }

    public function verifySupportInstrument(int $instrumentId, string $status, User $actor): array
    {
        if (! in_array($status, ['verified', 'rejected'], true)) {
            throw new InvalidArgumentException('Verification status must be verified or rejected.');
        }

        $instrument = DB::table('credit_support_instruments')->find($instrumentId);
        if (! $instrument) {
            throw new InvalidArgumentException('Credit-support instrument not found.');
        }

        if ($status === 'verified' && $instrument->expires_at !== null && now()->greaterThan(Carbon::parse($instrument->expires_at))) {
            throw new InvalidArgumentException('Expired credit-support evidence cannot be verified.');
        }

        if ($status === 'verified' && in_array($instrument->instrument_type, ['warehouse_receipt', 'receivable', 'asset_evidence'], true)) {
            if (! $instrument->provider_name || ! $instrument->external_reference) {
                throw new InvalidArgumentException('Externally evidenced collateral requires a provider name and reference before verification.');
            }
        }

        DB::table('credit_support_instruments')->where('id', $instrumentId)->update([
            'verification_status' => $status,
            'verified_by' => $actor->id,
            'verified_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->instrumentPayload(DB::table('credit_support_instruments')->find($instrumentId));
    }

    public function fairTreatment(User $user): array
    {
        $decision = Schema::hasTable('credit_decisions')
            ? DB::table('credit_decisions')->where('user_id', $user->id)->orderByDesc('id')->first()
            : null;

        return [
            'latest_decision' => $decision ? [
                'id' => $decision->id,
                'status' => $decision->status,
                'reason_codes' => $this->json($decision->reason_codes),
                'policy_version' => $decision->policy_version,
                'decision_summary' => $decision->decision_summary,
            ] : null,
            'protected_or_measurement_attributes_used' => null,
            'protected_or_measurement_attributes_used_status' => 'not_determined_by_this_endpoint',
            'inclusive_finance_measurement_fields_allowed_as_risk_inputs' => false,
            'certifies_model_fairness' => false,
            'measurement_only_fields' => self::MEASUREMENT_ONLY_KEYS,
            'explanation' => 'OpFin programme-measurement attributes are stored outside the credit-decision inputs and are not eligible risk inputs. This endpoint does not certify the fairness of external scoring models or providers.',
        ];
    }

    public function assessApplication(int $applicationId): array
    {
        $application = DB::table('loan_applications')->find($applicationId);
        if (! $application) {
            throw new InvalidArgumentException('Loan application not found.');
        }

        $decision = Schema::hasTable('credit_decisions')
            ? DB::table('credit_decisions')->where('loan_application_id', $applicationId)->first()
            : null;
        $reasons = $decision ? $this->json($decision->reason_codes) : [];

        $forbiddenMarkers = ['GENDER', 'DISABILITY', 'REFUGEE', 'RURAL_URBAN', 'AGE_COHORT'];
        $forbiddenUsed = collect($reasons)->contains(function ($reason) use ($forbiddenMarkers) {
            $upper = strtoupper((string) $reason);
            return collect($forbiddenMarkers)->contains(fn ($marker) => str_contains($upper, $marker));
        });

        $status = $forbiddenUsed ? 'failed' : ($decision && $reasons !== [] ? 'passed' : 'review');
        $assessmentReasons = array_values(array_filter([
            $forbiddenUsed ? 'MEASUREMENT_ATTRIBUTE_FOUND_IN_DECISION_REASON' : 'MEASUREMENT_ATTRIBUTES_EXCLUDED',
            ! $decision ? 'DECISION_NOT_AVAILABLE' : null,
            $decision && $reasons === [] ? 'DECISION_REASON_CODES_MISSING' : null,
        ]));

        $id = DB::table('fair_treatment_assessments')->insertGetId([
            'user_id' => $application->user_id,
            'loan_application_id' => $applicationId,
            'credit_decision_id' => $decision?->id,
            'assessment_type' => 'decision_review',
            'status' => $status,
            'reason_codes' => json_encode($assessmentReasons),
            'metrics' => json_encode([
                'decision_status' => $decision?->status,
                'reason_code_count' => count($reasons),
                'protected_attribute_inputs' => [],
                'review_scope' => 'decision_reason_codes',
                'certifies_model_fairness' => false,
            ]),
            'policy_version' => 'fair-treatment-v1',
            'assessed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'assessment_id' => $id,
            'status' => $status,
            'reason_codes' => $assessmentReasons,
            'credit_decision_id' => $decision?->id,
            'review_scope' => 'decision_reason_codes',
            'certifies_model_fairness' => false,
            'notice' => 'This automated review checks decision reason codes for prohibited inclusion markers. It does not certify the fairness of an external model or provider.',
        ];
    }

    public function impactSummary(?int $programmeId = null): array
    {
        $enrolmentsQuery = DB::table('inclusive_finance_enrolments')->where('status', 'enrolled');
        if ($programmeId !== null) {
            $enrolmentsQuery->where('programme_id', $programmeId);
        }
        $enrolments = $enrolmentsQuery->get();
        $userIds = $enrolments->pluck('user_id')->unique()->values()->all();

        $applicationIds = [];
        $decisionCounts = [];
        $applications = 0;
        $nplCount = 0;
        $averageApproved = 0;
        $capabilityEventCounts = [];

        if ($userIds !== []) {
            $applicationIds = DB::table('loan_applications as applications')
                ->join('inclusive_finance_enrolments as enrolments', 'enrolments.user_id', '=', 'applications.user_id')
                ->where('enrolments.status', 'enrolled')
                ->when($programmeId !== null, fn ($query) => $query->where('enrolments.programme_id', $programmeId))
                ->where(function ($query) {
                    $query->whereNull('enrolments.enrolled_at')
                        ->orWhereColumn('applications.created_at', '>=', 'enrolments.enrolled_at');
                })
                ->where(function ($query) {
                    $query->whereNull('enrolments.exited_at')
                        ->orWhereColumn('applications.created_at', '<=', 'enrolments.exited_at');
                })
                ->distinct()
                ->pluck('applications.id')
                ->all();

            $applications = count($applicationIds);

            if ($applicationIds !== [] && Schema::hasTable('credit_decisions')) {
                $decisionCounts = DB::table('credit_decisions')
                    ->whereIn('loan_application_id', $applicationIds)
                    ->select('status', DB::raw('COUNT(*) as total'))
                    ->groupBy('status')
                    ->pluck('total', 'status')
                    ->map(fn ($value) => (int) $value)
                    ->all();
                $approvedAmounts = DB::table('credit_decisions')
                    ->whereIn('loan_application_id', $applicationIds)
                    ->where('status', 'approved')
                    ->pluck('approved_amount_minor')
                    ->map(fn ($value) => (int) $value)
                    ->all();
                if ($approvedAmounts !== []) {
                    $approvedTotal = array_sum($approvedAmounts);
                    $approvedCount = count($approvedAmounts);
                    $averageApproved = intdiv($approvedTotal + intdiv($approvedCount, 2), $approvedCount);
                }
            }

            if ($applicationIds !== [] && Schema::hasTable('loans') && Schema::hasColumn('loans', 'non_performing_at')) {
                $nplCount = DB::table('loans')
                    ->whereIn('loan_application_id', $applicationIds)
                    ->whereNotNull('non_performing_at')
                    ->count();
            }

            $capabilityEventIds = DB::table('financial_capability_events as events')
                ->join('inclusive_finance_enrolments as enrolments', 'enrolments.user_id', '=', 'events.user_id')
                ->where('enrolments.status', 'enrolled')
                ->when($programmeId !== null, fn ($query) => $query->where('enrolments.programme_id', $programmeId))
                ->where(function ($query) {
                    $query->whereNull('enrolments.enrolled_at')
                        ->orWhereColumn('events.occurred_at', '>=', 'enrolments.enrolled_at');
                })
                ->where(function ($query) {
                    $query->whereNull('enrolments.exited_at')
                        ->orWhereColumn('events.occurred_at', '<=', 'enrolments.exited_at');
                })
                ->distinct()
                ->pluck('events.id')
                ->all();

            if ($capabilityEventIds !== []) {
                $capabilityEventCounts = DB::table('financial_capability_events')
                    ->whereIn('id', $capabilityEventIds)
                    ->select('event_type', DB::raw('COUNT(*) as total'))
                    ->groupBy('event_type')
                    ->pluck('total', 'event_type')
                    ->map(fn ($value) => (int) $value)
                    ->all();
            }
        }

        $eventQuery = DB::table('impact_events');
        if ($programmeId !== null) {
            $eventQuery->where('programme_id', $programmeId);
        }
        $eventCounts = $eventQuery
            ->select('event_type', DB::raw('COUNT(*) as total'))
            ->groupBy('event_type')
            ->pluck('total', 'event_type')
            ->map(fn ($value) => (int) $value)
            ->all();

        return [
            'programme_id' => $programmeId,
            'enrolled_people' => count($userIds),
            'applications' => $applications,
            'decisions' => $decisionCounts,
            'average_approved_amount_minor' => $averageApproved,
            'npl_count' => $nplCount,
            'impact_events' => $eventCounts,
            'participant_capability_events' => $capabilityEventCounts,
            'cohorts' => $this->cohortSummary($userIds),
            'measurement_notes' => [
                'credit_outcomes_window' => 'Only applications created after programme enrolment and before exit are counted.',
                'capability_events_window' => 'Participant capability events are observed after enrolment; they are not claimed as programme-caused without a directly attributed intervention.',
            ],
            'privacy' => [
                'minimum_cohort_size' => 5,
                'small_cohorts_suppressed' => true,
                'only_consented_measurement_profiles_included' => true,
            ],
        ];
    }

    private function cohortSummary(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $counts = [];
        $profiles = DB::table('inclusive_finance_profiles')
            ->whereIn('user_id', $userIds)
            ->where('programme_measurement_consent', true)
            ->get();

        foreach ($profiles as $profile) {
            foreach ($this->json($profile->measurement_attributes) as $key => $value) {
                if (! in_array($key, self::MEASUREMENT_ONLY_KEYS, true) || is_array($value) || is_object($value)) {
                    continue;
                }
                $label = is_bool($value) ? ($value ? 'yes' : 'no') : trim((string) $value);
                if ($label === '') {
                    continue;
                }
                $counts[$key][$label] = ($counts[$key][$label] ?? 0) + 1;
            }
        }

        $result = [];
        foreach ($counts as $key => $groups) {
            // Suppress the whole dimension when any bucket is below the privacy
            // threshold. Returning the large buckets while hiding a small one can
            // allow the hidden value to be reconstructed from programme totals.
            if (collect($groups)->contains(fn ($count) => $count < 5)) {
                continue;
            }

            foreach ($groups as $label => $count) {
                $result[$key][$label] = $count;
            }
        }

        return $result;
    }

    private function filterMeasurementAttributes(array $attributes): array
    {
        return collect($attributes)
            ->only(self::MEASUREMENT_ONLY_KEYS)
            ->reject(fn ($value) => $value === null || $value === '')
            ->all();
    }

    private function programmeEligibility(User $user, object $programme): array
    {
        $rules = $this->json($programme->eligibility_rules);
        if ($rules === []) {
            return ['status' => 'eligible', 'reason_codes' => [], 'policy' => 'no_configured_rules'];
        }

        $profile = DB::table('inclusive_finance_profiles')->where('user_id', $user->id)->first();
        $measurement = ($profile?->programme_measurement_consent ?? false)
            ? $this->json($profile?->measurement_attributes)
            : [];

        $context = $measurement;
        $context['kyc_verified'] = KycCase::query()
            ->where('user_id', $user->id)
            ->where('status', KycCase::STATUS_VERIFIED)
            ->exists();
        $context['financial_reputation_stage'] = $this->reputation($user)['stage'];

        $allResult = $this->evaluateEligibilityGroup((array) ($rules['all'] ?? []), $context, true);
        $anyRules = (array) ($rules['any'] ?? []);
        $anyResult = $anyRules === []
            ? ['status' => 'eligible', 'reason_codes' => []]
            : $this->evaluateEligibilityGroup($anyRules, $context, false);

        $statuses = [$allResult['status'], $anyResult['status']];
        $status = in_array('ineligible', $statuses, true)
            ? 'ineligible'
            : (in_array('incomplete', $statuses, true) ? 'incomplete' : 'eligible');

        return [
            'status' => $status,
            'reason_codes' => array_values(array_unique(array_merge(
                $allResult['reason_codes'],
                $anyResult['reason_codes'],
            ))),
            'policy' => 'configured_rules_v1',
        ];
    }

    private function evaluateEligibilityGroup(array $rules, array $context, bool $requireAll): array
    {
        if ($rules === []) {
            return ['status' => 'eligible', 'reason_codes' => []];
        }

        $results = [];
        $reasons = [];
        foreach ($rules as $index => $rule) {
            if (! is_array($rule)) {
                throw new InvalidArgumentException('Programme eligibility rules are malformed.');
            }

            $field = (string) ($rule['field'] ?? '');
            $operator = (string) ($rule['operator'] ?? '');
            if (! in_array($field, self::PROGRAMME_ELIGIBILITY_FIELDS, true)
                || ! in_array($operator, ['equals', 'in'], true)) {
                throw new InvalidArgumentException('Programme eligibility rules contain an unsupported field or operator.');
            }
            if ($operator === 'equals' && ! array_key_exists('value', $rule)) {
                throw new InvalidArgumentException('Programme eligibility equals rules require a value.');
            }
            if ($operator === 'in' && (! isset($rule['values']) || ! is_array($rule['values']) || $rule['values'] === [])) {
                throw new InvalidArgumentException('Programme eligibility in rules require one or more values.');
            }

            if (! array_key_exists($field, $context)) {
                $results[] = null;
                $reasons[] = 'ELIGIBILITY_INFORMATION_MISSING';
                continue;
            }

            $actual = $context[$field];
            $matched = $operator === 'equals'
                ? $actual === ($rule['value'] ?? null)
                : in_array($actual, (array) ($rule['values'] ?? []), true);
            $results[] = $matched;
            if (! $matched) {
                $reasons[] = 'PROGRAMME_CRITERIA_NOT_MET';
            }
        }

        if ($requireAll) {
            if (in_array(false, $results, true)) {
                return ['status' => 'ineligible', 'reason_codes' => $reasons];
            }
            if (in_array(null, $results, true)) {
                return ['status' => 'incomplete', 'reason_codes' => $reasons];
            }

            return ['status' => 'eligible', 'reason_codes' => []];
        }

        if (in_array(true, $results, true)) {
            return ['status' => 'eligible', 'reason_codes' => []];
        }
        if (in_array(null, $results, true)) {
            return ['status' => 'incomplete', 'reason_codes' => $reasons];
        }

        return ['status' => 'ineligible', 'reason_codes' => $reasons];
    }

    private function customerProgrammePayload(
        object $programme,
        ?string $enrolmentStatus = null,
        ?array $eligibility = null,
    ): array {
        return [
            'id' => $programme->id,
            'code' => $programme->code,
            'name' => $programme->name,
            'status' => $programme->status,
            'starts_at' => $programme->starts_at,
            'ends_at' => $programme->ends_at,
            'enrolment_status' => $enrolmentStatus,
            'eligibility' => $eligibility,
        ];
    }

    private function programmePayload(object $programme, ?string $enrolmentStatus = null): array
    {
        return [
            'id' => $programme->id,
            'code' => $programme->code,
            'name' => $programme->name,
            'sponsor_space_id' => $programme->sponsor_space_id,
            'partner_id' => $programme->partner_id,
            'status' => $programme->status,
            'target_population' => $this->json($programme->target_population),
            'eligibility_rules' => $this->json($programme->eligibility_rules),
            'product_config' => $this->json($programme->product_config),
            'reporting_config' => $this->json($programme->reporting_config),
            'starts_at' => $programme->starts_at,
            'ends_at' => $programme->ends_at,
            'enrolment_status' => $enrolmentStatus,
        ];
    }

    private function customerSignalPayload(object $signal): array
    {
        return [
            'id' => $signal->id,
            'financial_space_id' => $signal->financial_space_id,
            'source_type' => $signal->source_type,
            'signal_key' => $signal->signal_key,
            'signal_value' => $this->jsonValue($signal->signal_value),
            'purpose' => $signal->purpose,
            'risk_eligible' => false,
            'verified' => false,
            'observed_at' => $signal->observed_at,
            'expires_at' => $signal->expires_at,
        ];
    }

    private function signalPayload(object $signal): array
    {
        return [
            'id' => $signal->id,
            'user_id' => $signal->user_id,
            'financial_space_id' => $signal->financial_space_id,
            'source_type' => $signal->source_type,
            'signal_key' => $signal->signal_key,
            'signal_value' => $this->jsonValue($signal->signal_value),
            'purpose' => $signal->purpose,
            'risk_eligible' => (bool) $signal->risk_eligible,
            'verified' => (bool) $signal->verified,
            'provider_reference' => $signal->provider_reference,
            'provenance' => $this->json($signal->provenance),
            'observed_at' => $signal->observed_at,
            'expires_at' => $signal->expires_at,
        ];
    }

    private function instrumentPayload(object $instrument): array
    {
        return [
            'id' => $instrument->id,
            'financial_space_id' => $instrument->financial_space_id,
            'loan_application_id' => $instrument->loan_application_id,
            'instrument_type' => $instrument->instrument_type,
            'provider_name' => $instrument->provider_name,
            'external_reference' => $instrument->external_reference,
            'value_minor' => $instrument->value_minor === null ? null : (int) $instrument->value_minor,
            'currency' => $instrument->currency,
            'verification_status' => $instrument->verification_status,
            'evidence' => $this->json($instrument->evidence),
            'verified_at' => $instrument->verified_at,
            'expires_at' => $instrument->expires_at,
        ];
    }

    private function normaliseProgrammeCode(string $code): string
    {
        $normalised = strtoupper(trim($code));
        if ($normalised === '') {
            throw new InvalidArgumentException('Programme code is required.');
        }

        return $normalised;
    }

    private function assertProgrammeCodeAvailable(string $code, ?int $ignoreProgrammeId = null): void
    {
        $query = DB::table('inclusive_finance_programmes')->whereRaw('UPPER(code) = ?', [$code]);
        if ($ignoreProgrammeId !== null) {
            $query->where('id', '!=', $ignoreProgrammeId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException('Programme code is already in use.');
        }
    }

    private function assertProgrammeWindow(?string $startsAt, ?string $endsAt): void
    {
        if ($startsAt === null || $endsAt === null) {
            return;
        }

        if (Carbon::parse($endsAt)->lt(Carbon::parse($startsAt))) {
            throw new InvalidArgumentException('Programme end date must be on or after the start date.');
        }
    }

    private function isProtectedSignalKey(string $key): bool
    {
        $normalised = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', $key)));
        $normalised = trim($normalised, '_');

        if (in_array($normalised, self::PROTECTED_SIGNAL_KEYS, true)) {
            return true;
        }

        $tokens = array_values(array_filter(explode('_', $normalised)));
        if (array_intersect($tokens, ['gender', 'sex', 'disability', 'disabled', 'pwd', 'refugee', 'displaced', 'displacement'])) {
            return true;
        }

        if (in_array($normalised, [
            'dob',
            'birth_date',
            'birth_year',
            'year_of_birth',
            'customer_age',
            'applicant_age',
            'borrower_age',
        ], true)) {
            return true;
        }

        return str_contains($normalised, 'age_cohort')
            || str_contains($normalised, 'age_group')
            || str_contains($normalised, 'date_of_birth')
            || str_contains($normalised, 'rural_urban')
            || str_contains($normalised, 'employment_category')
            || str_contains($normalised, 'first_time_formal_borrower');
    }

    private function assertSpaceMembership(User $user, ?int $spaceId): void
    {
        if ($spaceId === null) {
            return;
        }

        $member = DB::table('financial_space_memberships')
            ->where('financial_space_id', $spaceId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if (! $member) {
            throw new InvalidArgumentException('You do not have access to the selected Financial Space.');
        }
    }

    private function guidance(string $code, string $title, string $text): array
    {
        return ['code' => $code, 'title' => $title, 'text' => $text];
    }

    private function json(?string $value): array
    {
        if (! $value) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function jsonValue(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return json_decode($value, true);
    }
}
