<?php

namespace App\Services;

use App\Models\User;
use App\Models\Otp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProgrammeDeliveryService
{
    public const CHANNELS = ['app', 'web', 'whatsapp', 'ussd', 'assisted'];

    public const LOCALES = ['en', 'sw', 'lg', 'nyn-ruk', 'fr', 'ar', 'ach'];

    public const ANSWER_TYPES = [
        'text',
        'integer',
        'decimal',
        'boolean',
        'single_choice',
        'multi_choice',
        'currency_minor',
    ];

    public const CONSENT_CLASSIFICATIONS = [
        'programme_measurement',
        'service_adaptation',
        'operational',
    ];

    public const TEMPLATE_CODES = [
        'youth_women_resilience',
        'vsla_formal_bridge',
        'refugee_pwd_inclusion',
        'employer_financial_wellness',
        'msme_livelihood',
    ];

    public function adminInstruments(?int $programmeId = null): array
    {
        $query = DB::table('programme_instruments as instrument')
            ->join('inclusive_finance_programmes as programme', 'programme.id', '=', 'instrument.programme_id')
            ->select([
                'instrument.*',
                'programme.code as programme_code',
                'programme.name as programme_name',
            ])
            ->orderBy('programme.name')
            ->orderBy('instrument.name');

        if ($programmeId) {
            $query->where('instrument.programme_id', $programmeId);
        }

        return [
            'instruments' => $query->get()->map(fn ($row) => $this->instrumentPayload($row, true))->values()->all(),
            'channels' => self::CHANNELS,
            'locales' => self::LOCALES,
            'answer_types' => self::ANSWER_TYPES,
            'consent_classifications' => self::CONSENT_CLASSIFICATIONS,
        ];
    }

    public function createInstrument(array $data, User $actor): array
    {
        $programme = DB::table('inclusive_finance_programmes')->where('id', $data['programme_id'])->first();
        if (! $programme) {
            throw new InvalidArgumentException('Programme not found.');
        }

        $code = strtoupper(trim($data['code']));
        if (DB::table('programme_instruments')->where('programme_id', $programme->id)->whereRaw('UPPER(code) = ?', [$code])->exists()) {
            throw new InvalidArgumentException('An instrument with this code already exists for the programme.');
        }

        $channels = array_values(array_intersect(self::CHANNELS, $data['channels'] ?? ['app', 'web']));
        if ($channels === []) {
            throw new InvalidArgumentException('At least one supported delivery channel is required.');
        }

        $locales = array_values(array_intersect(self::LOCALES, $data['supported_locales'] ?? ['en']));
        if (! in_array($data['default_locale'] ?? 'en', $locales, true)) {
            $locales[] = $data['default_locale'] ?? 'en';
        }

        $scheduleConfig = $this->normaliseScheduleConfig($data['schedule_config'] ?? []);

        $id = DB::table('programme_instruments')->insertGetId([
            'programme_id' => $programme->id,
            'code' => $code,
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'outcome_domain' => $data['outcome_domain'],
            'default_measurement_stage' => $data['default_measurement_stage'] ?? 'check_in',
            'consent_classification' => $data['consent_classification'] ?? 'programme_measurement',
            'channels' => json_encode($channels),
            'default_locale' => $data['default_locale'] ?? 'en',
            'supported_locales' => json_encode($locales),
            'schedule_config' => $scheduleConfig === [] ? null : json_encode($scheduleConfig),
            'status' => $data['status'] ?? 'draft',
            'version' => $data['version'] ?? '1.0',
            'active_from' => $data['active_from'] ?? null,
            'active_to' => $data['active_to'] ?? null,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (($data['status'] ?? 'draft') === 'active') {
            $this->generateFollowUps($programme->id, $id);
        }

        return $this->instrumentPayload(DB::table('programme_instruments')->where('id', $id)->first(), true);
    }

    public function updateInstrument(int $instrumentId, array $data, User $actor): array
    {
        $instrument = DB::table('programme_instruments')->where('id', $instrumentId)->first();
        if (! $instrument) {
            throw new InvalidArgumentException('Programme instrument not found.');
        }

        $update = collect($data)->only([
            'name',
            'description',
            'outcome_domain',
            'default_measurement_stage',
            'consent_classification',
            'default_locale',
            'status',
            'version',
            'active_from',
            'active_to',
        ])->all();

        if (array_key_exists('channels', $data)) {
            $channels = array_values(array_intersect(self::CHANNELS, (array) $data['channels']));
            if ($channels === []) {
                throw new InvalidArgumentException('At least one supported delivery channel is required.');
            }
            $update['channels'] = json_encode($channels);
        }

        if (array_key_exists('supported_locales', $data)) {
            $locales = array_values(array_intersect(self::LOCALES, (array) $data['supported_locales']));
            if ($locales === []) {
                $locales = ['en'];
            }
            $update['supported_locales'] = json_encode($locales);
        }

        if (array_key_exists('schedule_config', $data)) {
            $schedule = $this->normaliseScheduleConfig((array) $data['schedule_config']);
            $update['schedule_config'] = $schedule === [] ? null : json_encode($schedule);
        }

        $update['updated_by'] = $actor->id;
        $update['updated_at'] = now();

        DB::table('programme_instruments')->where('id', $instrumentId)->update($update);

        $fresh = DB::table('programme_instruments')->where('id', $instrumentId)->first();
        if ($fresh->status === 'active') {
            $this->generateFollowUps((int) $fresh->programme_id, $instrumentId);
        }

        return $this->instrumentPayload($fresh, true);
    }

    public function addQuestion(int $instrumentId, array $data): array
    {
        $instrument = DB::table('programme_instruments')->where('id', $instrumentId)->first();
        if (! $instrument) {
            throw new InvalidArgumentException('Programme instrument not found.');
        }

        $code = strtoupper(trim($data['code']));
        if (DB::table('programme_instrument_questions')->where('instrument_id', $instrumentId)->whereRaw('UPPER(code) = ?', [$code])->exists()) {
            throw new InvalidArgumentException('A question with this code already exists in this instrument.');
        }

        $id = DB::table('programme_instrument_questions')->insertGetId([
            'instrument_id' => $instrumentId,
            'indicator_definition_id' => $data['indicator_definition_id'] ?? null,
            'code' => $code,
            'answer_type' => $data['answer_type'],
            'required' => (bool) ($data['required'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'options' => isset($data['options']) ? json_encode(array_values($data['options'])) : null,
            'validation_rules' => isset($data['validation_rules']) ? json_encode($data['validation_rules']) : null,
            'verification_source' => $data['verification_source'] ?? 'self_reported',
            'credit_decision_eligible' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->upsertTranslation($id, [
            'locale' => $instrument->default_locale ?: 'en',
            'prompt' => $data['prompt'],
            'help_text' => $data['help_text'] ?? null,
            'options' => $data['translated_options'] ?? $data['options'] ?? null,
        ]);

        return $this->questionPayload(DB::table('programme_instrument_questions')->where('id', $id)->first(), $instrument->default_locale ?: 'en');
    }

    public function upsertTranslation(int $questionId, array $data): array
    {
        if (! DB::table('programme_instrument_questions')->where('id', $questionId)->exists()) {
            throw new InvalidArgumentException('Programme question not found.');
        }

        if (! in_array($data['locale'], self::LOCALES, true)) {
            throw new InvalidArgumentException('Unsupported programme locale.');
        }

        $existing = DB::table('programme_question_translations')
            ->where('question_id', $questionId)
            ->where('locale', $data['locale'])
            ->first();

        DB::table('programme_question_translations')->updateOrInsert(
            ['question_id' => $questionId, 'locale' => $data['locale']],
            [
                'prompt' => $data['prompt'],
                'help_text' => $data['help_text'] ?? null,
                'options' => isset($data['options']) ? json_encode($data['options']) : null,
                'created_at' => $existing?->created_at ?? now(),
                'updated_at' => now(),
            ],
        );

        return [
            'question_id' => $questionId,
            'locale' => $data['locale'],
            'prompt' => $data['prompt'],
            'help_text' => $data['help_text'] ?? null,
            'options' => $data['options'] ?? [],
        ];
    }

    public function dueInstruments(User $user, string $channel = 'app', ?string $locale = null): array
    {
        if (! in_array($channel, self::CHANNELS, true)) {
            throw new InvalidArgumentException('Unsupported programme delivery channel.');
        }

        $locale = $this->normaliseLocale($locale ?: $user->preferred_language ?: 'en');
        $profile = DB::table('inclusive_finance_profiles')->where('user_id', $user->id)->first();
        $measurementConsent = (bool) ($profile?->programme_measurement_consent ?? false);
        $this->generateFollowUpsForUser($user);

        $rows = DB::table('programme_follow_up_schedules as schedule')
            ->join('inclusive_finance_enrolments as enrolment', 'enrolment.id', '=', 'schedule.enrolment_id')
            ->join('programme_instruments as instrument', 'instrument.id', '=', 'schedule.instrument_id')
            ->join('inclusive_finance_programmes as programme', 'programme.id', '=', 'enrolment.programme_id')
            ->where('enrolment.user_id', $user->id)
            ->where('enrolment.status', 'enrolled')
            ->where('instrument.status', 'active')
            ->whereIn('schedule.status', ['scheduled', 'due', 'overdue', 'opened'])
            ->where('schedule.due_at', '<=', now()->addDays(14))
            ->orderBy('schedule.due_at')
            ->select([
                'schedule.id as schedule_id',
                'schedule.measurement_stage',
                'schedule.due_at',
                'schedule.status as schedule_status',
                'instrument.*',
                'programme.code as programme_code',
                'programme.name as programme_name',
            ])
            ->get()
            ->filter(function ($row) use ($channel, $measurementConsent) {
                if (! in_array($channel, $this->json($row->channels), true)) {
                    return false;
                }

                return $row->consent_classification !== 'programme_measurement'
                    || $measurementConsent;
            })
            ->map(function ($row) use ($locale) {
                $payload = $this->instrumentPayload($row, false, $locale);
                $payload['schedule'] = [
                    'id' => (int) $row->schedule_id,
                    'measurement_stage' => $row->measurement_stage,
                    'due_at' => $row->due_at,
                    'status' => $this->effectiveScheduleStatus($row->schedule_status, $row->due_at),
                ];
                $payload['programme'] = [
                    'code' => $row->programme_code,
                    'name' => $row->programme_name,
                ];

                return $payload;
            })
            ->values()
            ->all();

        return [
            'instruments' => $rows,
            'channel' => $channel,
            'locale' => $locale,
            'fallback_locale' => 'en',
            'translation_policy' => 'If a reviewed translation is unavailable, OpFin falls back to English rather than generating an unverified translation.',
        ];
    }

    public function submitResponse(
        User $user,
        int $instrumentId,
        array $answers,
        string $channel,
        ?string $locale = null,
        ?int $scheduleId = null,
        ?User $capturedBy = null,
    ): array {
        if (! in_array($channel, self::CHANNELS, true)) {
            throw new InvalidArgumentException('Unsupported programme delivery channel.');
        }

        $instrument = DB::table('programme_instruments')->where('id', $instrumentId)->first();
        if (! $instrument || $instrument->status !== 'active') {
            throw new InvalidArgumentException('Programme instrument is not active.');
        }

        if (! in_array($channel, $this->json($instrument->channels), true)) {
            throw new InvalidArgumentException('This programme instrument is not enabled for the selected channel.');
        }

        $enrolment = $this->assertProgrammeParticipation(
            $user,
            (int) $instrument->programme_id,
            $instrument->consent_classification === 'programme_measurement',
        );
        $schedule = null;
        if ($scheduleId) {
            $schedule = DB::table('programme_follow_up_schedules')
                ->where('id', $scheduleId)
                ->where('enrolment_id', $enrolment->id)
                ->where('instrument_id', $instrumentId)
                ->first();
            if (! $schedule) {
                throw new InvalidArgumentException('Programme follow-up schedule does not belong to this participant.');
            }
            if ($schedule->status === 'completed') {
                throw new InvalidArgumentException('This programme follow-up is already completed.');
            }
        }

        $questions = DB::table('programme_instrument_questions')
            ->where('instrument_id', $instrumentId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $answerIds = collect($answers)
            ->map(fn ($answer) => (int) ($answer['question_id'] ?? 0))
            ->values();
        if ($answerIds->duplicates()->isNotEmpty()) {
            throw new InvalidArgumentException('A programme question may only be answered once per response.');
        }

        $questionIds = $questions->pluck('id')->map(fn ($id) => (int) $id);
        if ($answerIds->diff($questionIds)->isNotEmpty()) {
            throw new InvalidArgumentException('Programme response contains a question that does not belong to this instrument.');
        }

        $answerMap = collect($answers)->keyBy(fn ($answer) => (int) ($answer['question_id'] ?? 0));
        foreach ($questions as $question) {
            $provided = $answerMap->has((int) $question->id);
            if ($question->required && ! $provided) {
                throw new InvalidArgumentException('A required programme question was not answered.');
            }
        }

        $locale = $this->normaliseLocale($locale ?: $user->preferred_language ?: $instrument->default_locale ?: 'en');
        $stage = $schedule?->measurement_stage ?: $instrument->default_measurement_stage;

        return DB::transaction(function () use (
            $user,
            $instrument,
            $instrumentId,
            $enrolment,
            $schedule,
            $scheduleId,
            $questions,
            $answerMap,
            $channel,
            $locale,
            $stage,
            $capturedBy,
        ) {
            $responseId = DB::table('programme_instrument_responses')->insertGetId([
                'instrument_id' => $instrumentId,
                'schedule_id' => $scheduleId,
                'enrolment_id' => $enrolment->id,
                'user_id' => $user->id,
                'measurement_stage' => $stage,
                'channel' => $channel,
                'locale' => $locale,
                'status' => 'completed',
                'provenance' => json_encode([
                    'channel' => $channel,
                    'captured_by_user_id' => $capturedBy?->id,
                    'instrument_version' => $instrument->version,
                ]),
                'credit_decision_eligible' => false,
                'started_at' => now(),
                'completed_at' => now(),
                'captured_by' => $capturedBy?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($questions as $question) {
                if (! $answerMap->has((int) $question->id)) {
                    continue;
                }

                $raw = $answerMap->get((int) $question->id);
                $normalised = $this->normaliseAnswer($question, $raw['value'] ?? null);

                DB::table('programme_instrument_answers')->insert([
                    'response_id' => $responseId,
                    'question_id' => $question->id,
                    'numeric_value' => $normalised['numeric_value'],
                    'text_value' => $normalised['text_value'],
                    'boolean_value' => $normalised['boolean_value'],
                    'json_value' => $normalised['json_value'] === null ? null : json_encode($normalised['json_value']),
                    'unit' => $normalised['unit'],
                    'credit_decision_eligible' => false,
                    'observed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($question->indicator_definition_id) {
                    DB::table('programme_outcome_observations')->insert([
                        'programme_id' => $instrument->programme_id,
                        'enrolment_id' => $enrolment->id,
                        'user_id' => $user->id,
                        'indicator_definition_id' => $question->indicator_definition_id,
                        'measurement_stage' => $stage,
                        'numeric_value' => $normalised['numeric_value'],
                        'text_value' => $normalised['text_value'],
                        'boolean_value' => $normalised['boolean_value'],
                        'unit' => $normalised['unit'],
                        'source_type' => $channel === 'assisted' ? 'administrative' : 'customer',
                        'source_reference' => 'instrument-response:'.$responseId,
                        'provenance' => json_encode([
                            'instrument_id' => $instrumentId,
                            'question_id' => $question->id,
                            'channel' => $channel,
                            'locale' => $locale,
                        ]),
                        'verification_status' => $question->verification_source === 'self_reported' ? 'self_reported' : 'not_applicable',
                        'credit_decision_eligible' => false,
                        'observed_at' => now(),
                        'collected_by' => $capturedBy?->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if ($scheduleId) {
                DB::table('programme_follow_up_schedules')->where('id', $scheduleId)->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return [
                'response_id' => $responseId,
                'recorded' => true,
                'programme_id' => (int) $instrument->programme_id,
                'measurement_stage' => $stage,
                'channel' => $channel,
                'locale' => $locale,
                'credit_decision_eligible' => false,
            ];
        });
    }

    public function generateFollowUps(?int $programmeId = null, ?int $instrumentId = null): array
    {
        $instruments = DB::table('programme_instruments')
            ->where('status', 'active')
            ->when($programmeId, fn ($query) => $query->where('programme_id', $programmeId))
            ->when($instrumentId, fn ($query) => $query->where('id', $instrumentId))
            ->get();

        $created = 0;
        foreach ($instruments as $instrument) {
            $scheduleConfig = $this->normaliseScheduleConfig($this->json($instrument->schedule_config));
            if ($scheduleConfig === []) {
                continue;
            }

            $enrolments = DB::table('inclusive_finance_enrolments')
                ->where('programme_id', $instrument->programme_id)
                ->where('status', 'enrolled')
                ->whereNotNull('enrolled_at')
                ->get();

            foreach ($enrolments as $enrolment) {
                foreach ($scheduleConfig as $item) {
                    $dueAt = Carbon::parse($enrolment->enrolled_at)->addDays((int) $item['offset_days']);
                    $exists = DB::table('programme_follow_up_schedules')
                        ->where('enrolment_id', $enrolment->id)
                        ->where('instrument_id', $instrument->id)
                        ->where('measurement_stage', $item['stage'])
                        ->where('due_at', $dueAt)
                        ->exists();
                    if ($exists) {
                        continue;
                    }

                    DB::table('programme_follow_up_schedules')->insert([
                        'enrolment_id' => $enrolment->id,
                        'instrument_id' => $instrument->id,
                        'measurement_stage' => $item['stage'],
                        'due_at' => $dueAt,
                        'status' => $dueAt->isPast() ? 'overdue' : 'scheduled',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $created++;
                }
            }
        }

        DB::table('programme_follow_up_schedules')
            ->whereIn('status', ['scheduled', 'due'])
            ->where('due_at', '<', now())
            ->update(['status' => 'overdue', 'updated_at' => now()]);

        DB::table('programme_follow_up_schedules')
            ->where('status', 'scheduled')
            ->whereBetween('due_at', [now(), now()->addDay()])
            ->update(['status' => 'due', 'updated_at' => now()]);

        return ['generated' => $created];
    }

    public function operationsSummary(?int $programmeId = null): array
    {
        $base = DB::table('programme_follow_up_schedules as schedule')
            ->join('inclusive_finance_enrolments as enrolment', 'enrolment.id', '=', 'schedule.enrolment_id')
            ->join('programme_instruments as instrument', 'instrument.id', '=', 'schedule.instrument_id');

        if ($programmeId) {
            $base->where('enrolment.programme_id', $programmeId);
        }

        $all = (clone $base)->select([
            'schedule.id',
            'schedule.status',
            'schedule.due_at',
            'schedule.completed_at',
            'schedule.measurement_stage',
            'instrument.id as instrument_id',
            'instrument.name as instrument_name',
            'enrolment.programme_id',
            'enrolment.user_id',
        ])->get();

        $programmeIds = $programmeId
            ? [$programmeId]
            : DB::table('inclusive_finance_programmes')->pluck('id')->map(fn ($id) => (int) $id)->all();

        $activeEnrolments = DB::table('inclusive_finance_enrolments')
            ->whereIn('programme_id', $programmeIds ?: [-1])
            ->where('status', 'enrolled')
            ->count();

        $consentWithdrawals = DB::table('inclusive_finance_enrolments as enrolment')
            ->join('inclusive_finance_profiles as profile', 'profile.user_id', '=', 'enrolment.user_id')
            ->whereIn('enrolment.programme_id', $programmeIds ?: [-1])
            ->where('enrolment.status', 'enrolled')
            ->where('profile.programme_measurement_consent', false)
            ->count();

        return [
            'programme_id' => $programmeId,
            'active_enrolments' => $activeEnrolments,
            'scheduled' => $all->where('status', 'scheduled')->count(),
            'due_next_7_days' => $all->filter(fn ($row) => in_array($row->status, ['scheduled', 'due'], true) && Carbon::parse($row->due_at)->between(now(), now()->addDays(7)))->count(),
            'overdue' => $all->where('status', 'overdue')->count(),
            'completed' => $all->where('status', 'completed')->count(),
            'consent_exceptions' => $consentWithdrawals,
            'baseline_missing' => $this->missingStageCount($programmeIds, 'baseline'),
            'follow_ups' => $all->sortBy('due_at')->take(100)->values()->map(fn ($row) => [
                'id' => (int) $row->id,
                'programme_id' => (int) $row->programme_id,
                'user_id' => (int) $row->user_id,
                'instrument_id' => (int) $row->instrument_id,
                'instrument_name' => $row->instrument_name,
                'measurement_stage' => $row->measurement_stage,
                'due_at' => $row->due_at,
                'status' => $this->effectiveScheduleStatus($row->status, $row->due_at),
            ])->all(),
            'data_quality' => [
                'consent_exceptions' => $consentWithdrawals,
                'baseline_missing' => $this->missingStageCount($programmeIds, 'baseline'),
            ],
        ];
    }

    public function templates(): array
    {
        return ['templates' => array_values($this->templateDefinitions())];
    }

    public function applyTemplate(int $programmeId, string $templateCode, User $actor): array
    {
        $templates = $this->templateDefinitions();
        if (! isset($templates[$templateCode])) {
            throw new InvalidArgumentException('Unknown programme template.');
        }

        $programme = DB::table('inclusive_finance_programmes')->where('id', $programmeId)->first();
        if (! $programme) {
            throw new InvalidArgumentException('Programme not found.');
        }

        $template = $templates[$templateCode];

        DB::table('programme_theories_of_change')->updateOrInsert(
            ['programme_id' => $programmeId],
            [
                'problem_statement' => $template['theory']['problem_statement'],
                'inputs' => json_encode($template['theory']['inputs']),
                'interventions' => json_encode($template['theory']['interventions']),
                'outputs' => json_encode($template['theory']['outputs']),
                'outcomes' => json_encode($template['theory']['outcomes']),
                'impact' => json_encode($template['theory']['impact']),
                'assumptions' => json_encode($template['theory']['assumptions']),
                'risks' => json_encode($template['theory']['risks']),
                'evidence_sources' => json_encode($template['theory']['evidence_sources']),
                'version' => 'template-1.0',
                'status' => 'draft',
                'updated_by' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $created = [];
        foreach ($template['instruments'] as $definition) {
            $existing = DB::table('programme_instruments')
                ->where('programme_id', $programmeId)
                ->where('code', $definition['code'])
                ->first();

            if ($existing) {
                $created[] = $this->instrumentPayload($existing, true);
                continue;
            }

            $instrument = $this->createInstrument(array_merge($definition, [
                'programme_id' => $programmeId,
                'status' => 'draft',
            ]), $actor);

            foreach ($definition['questions'] as $question) {
                $this->addQuestion($instrument['id'], $question);
            }
            $created[] = $this->instrumentPayload(DB::table('programme_instruments')->where('id', $instrument['id'])->first(), true);
        }

        return [
            'programme_id' => $programmeId,
            'template_code' => $templateCode,
            'template_name' => $template['name'],
            'instruments' => $created,
            'activation_required' => true,
            'notice' => 'Templates are editable starting points. Targets, translations and programme agreements must be reviewed before activation.',
        ];
    }

    public function createPartnerInvitation(array $data, User $actor): array
    {
        $programme = DB::table('inclusive_finance_programmes')->where('id', $data['programme_id'])->first();
        if (! $programme || ! $programme->partner_id || (int) $programme->partner_id !== (int) $data['partner_id']) {
            throw new InvalidArgumentException('The programme must already be linked to the specified partner.');
        }

        $token = Str::random(64);
        $id = DB::table('programme_partner_invitations')->insertGetId([
            'programme_id' => $data['programme_id'],
            'partner_id' => $data['partner_id'],
            'invited_name' => trim($data['invited_name']),
            'invited_phone' => $data['invited_phone'] ?? null,
            'invited_email' => $data['invited_email'] ?? null,
            'token_hash' => hash('sha256', $token),
            'delivery_token_encrypted' => Crypt::encryptString($token),
            'access_level' => $data['access_level'] ?? 'read_only',
            'status' => 'pending',
            'expires_at' => $data['expires_at'] ?? now()->addDays(7),
            'created_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'invitation_id' => $id,
            'programme_id' => (int) $data['programme_id'],
            'access_level' => $data['access_level'] ?? 'read_only',
            'expires_at' => $data['expires_at'] ?? now()->addDays(7)->toIso8601String(),
            'activation_token' => $token,
            'delivery_status' => 'not_sent',
            'notice' => 'The invitation token is returned once for authorised delivery. OpFin does not fabricate email/SMS delivery when no provider action was requested.',
        ];
    }

    public function acceptPartnerInvitation(array $data): array
    {
        $hash = hash('sha256', $data['token']);
        $invitation = DB::table('programme_partner_invitations')
            ->where('token_hash', $hash)
            ->where('status', 'pending')
            ->first();

        if (! $invitation || now()->greaterThan(Carbon::parse($invitation->expires_at))) {
            throw new InvalidArgumentException('Programme-partner invitation is invalid or expired.');
        }

        $phone = trim($data['phone']);
        $email = isset($data['email']) ? trim($data['email']) : null;

        if ($invitation->invited_phone && trim((string) $invitation->invited_phone) !== $phone) {
            throw new InvalidArgumentException('The activation phone must match the number on the programme invitation.');
        }
        if ($invitation->invited_email && (! $email || strcasecmp(trim((string) $invitation->invited_email), $email) !== 0)) {
            throw new InvalidArgumentException('The activation email must match the address on the programme invitation.');
        }

        $otpRecord = Otp::where('phone', $phone)->first();
        $verificationToken = (string) ($data['verification_token'] ?? '');
        $verifiedPhone = $otpRecord
            && $otpRecord->verified_at
            && $otpRecord->verification_token_hash
            && now()->lte($otpRecord->verified_at->copy()->addMinutes(10))
            && hash_equals($otpRecord->verification_token_hash, hash('sha256', $verificationToken));

        if (! $verifiedPhone) {
            throw new InvalidArgumentException('Verify the invited phone number with an OpFin OTP before activating the programme-partner account.');
        }

        if (User::withoutGlobalScopes()->where('phone', $phone)->exists()) {
            throw new InvalidArgumentException('A dedicated programme-partner account must use a phone number not already attached to another OpFin account.');
        }
        if ($email && User::withoutGlobalScopes()->where('email', $email)->exists()) {
            throw new InvalidArgumentException('A dedicated programme-partner account must use an email address not already attached to another OpFin account.');
        }
        if ($this->weakPin((string) $data['pin'])) {
            throw new InvalidArgumentException('Choose a less predictable 6-digit PIN.');
        }

        return DB::transaction(function () use ($data, $invitation, $phone, $email, $otpRecord) {
            $user = User::withoutGlobalScopes()->create([
                'name' => trim($data['name']),
                'first_name' => trim($data['first_name'] ?? $data['name']),
                'last_name' => trim($data['last_name'] ?? ''),
                'phone' => $phone,
                'email' => $email,
                'password' => Hash::make($data['pin']),
                'role' => User::ROLE_PROGRAMME_PARTNER,
                'preferred_language' => $this->normaliseLocale($data['preferred_language'] ?? 'en'),
                'phone_verified_at' => now(),
            ]);

            $otpRecord?->delete();

            DB::table('programme_partner_access')->insert([
                'programme_id' => $invitation->programme_id,
                'partner_id' => $invitation->partner_id,
                'user_id' => $user->id,
                'access_level' => $invitation->access_level,
                'can_view_individual_records' => false,
                'status' => 'active',
                'granted_by' => $invitation->created_by,
                'granted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('programme_partner_invitations')->where('id', $invitation->id)->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'accepted_user_id' => $user->id,
                'delivery_token_encrypted' => null,
                'updated_at' => now(),
            ]);

            return [
                'user_id' => $user->id,
                'programme_id' => (int) $invitation->programme_id,
                'role' => User::ROLE_PROGRAMME_PARTNER,
                'access_level' => $invitation->access_level,
                'individual_records_exposed' => false,
            ];
        });
    }

    public function partnerUsers(int $programmeId): array
    {
        if (! DB::table('inclusive_finance_programmes')->where('id', $programmeId)->exists()) {
            throw new InvalidArgumentException('Programme not found.');
        }

        $users = DB::table('programme_partner_access as access')
            ->join('users', 'users.id', '=', 'access.user_id')
            ->where('access.programme_id', $programmeId)
            ->orderByDesc('access.granted_at')
            ->select([
                'access.user_id',
                'access.partner_id',
                'access.access_level',
                'access.status',
                'access.granted_at',
                'access.revoked_at',
                'users.name',
                'users.phone',
                'users.email',
            ])
            ->get()
            ->map(fn ($row) => [
                'user_id' => (int) $row->user_id,
                'partner_id' => (int) $row->partner_id,
                'name' => $row->name,
                'phone' => $row->phone,
                'email' => $row->email,
                'access_level' => $row->access_level,
                'status' => $row->status,
                'granted_at' => $row->granted_at,
                'revoked_at' => $row->revoked_at,
                'individual_records_exposed' => false,
            ])
            ->values()
            ->all();

        $invitations = DB::table('programme_partner_invitations')
            ->where('programme_id', $programmeId)
            ->whereIn('status', ['pending', 'accepted'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($row) {
                $expired = now()->greaterThan(Carbon::parse($row->expires_at));
                $activationToken = null;

                if ($row->status === 'pending' && ! $expired && $row->delivery_token_encrypted) {
                    try {
                        $activationToken = Crypt::decryptString($row->delivery_token_encrypted);
                    } catch (\Throwable) {
                        $activationToken = null;
                    }
                }

                return [
                    'id' => (int) $row->id,
                    'partner_id' => (int) $row->partner_id,
                    'invited_name' => $row->invited_name,
                    'invited_phone' => $row->invited_phone,
                    'invited_email' => $row->invited_email,
                    'access_level' => $row->access_level,
                    'status' => $expired && $row->status === 'pending' ? 'expired' : $row->status,
                    'expires_at' => $row->expires_at,
                    'accepted_at' => $row->accepted_at,
                    'accepted_user_id' => $row->accepted_user_id ? (int) $row->accepted_user_id : null,
                    'activation_token' => $activationToken,
                    'delivery_status' => 'not_sent',
                ];
            })
            ->values()
            ->all();

        return [
            'programme_id' => $programmeId,
            'users' => $users,
            'invitations' => $invitations,
            'access_boundary' => 'Programme-partner identities are dedicated and programme-scoped. Individual participant records remain unavailable.',
        ];
    }

    public function revokePartnerAccess(int $programmeId, int $userId): array
    {
        $updated = DB::table('programme_partner_access')
            ->where('programme_id', $programmeId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->update([
                'status' => 'revoked',
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);

        return [
            'programme_id' => $programmeId,
            'user_id' => $userId,
            'revoked' => $updated > 0,
        ];
    }

    public function markOpened(User $user, int $scheduleId): void
    {
        $ownedSchedule = DB::table('programme_follow_up_schedules as schedule')
            ->join('inclusive_finance_enrolments as enrolment', 'enrolment.id', '=', 'schedule.enrolment_id')
            ->where('schedule.id', $scheduleId)
            ->where('enrolment.user_id', $user->id)
            ->whereIn('schedule.status', ['scheduled', 'due', 'overdue'])
            ->value('schedule.id');

        if (! $ownedSchedule) {
            throw new InvalidArgumentException('Programme follow-up is not available for this customer.');
        }

        DB::table('programme_follow_up_schedules')->where('id', $ownedSchedule)->update([
            'status' => 'opened',
            'opened_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function generateFollowUpsForUser(User $user): void
    {
        $programmeIds = DB::table('inclusive_finance_enrolments')
            ->where('user_id', $user->id)
            ->where('status', 'enrolled')
            ->pluck('programme_id');

        foreach ($programmeIds as $programmeId) {
            $this->generateFollowUps((int) $programmeId);
        }
    }

    private function assertProgrammeParticipation(User $user, int $programmeId, bool $requiresMeasurementConsent): object
    {
        if ($requiresMeasurementConsent) {
            $profile = DB::table('inclusive_finance_profiles')->where('user_id', $user->id)->first();
            if (! $profile || ! $profile->programme_measurement_consent) {
                throw new InvalidArgumentException('Programme measurement consent is required before this programme check-in can be recorded.');
            }
        }

        $enrolment = DB::table('inclusive_finance_enrolments')
            ->where('programme_id', $programmeId)
            ->where('user_id', $user->id)
            ->where('status', 'enrolled')
            ->first();

        if (! $enrolment) {
            throw new InvalidArgumentException('Active programme enrolment is required before a programme check-in can be recorded.');
        }

        return $enrolment;
    }

    private function instrumentPayload(object $instrument, bool $includeQuestions = false, ?string $locale = null): array
    {
        $locale = $this->normaliseLocale($locale ?: $instrument->default_locale ?: 'en');
        $questions = [];
        if ($includeQuestions || $locale) {
            $questions = DB::table('programme_instrument_questions')
                ->where('instrument_id', $instrument->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn ($question) => $this->questionPayload($question, $locale))
                ->values()
                ->all();
        }

        return [
            'id' => (int) $instrument->id,
            'programme_id' => (int) $instrument->programme_id,
            'code' => $instrument->code,
            'name' => $instrument->name,
            'description' => $instrument->description,
            'outcome_domain' => $instrument->outcome_domain,
            'default_measurement_stage' => $instrument->default_measurement_stage,
            'consent_classification' => $instrument->consent_classification,
            'channels' => $this->json($instrument->channels),
            'default_locale' => $instrument->default_locale,
            'supported_locales' => $this->json($instrument->supported_locales),
            'schedule_config' => $this->json($instrument->schedule_config),
            'status' => $instrument->status,
            'version' => $instrument->version,
            'active_from' => $instrument->active_from,
            'active_to' => $instrument->active_to,
            'questions' => $questions,
            'credit_decision_eligible' => false,
        ];
    }

    private function questionPayload(object $question, string $locale): array
    {
        $translation = DB::table('programme_question_translations')
            ->where('question_id', $question->id)
            ->where('locale', $locale)
            ->first();

        $fallback = false;
        if (! $translation && $locale !== 'en') {
            $translation = DB::table('programme_question_translations')
                ->where('question_id', $question->id)
                ->where('locale', 'en')
                ->first();
            $fallback = true;
        }

        if (! $translation) {
            $translation = DB::table('programme_question_translations')
                ->where('question_id', $question->id)
                ->orderBy('id')
                ->first();
            $fallback = true;
        }

        return [
            'id' => (int) $question->id,
            'code' => $question->code,
            'indicator_definition_id' => $question->indicator_definition_id ? (int) $question->indicator_definition_id : null,
            'answer_type' => $question->answer_type,
            'required' => (bool) $question->required,
            'sort_order' => (int) $question->sort_order,
            'options' => $translation?->options ? $this->json($translation->options) : $this->json($question->options),
            'validation_rules' => $this->json($question->validation_rules),
            'verification_source' => $question->verification_source,
            'prompt' => $translation?->prompt ?? $question->code,
            'help_text' => $translation?->help_text,
            'locale' => $translation?->locale ?? 'en',
            'requested_locale' => $locale,
            'translation_fallback' => $fallback,
            'credit_decision_eligible' => false,
        ];
    }

    private function normaliseAnswer(object $question, mixed $value): array
    {
        $rules = $this->json($question->validation_rules);
        $result = [
            'numeric_value' => null,
            'text_value' => null,
            'boolean_value' => null,
            'json_value' => null,
            'unit' => $rules['unit'] ?? null,
        ];

        switch ($question->answer_type) {
            case 'integer':
            case 'currency_minor':
                if (! is_numeric($value) || (int) $value != $value) {
                    throw new InvalidArgumentException('A programme answer must be a whole number.');
                }
                $number = (int) $value;
                $this->assertRange($number, $rules);
                $result['numeric_value'] = $number;
                break;
            case 'decimal':
                if (! is_numeric($value)) {
                    throw new InvalidArgumentException('A programme answer must be numeric.');
                }
                $number = (float) $value;
                $this->assertRange($number, $rules);
                $result['numeric_value'] = $number;
                break;
            case 'boolean':
                if (! is_bool($value) && ! in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
                    throw new InvalidArgumentException('A programme answer must be yes or no.');
                }
                $result['boolean_value'] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                break;
            case 'single_choice':
                $options = $this->json($question->options);
                if (! in_array($value, $options, true)) {
                    throw new InvalidArgumentException('Programme answer is not one of the configured choices.');
                }
                $result['text_value'] = (string) $value;
                break;
            case 'multi_choice':
                if (! is_array($value)) {
                    throw new InvalidArgumentException('Programme answer must be a list of configured choices.');
                }
                $options = $this->json($question->options);
                foreach ($value as $selected) {
                    if (! in_array($selected, $options, true)) {
                        throw new InvalidArgumentException('Programme answer contains an unsupported choice.');
                    }
                }
                $result['json_value'] = array_values($value);
                break;
            default:
                $text = trim((string) $value);
                if (isset($rules['max_length']) && mb_strlen($text) > (int) $rules['max_length']) {
                    throw new InvalidArgumentException('Programme answer is longer than the configured limit.');
                }
                $result['text_value'] = $text;
        }

        return $result;
    }

    private function assertRange(float|int $number, array $rules): void
    {
        if (array_key_exists('min', $rules) && $number < $rules['min']) {
            throw new InvalidArgumentException('Programme answer is below the configured minimum.');
        }
        if (array_key_exists('max', $rules) && $number > $rules['max']) {
            throw new InvalidArgumentException('Programme answer is above the configured maximum.');
        }
    }

    private function normaliseScheduleConfig(array $config): array
    {
        $allowed = InclusiveImpactService::MEASUREMENT_STAGES;
        $normalised = [];
        foreach ($config as $item) {
            if (! is_array($item)) {
                continue;
            }
            $stage = $item['stage'] ?? null;
            $offset = $item['offset_days'] ?? null;
            if (! in_array($stage, $allowed, true) || ! is_numeric($offset) || (int) $offset < 0) {
                throw new InvalidArgumentException('Programme schedule entries require a supported stage and non-negative offset_days.');
            }
            $normalised[] = ['stage' => $stage, 'offset_days' => (int) $offset];
        }

        return $normalised;
    }

    private function normaliseLocale(string $locale): string
    {
        $locale = strtolower(trim($locale));
        if (in_array($locale, self::LOCALES, true)) {
            return $locale;
        }

        $base = explode('-', $locale)[0];

        return in_array($base, self::LOCALES, true) ? $base : 'en';
    }

    private function effectiveScheduleStatus(string $status, string $dueAt): string
    {
        if ($status === 'completed') {
            return 'completed';
        }
        if (Carbon::parse($dueAt)->isPast() && $status !== 'opened') {
            return 'overdue';
        }

        return $status;
    }

    private function missingStageCount(array $programmeIds, string $stage): int
    {
        if ($programmeIds === []) {
            return 0;
        }

        return DB::table('inclusive_finance_enrolments as enrolment')
            ->whereIn('enrolment.programme_id', $programmeIds)
            ->where('enrolment.status', 'enrolled')
            ->whereNotExists(function ($query) use ($stage) {
                $query->selectRaw('1')
                    ->from('programme_instrument_responses as response')
                    ->whereColumn('response.enrolment_id', 'enrolment.id')
                    ->where('response.measurement_stage', $stage)
                    ->where('response.status', 'completed');
            })
            ->count();
    }

    private function templateDefinitions(): array
    {
        $commonSchedule = [
            ['stage' => 'baseline', 'offset_days' => 0],
            ['stage' => '90_day', 'offset_days' => 90],
            ['stage' => '6_month', 'offset_days' => 180],
            ['stage' => '12_month', 'offset_days' => 365],
        ];

        $financialQuestions = [
            [
                'code' => 'EXPENSE_COVERAGE_DAYS',
                'prompt' => 'About how many days could your available money cover essential expenses?',
                'answer_type' => 'integer',
                'required' => true,
                'validation_rules' => ['min' => 0, 'max' => 3650, 'unit' => 'days'],
            ],
            [
                'code' => 'EMERGENCY_SAVINGS',
                'prompt' => 'How much emergency savings can you access now?',
                'answer_type' => 'currency_minor',
                'required' => true,
                'validation_rules' => ['min' => 0, 'unit' => 'UGX'],
            ],
            [
                'code' => 'REPAYMENT_STRESS',
                'prompt' => 'Are you currently struggling to keep up with repayments?',
                'answer_type' => 'boolean',
                'required' => true,
            ],
        ];

        return [
            'youth_women_resilience' => [
                'code' => 'youth_women_resilience',
                'name' => 'Youth and women financial resilience',
                'description' => 'Access, capability, resilience, livelihood and agency measurement for youth- and women-focused programmes.',
                'theory' => [
                    'problem_statement' => 'Young people and women can have financial access without meaningful use, resilience or improved livelihoods.',
                    'inputs' => ['Responsible financial services', 'Financial capability', 'Consent-governed data'],
                    'interventions' => ['Financial-health guidance', 'Appropriate finance', 'Savings and protection pathways'],
                    'outputs' => ['Customers complete check-ins', 'Customers use suitable financial services'],
                    'outcomes' => ['Improved resilience', 'More reliable livelihoods', 'Greater economic agency'],
                    'impact' => ['Sustainable financial participation and stronger livelihoods'],
                    'assumptions' => ['Products remain affordable and appropriate', 'Customers retain control over programme measurement'],
                    'risks' => ['Over-indebtedness', 'Digital exclusion', 'Programme metrics leaking into underwriting'],
                    'evidence_sources' => ['OpFin system records', 'Customer programme check-ins'],
                ],
                'instruments' => [[
                    'code' => 'YWR-FH',
                    'name' => 'Financial resilience check-in',
                    'description' => 'Short resilience and financial-health follow-up.',
                    'outcome_domain' => 'financial_health_resilience',
                    'default_measurement_stage' => 'baseline',
                    'consent_classification' => 'programme_measurement',
                    'channels' => self::CHANNELS,
                    'default_locale' => 'en',
                    'supported_locales' => self::LOCALES,
                    'schedule_config' => $commonSchedule,
                    'questions' => $financialQuestions,
                ]],
            ],
            'vsla_formal_bridge' => [
                'code' => 'vsla_formal_bridge',
                'name' => 'Savings group to formal finance bridge',
                'description' => 'Portable community-finance evidence and formal-finance progression.',
                'theory' => [
                    'problem_statement' => 'Reliable savings-group behaviour is often not portable into formal financial reputation.',
                    'inputs' => ['Savings-group evidence', 'Consent', 'Financial capability'],
                    'interventions' => ['Record community-finance history', 'Build formal reputation'],
                    'outputs' => ['Verified group evidence', 'Formal financial profile'],
                    'outcomes' => ['Broader appropriate financial access', 'Improved savings resilience'],
                    'impact' => ['Sustainable transition between community and formal finance'],
                    'assumptions' => ['Group evidence can be verified where required'],
                    'risks' => ['Unverified self-report being treated as credit truth'],
                    'evidence_sources' => ['Programme check-ins', 'Approved group/provider evidence'],
                ],
                'instruments' => [[
                    'code' => 'VSLA-BRIDGE',
                    'name' => 'Community finance progression',
                    'description' => 'Savings-group participation and progression check-in.',
                    'outcome_domain' => 'access_inclusion',
                    'default_measurement_stage' => 'baseline',
                    'consent_classification' => 'programme_measurement',
                    'channels' => self::CHANNELS,
                    'default_locale' => 'en',
                    'supported_locales' => self::LOCALES,
                    'schedule_config' => $commonSchedule,
                    'questions' => [
                        ['code' => 'GROUP_ACTIVE', 'prompt' => 'Are you currently active in a savings group?', 'answer_type' => 'boolean', 'required' => true],
                        ['code' => 'SAVINGS_BALANCE', 'prompt' => 'What is your current savings-group balance?', 'answer_type' => 'currency_minor', 'required' => false, 'validation_rules' => ['min' => 0, 'unit' => 'UGX']],
                        ['code' => 'FORMAL_PRODUCT_USED', 'prompt' => 'Have you used a formal financial product since joining this programme?', 'answer_type' => 'boolean', 'required' => true],
                    ],
                ]],
            ],
            'refugee_pwd_inclusion' => [
                'code' => 'refugee_pwd_inclusion',
                'name' => 'Refugee and disability inclusion',
                'description' => 'Accessibility, service usability and resilience outcomes without using protected status as a credit input.',
                'theory' => [
                    'problem_statement' => 'Displaced people and people with disabilities can face usability, documentation and service barriers.',
                    'inputs' => ['Accessible channels', 'Assisted onboarding', 'Appropriate finance'],
                    'interventions' => ['Accessible service delivery', 'Consent-governed programme support'],
                    'outputs' => ['Accessible journeys completed', 'Support barriers recorded'],
                    'outcomes' => ['Improved meaningful usage and resilience'],
                    'impact' => ['More equitable participation in formal finance'],
                    'assumptions' => ['Accessibility support does not weaken financial controls'],
                    'risks' => ['Protected attributes influencing underwriting', 'Inaccessible programme channels'],
                    'evidence_sources' => ['Accessibility preferences', 'Programme check-ins'],
                ],
                'instruments' => [[
                    'code' => 'INCLUSION-USABILITY',
                    'name' => 'Service usability and resilience',
                    'description' => 'Short programme usability check-in.',
                    'outcome_domain' => 'access_inclusion',
                    'default_measurement_stage' => 'baseline',
                    'consent_classification' => 'programme_measurement',
                    'channels' => self::CHANNELS,
                    'default_locale' => 'en',
                    'supported_locales' => self::LOCALES,
                    'schedule_config' => $commonSchedule,
                    'questions' => [
                        ['code' => 'SERVICE_INDEPENDENT', 'prompt' => 'Can you use OpFin financial services independently when you want to?', 'answer_type' => 'boolean', 'required' => true],
                        ['code' => 'SUPPORT_NEEDED', 'prompt' => 'Do you currently need assisted support to complete financial-service tasks?', 'answer_type' => 'boolean', 'required' => true],
                    ],
                ]],
            ],
            'employer_financial_wellness' => [
                'code' => 'employer_financial_wellness',
                'name' => 'Employer financial wellness',
                'description' => 'Employee financial health, resilience and capability outcomes.',
                'theory' => [
                    'problem_statement' => 'Regular income does not automatically translate into financial resilience.',
                    'inputs' => ['Employer-supported financial wellness', 'Personal finance tools'],
                    'interventions' => ['Budgeting', 'Savings', 'Responsible credit', 'Protection'],
                    'outputs' => ['Employees use personal-finance tools'],
                    'outcomes' => ['Lower financial stress', 'Improved emergency resilience'],
                    'impact' => ['More resilient households and workplaces'],
                    'assumptions' => ['Employer access does not expose private personal finances'],
                    'risks' => ['Employer overreach into employee financial data'],
                    'evidence_sources' => ['Aggregate programme metrics', 'Customer check-ins'],
                ],
                'instruments' => [[
                    'code' => 'EMP-FH',
                    'name' => 'Employee financial wellness',
                    'description' => 'Employee financial-health follow-up.',
                    'outcome_domain' => 'financial_health_resilience',
                    'default_measurement_stage' => 'baseline',
                    'consent_classification' => 'programme_measurement',
                    'channels' => ['app', 'web', 'whatsapp', 'assisted'],
                    'default_locale' => 'en',
                    'supported_locales' => self::LOCALES,
                    'schedule_config' => $commonSchedule,
                    'questions' => $financialQuestions,
                ]],
            ],
            'msme_livelihood' => [
                'code' => 'msme_livelihood',
                'name' => 'MSME and livelihood progression',
                'description' => 'Enterprise continuity, income reliability, jobs and dignified-work outcomes.',
                'theory' => [
                    'problem_statement' => 'Microenterprise access to finance can fail to translate into durable business and livelihood outcomes.',
                    'inputs' => ['Appropriate finance', 'Financial capability', 'Verified enterprise signals where available'],
                    'interventions' => ['Working-capital support', 'Financial planning'],
                    'outputs' => ['Enterprises access and use finance'],
                    'outcomes' => ['Business continuity', 'More reliable income', 'Jobs retained or created'],
                    'impact' => ['Stronger livelihoods and sustainable enterprise growth'],
                    'assumptions' => ['Finance is used productively and remains affordable'],
                    'risks' => ['Over-borrowing', 'Self-reported revenue overstated'],
                    'evidence_sources' => ['Programme check-ins', 'Approved provider evidence', 'OpFin financial records'],
                ],
                'instruments' => [[
                    'code' => 'MSME-OUTCOME',
                    'name' => 'Enterprise and livelihood follow-up',
                    'description' => 'Short business continuity and livelihood check-in.',
                    'outcome_domain' => 'livelihood_enterprise',
                    'default_measurement_stage' => 'baseline',
                    'consent_classification' => 'programme_measurement',
                    'channels' => self::CHANNELS,
                    'default_locale' => 'en',
                    'supported_locales' => self::LOCALES,
                    'schedule_config' => $commonSchedule,
                    'questions' => [
                        ['code' => 'ENTERPRISE_OPERATING', 'prompt' => 'Is your income-generating activity or business currently operating?', 'answer_type' => 'boolean', 'required' => true],
                        ['code' => 'MONTHLY_REVENUE', 'prompt' => 'What was the approximate revenue from this activity in the last month?', 'answer_type' => 'currency_minor', 'required' => false, 'validation_rules' => ['min' => 0, 'unit' => 'UGX']],
                        ['code' => 'WORKERS_TOTAL', 'prompt' => 'How many people currently work in the activity, including you?', 'answer_type' => 'integer', 'required' => false, 'validation_rules' => ['min' => 0, 'max' => 100000, 'unit' => 'people']],
                        ['code' => 'INCOME_RELIABLE', 'prompt' => 'Has income from this activity become more reliable?', 'answer_type' => 'single_choice', 'required' => true, 'options' => ['yes', 'about_the_same', 'less_reliable', 'prefer_not_to_say']],
                    ],
                ]],
            ],
        ];
    }

    private function weakPin(string $pin): bool
    {
        return preg_match('/^(\\d)\\1{5}$/', $pin) === 1
            || in_array($pin, ['012345', '123456', '234567', '345678', '456789', '987654', '876543', '765432', '654321', '543210'], true);
    }

    private function json(?string $value): array
    {
        if (! $value) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
